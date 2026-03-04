<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ClienteSuscripcion;
use App\Models\ContadorFactura;
use App\Models\Factura;
use App\Enums\FacturaEstadoEnum;
use App\Enums\ServicioTipoEnum;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FacturacionService
{
    /**
     * Genera el siguiente número de factura.
     */
    public static function generarSiguienteNumeroFactura(string $tipo = 'normal'): array
    {
        return DB::transaction(function () use ($tipo) {
            $esRectificativa = ($tipo === 'rectificativa');
            $formatoKey = $esRectificativa ? 'formato_factura_rectificativa' : 'formato_factura';
            $defaultFormato = $esRectificativa ? 'REC{YY}-00000' : 'FR{YY}-00000';
            
            $formato = ConfiguracionService::get($formatoKey, $defaultFormato);
            $prefijoSerie = substr($formato, 0, strpos($formato, '{'));

            $anoActual = Carbon::now()->year;

            $contador = ContadorFactura::lockForUpdate()->firstOrCreate(
                ['serie' => $prefijoSerie, 'anio' => $anoActual],
                ['ultimo_numero' => 0]
            );

            $nuevoNumero = $contador->ultimo_numero + 1;
            $contador->update(['ultimo_numero' => $nuevoNumero]);

            $anoDosDigitos = Carbon::now()->format('y');
            $serieCompleta = "{$prefijoSerie}{$anoDosDigitos}-";
            $padding = strlen(substr($formato, strrpos($formato, '-') + 1));
            $numeroConPadding = str_pad($nuevoNumero, $padding, '0', STR_PAD_LEFT);

            return [
                'serie'          => $serieCompleta,
                'numero_factura' => $serieCompleta . $numeroConPadding,
            ];
        });
    }

    /**
     * 🟢 MÉTODO 1: Para Ventas CONFIRMADAS Y PAGADAS (Flujo Automático/Stripe)
     * Genera la factura directamente como PAGADA.
     */
public static function generarFacturaInicial(
    Venta $venta,
    Carbon $fechaPago,
    ?string $metodoPago = 'manual',
    ?\App\Enums\FacturaEstadoEnum $estado = null,
    ?string $stripeInvoiceId = null,
    ?string $stripePaymentIntentId = null
): ?Factura {
    $estado = $estado ?? \App\Enums\FacturaEstadoEnum::PAGADA;

    $ventaItems = $venta->items()->whereHas('servicio', function ($q) {
        $q->where('tipo', ServicioTipoEnum::UNICO->value);
    })->get();

    if ($ventaItems->isEmpty()) {
        return null;
    }

    if (! self::tieneImporteUnicoReal($ventaItems)) {
        return null;
    }

    // ✅ Idempotencia: si ya existe factura inicial con ese estado, la devolvemos.
    // (No tocamos el criterio para no romperte lógica previa)
    $facturaExistente = $venta->facturas()
        ->where('estado', $estado)
        ->first();

    if ($facturaExistente) {
        // ✅ Si ahora tenemos trazabilidad Stripe y antes no, la “rellenamos” (sin cambiar nada más)
        $updates = [];

        if ($stripeInvoiceId && empty($facturaExistente->stripe_invoice_id)) {
            $updates['stripe_invoice_id'] = $stripeInvoiceId;
        }

        if ($stripePaymentIntentId && empty($facturaExistente->stripe_payment_intent_id)) {
            $updates['stripe_payment_intent_id'] = $stripePaymentIntentId;
        }

        if (! empty($updates)) {
            $facturaExistente->update($updates);
        }

        return $facturaExistente;
    }

    // ✅ Creamos factura con items como antes
    $factura = self::crearFacturaConItems($venta, $ventaItems, $estado, $fechaPago, $metodoPago);

    // ✅ Guardar trazabilidad Stripe si aplica
    if ($factura && ($stripeInvoiceId || $stripePaymentIntentId)) {
        $factura->update([
            'stripe_invoice_id'        => $stripeInvoiceId,
            'stripe_payment_intent_id' => $stripePaymentIntentId,
        ]);
    }

    return $factura;
}




    /**
     * 🟡 MÉTODO 2: Para Correcciones o Generación Manual (Flujo Admin)
     * Genera la factura como PENDIENTE DE PAGO.
     * Mantenemos este método porque lo usan CorreccionVentaService y EditVenta.
     */
    public static function generarFacturaParaVenta(Venta $venta): ?Factura
{
    $ventaItems = $venta->items()->whereHas('servicio', function ($q) {
        $q->where('tipo', ServicioTipoEnum::UNICO->value);
    })->get();

    if ($ventaItems->isEmpty()) {
        return null;
    }

    if (! self::tieneImporteUnicoReal($ventaItems)) {
        return null;
    }

    return self::crearFacturaConItems($venta, $ventaItems, FacturaEstadoEnum::PENDIENTE_PAGO, now(), null);
}

private static function tieneImporteUnicoReal($items): bool
{
    $total = 0.0;

    foreach ($items as $item) {
        $subtotal = $item->subtotal_aplicado;

        if ($subtotal === null) {
            $precioAplicado = $item->precio_unitario_aplicado ?? $item->precio_unitario ?? 0;
            $cantidad = (float) ($item->cantidad ?? 1);
            $subtotal = (float) $precioAplicado * $cantidad;
        }

        $total += (float) $subtotal;
    }

    return round($total, 2) > 0;
}

    /**
     * ⚙️ MÉTODO PRIVADO COMÚN (Para no repetir código fiscal)
     * Aquí es donde aplicamos la lógica de Canarias 0% para ambos casos.
     */
    private static function crearFacturaConItems(
    Venta $venta,
    $items,
    FacturaEstadoEnum $estado,
    Carbon $fechaEmision,
    ?string $metodoPago
): Factura {

    return DB::transaction(function () use ($venta, $items, $estado, $fechaEmision, $metodoPago) {

        $datosFactura  = self::generarSiguienteNumeroFactura();
        $fechaEmision  = $fechaEmision->copy()->startOfDay();
        $diasVencimiento = 30;

        $fechaVencimiento = $estado === FacturaEstadoEnum::PAGADA
            ? $fechaEmision->copy()
            : $fechaEmision->copy()->addDays($diasVencimiento);

        $cliente = $venta->cliente;

        $porcentajeIva = Cliente::getPorcentajeImpuesto(
            $cliente->codigo_postal,
            $cliente->provincia
        );

        $factura = Factura::create([
            'cliente_id'        => $venta->cliente_id,
            'venta_id'          => $venta->id,
            'serie'             => $datosFactura['serie'],
            'numero_factura'    => $datosFactura['numero_factura'],
            'estado'            => $estado,
            'metodo_pago'       => $metodoPago,
            'fecha_emision'     => $fechaEmision,
            'fecha_vencimiento' => $fechaVencimiento,
            'base_imponible'    => 0,
            'total_iva'         => 0,
            'total_factura'     => 0,
        ]);

        $baseImponibleTotal = 0;
        $totalIva           = 0;

        foreach ($items as $item) {
            $cantidad       = (float) ($item->cantidad ?? 1);
            $precioOriginal = (float) ($item->precio_unitario ?? 0);

            $precioAplicado   = $item->precio_unitario_aplicado ?? $item->precio_unitario ?? 0;
            $precioAplicado   = (float) $precioAplicado;

            $importeDescuento = 0;

            if ($item->descuento_tipo && $item->descuento_valor && is_null($item->precio_unitario_aplicado)) {
                if ($item->descuento_tipo === 'porcentaje') {
                    $importeDescuento = round($precioOriginal * ((float)$item->descuento_valor / 100), 2);
                    $precioAplicado   = $precioOriginal - $importeDescuento;
                } elseif ($item->descuento_tipo === 'fijo') {
                    $importeDescuento = round((float)$item->descuento_valor, 2);
                    $precioAplicado   = max(0, $precioOriginal - $importeDescuento);
                } elseif ($item->descuento_tipo === 'precio_final') {
                    $precioAplicado   = round((float)$item->descuento_valor, 2);
                    $importeDescuento = $precioOriginal - $precioAplicado;
                }
            } elseif ($item->precio_unitario_aplicado !== null) {
                $importeDescuento = $precioOriginal - $precioAplicado;
            }

            $subtotalLinea = round($precioAplicado * $cantidad, 2);
            $ivaLinea      = round($subtotalLinea * ((float)$porcentajeIva / 100), 2);

            $baseImponibleTotal += $subtotalLinea;
            $totalIva           += $ivaLinea;

          $factura->items()->create([
                'servicio_id'              => $item->servicio_id,
                'descripcion'              => $item->nombre_personalizado ?: ($item->servicio->nombre ?? 'Servicio'),
                'cantidad'                 => $cantidad,

                'precio_unitario'          => round($precioOriginal, 2),
                'precio_unitario_aplicado' => round($precioAplicado, 2),
                'importe_descuento'        => round($importeDescuento * $cantidad, 2),

                'porcentaje_iva'           => $porcentajeIva,
                'subtotal'                 => $subtotalLinea,

                'cliente_suscripcion_id'   => $item->cliente_suscripcion_id,
                'descuento_tipo'           => $item->descuento_tipo,
                'descuento_valor'          => $item->descuento_valor,
            ]);

        }

        $factura->update([
            'base_imponible' => round($baseImponibleTotal, 2),
            'total_iva'      => round($totalIva, 2),
            'total_factura'  => round($baseImponibleTotal + $totalIva, 2),
        ]);

        return $factura;
    });
}
    /**
     * Genera una factura para un cobro recurrente de Stripe (Webhook).
     */
public static function crearFacturaRecurrente(
    Cliente $cliente,
    ClienteSuscripcion $suscripcion,
    int $amountCents,
    Carbon $fechaPago,
    ?string $stripeInvoiceId = null,
    ?string $stripeInvoiceNumber = null,
    ?string $stripePaymentIntentId = null,
    ?Carbon $periodoInicio = null
): Factura {

    return DB::transaction(function () use (
        $cliente,
        $suscripcion,
        $amountCents,
        $fechaPago,
        $stripeInvoiceId,
        $stripeInvoiceNumber,
        $stripePaymentIntentId,
        $periodoInicio
    ) {
        $datosFactura = self::generarSiguienteNumeroFactura();

        $porcentajeIva = Cliente::getPorcentajeImpuesto($cliente->codigo_postal, $cliente->provincia);
        $factor = 1 + ($porcentajeIva / 100);

        $totalPagado   = $amountCents / 100;
        $baseImponible = round($totalPagado / $factor, 2);
        $iva           = round($totalPagado - $baseImponible, 2);

        $numeroRef = $stripeInvoiceNumber ?: $stripeInvoiceId;

        // ✅ Nombre base del servicio (lo que ya venías usando)
        $nombreBase = $suscripcion->nombre_personalizado ?? ($suscripcion->servicio->nombre ?? 'Suscripción');

        // ✅ Mes/Año (si nos lo pasan)
        $mesLabel = null;
        if ($periodoInicio) {
            $meses = [
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
            ];

            $m = (int) $periodoInicio->month;
            $y = (int) $periodoInicio->year;

            $mesLabel = ($meses[$m] ?? $periodoInicio->format('m')) . " {$y}";
        }

        $descripcionConMes = $mesLabel ? "{$nombreBase} — {$mesLabel}" : $nombreBase;

        $factura = Factura::create([
            'cliente_id'              => $cliente->id,
            'venta_id'                => $suscripcion->venta_origen_id,
            'serie'                   => $datosFactura['serie'],
            'numero_factura'          => $datosFactura['numero_factura'],
            'estado'                  => FacturaEstadoEnum::PAGADA,
            'metodo_pago'             => 'stripe',
            'fecha_emision'           => $fechaPago,
            'fecha_vencimiento'       => $fechaPago,

            // ✅ STRIPE
            'stripe_invoice_id'        => $stripeInvoiceId,
            'stripe_payment_intent_id' => $stripePaymentIntentId,

            'base_imponible'          => $baseImponible,
            'total_iva'               => $iva,
            'total_factura'           => $totalPagado,

            // ✅ Mantenemos tu texto, pero añadimos el mes si existe
            'observaciones_publicas' => $numeroRef
                ? "Renovación automática Stripe: {$numeroRef}" . ($mesLabel ? " ({$mesLabel})" : '')
                : ('Renovación automática Stripe' . ($mesLabel ? " ({$mesLabel})" : '')),
        ]);

        $factura->items()->create([
            'descripcion'            => $descripcionConMes,
            'cantidad'               => 1,
            'precio_unitario'        => $baseImponible,
            'porcentaje_iva'         => $porcentajeIva,
            'cuota_iva'              => $iva,
            'subtotal'               => $baseImponible,
            'total'                  => $totalPagado,
            'cliente_suscripcion_id' => $suscripcion->id,
            'servicio_id'            => $suscripcion->servicio_id,
        ]);

        return $factura;
    });
}




    /**
 * 🧾 Crea una factura recurrente manual (sin Stripe)
 * Usado para:
 * - Prorratas
 * - Cuotas mensuales
 * - SEPA pendiente
 * - Tarjeta pagada
 */
/* public static function crearFacturaRecurrenteManual(
    ClienteSuscripcion $suscripcion,
    Carbon $fechaFactura,
    float $baseImponible,
    FacturaEstadoEnum $estado,
    string $descripcion
): Factura {

    return DB::transaction(function () use (
        $suscripcion,
        $fechaFactura,
        $baseImponible,
        $estado,
        $descripcion
    ) {

        $cliente = $suscripcion->cliente;

        $porcentajeIva = Cliente::getPorcentajeImpuesto(
            $cliente->codigo_postal,
            $cliente->provincia
        );

        $iva   = round($baseImponible * ((float)$porcentajeIva / 100), 2);
        $total = round($baseImponible + $iva, 2);

        $datosFactura = self::generarSiguienteNumeroFactura();

        $fechaEmision = $fechaFactura->copy()->startOfDay();
        $diasVencimiento = 30;

        $fechaVencimiento = $estado === FacturaEstadoEnum::PAGADA
            ? $fechaEmision->copy()
            : $fechaEmision->copy()->addDays($diasVencimiento);

        $factura = Factura::create([
            'cliente_id'        => $cliente->id,
            'venta_id'          => $suscripcion->venta_origen_id,
            'serie'             => $datosFactura['serie'],
            'numero_factura'    => $datosFactura['numero_factura'],
            'estado'            => $estado,
            'metodo_pago'       => $estado === FacturaEstadoEnum::PAGADA ? 'tarjeta' : 'sepa',
            'fecha_emision'     => $fechaEmision,
            'fecha_vencimiento' => $fechaVencimiento,
            'base_imponible'    => $baseImponible,
            'total_iva'         => $iva,
            'total_factura'     => $total,
            'observaciones_publicas' => $descripcion,
        ]);

        $factura->items()->create([
            'cliente_suscripcion_id' => $suscripcion->id,
            'servicio_id'            => $suscripcion->servicio_id,
            'descripcion'            => $descripcion,
            'cantidad'               => 1,
            'precio_unitario'        => $baseImponible,
            'porcentaje_iva'         => $porcentajeIva,
            'cuota_iva'              => $iva,
            'subtotal'               => $baseImponible,
            'total'                  => $total,
        ]);

        return $factura;
    });
}
 */


}