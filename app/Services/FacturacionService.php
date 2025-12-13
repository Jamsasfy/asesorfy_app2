<?php

namespace App\Services;

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
        ?string $metodoPago = 'manual'
    ): ?Factura {
        
        $ventaItems = $venta->items()->whereHas('servicio', function ($q) {
            $q->where('tipo', ServicioTipoEnum::UNICO->value);
        })->get();

        if ($ventaItems->isEmpty()) {
            return null;
        }

        $facturaExistente = $venta->facturas()
            ->where('estado', FacturaEstadoEnum::PAGADA)
            ->exists();

        if ($facturaExistente) {
            return $venta->facturas()->where('estado', FacturaEstadoEnum::PAGADA)->first();
        }

        return self::crearFacturaConItems($venta, $ventaItems, FacturaEstadoEnum::PAGADA, $fechaPago, $metodoPago);
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

        // Generamos con estado PENDIENTE y fecha de hoy
        return self::crearFacturaConItems($venta, $ventaItems, FacturaEstadoEnum::PENDIENTE_PAGO, now(), null);
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
            
            $datosFactura = self::generarSiguienteNumeroFactura();
            $fechaEmision = $fechaEmision->copy()->startOfDay();
            $fechaVencimiento = $fechaEmision->copy()->addDays(15);
            
            // 🔥 CEREBRO FISCAL: Detectamos impuestos aquí
            $cliente = $venta->cliente;
            $porcentajeIva = \App\Models\Cliente::getPorcentajeImpuesto(
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
                $cantidad        = $item->cantidad;
                $precioOriginal  = $item->precio_unitario;
                
                // Lógica de descuentos
                $precioAplicado   = $item->precio_unitario_aplicado ?? $item->precio_unitario;
                $importeDescuento = 0;

                // Si no venía calculado, lo calculamos (seguridad)
                if ($item->descuento_tipo && $item->descuento_valor && is_null($item->precio_unitario_aplicado)) {
                     if ($item->descuento_tipo === 'porcentaje') {
                        $importeDescuento = round($precioOriginal * ($item->descuento_valor / 100), 2);
                        $precioAplicado   = $precioOriginal - $importeDescuento;
                    } elseif ($item->descuento_tipo === 'fijo') {
                        $importeDescuento = round($item->descuento_valor, 2);
                        $precioAplicado   = max(0, $precioOriginal - $importeDescuento);
                    } elseif ($item->descuento_tipo === 'precio_final') {
                        $precioAplicado   = round($item->descuento_valor, 2);
                        $importeDescuento = $precioOriginal - $precioAplicado;
                    }
                } elseif ($item->precio_unitario_aplicado !== null) {
                    $importeDescuento = $precioOriginal - $precioAplicado;
                }

                $subtotalLinea = round($precioAplicado * $cantidad, 2);
                
                // 🔥 APLICAR IMPUESTO DETECTADO (0% o 21%)
                $ivaLinea = round($subtotalLinea * ($porcentajeIva / 100), 2);

                $baseImponibleTotal += $subtotalLinea;
                $totalIva           += $ivaLinea;

                $factura->items()->create([
                    'venta_item_id'            => $item->id,
                    'servicio_id'              => $item->servicio_id,
                    'descripcion'              => $item->nombre_personalizado ?: ($item->servicio->nombre ?? 'Servicio'),
                    'cantidad'                 => $cantidad,
                    'precio_unitario'          => round($precioOriginal, 2),
                    'precio_unitario_aplicado' => round($precioAplicado, 2),
                    'importe_descuento'        => round($importeDescuento * $cantidad, 2),
                    
                    // Guardamos el 0.00 o 21.00
                    'porcentaje_iva'           => $porcentajeIva, 
                    'cuota_iva'                => $ivaLinea,
                    'subtotal'                 => $subtotalLinea,
                    'total'                    => $subtotalLinea + $ivaLinea,
                    
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
        \App\Models\Cliente $cliente,
        \App\Models\ClienteSuscripcion $suscripcion,
        int $amountCents,
        \Carbon\Carbon $fechaPago,
        string $stripeInvoiceNumber
    ): \App\Models\Factura {
        
        return DB::transaction(function () use ($cliente, $suscripcion, $amountCents, $fechaPago, $stripeInvoiceNumber) {
            
            // 1. Datos de serie y número
            $datosFactura = self::generarSiguienteNumeroFactura();
            
            // 2. Cerebro Fiscal: Detectar impuestos
            $porcentajeIva = \App\Models\Cliente::getPorcentajeImpuesto(
                $cliente->codigo_postal, 
                $cliente->provincia
            );
            $factor = 1 + ($porcentajeIva / 100);
            
            // 3. Desglosar totales (Stripe manda bruto en céntimos)
            $totalPagado    = $amountCents / 100; // a Euros
            $baseImponible  = round($totalPagado / $factor, 2);
            $iva            = round($totalPagado - $baseImponible, 2);

            // 4. Crear Cabecera
            $factura = Factura::create([
                'cliente_id'        => $cliente->id,
                // En renovaciones puras no hay "venta_id" nueva, usamos la original de la suscripción
                'venta_id'          => $suscripcion->venta_origen_id, 
                'serie'             => $datosFactura['serie'],
                'numero_factura'    => $datosFactura['numero_factura'],
                'estado'            => FacturaEstadoEnum::PAGADA,
                'metodo_pago'       => 'stripe',
                'fecha_emision'     => $fechaPago,
                'fecha_vencimiento' => $fechaPago,
                'base_imponible'    => $baseImponible,
                'total_iva'         => $iva,
                'total_factura'     => $totalPagado,
                'observaciones_publicas'     => "Renovación automática Stripe: " . $stripeInvoiceNumber,
            ]);

            // 5. Crear Línea
            $factura->items()->create([
                'descripcion'            => $suscripcion->nombre_personalizado ?? ($suscripcion->servicio->nombre ?? 'Suscripción'),
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
public static function crearFacturaRecurrenteManual(
    \App\Models\ClienteSuscripcion $suscripcion,
    \Carbon\Carbon $fechaFactura,
    float $baseImponible,
    \App\Enums\FacturaEstadoEnum $estado,
    string $descripcion
): \App\Models\Factura {

    return DB::transaction(function () use (
        $suscripcion,
        $fechaFactura,
        $baseImponible,
        $estado,
        $descripcion
    ) {

        $cliente = $suscripcion->cliente;

        // =========================
        // 🔥 DETECCIÓN IVA AUTOMÁTICA
        // =========================
        $porcentajeIva = \App\Models\Cliente::getPorcentajeImpuesto(
            $cliente->codigo_postal,
            $cliente->provincia
        );

        $iva  = round($baseImponible * ($porcentajeIva / 100), 2);
        $total = round($baseImponible + $iva, 2);

        // =========================
        // 🔢 NÚMERO DE FACTURA
        // =========================
        $datosFactura = self::generarSiguienteNumeroFactura();

        $fechaEmision = $fechaFactura->copy()->startOfDay();

        // =========================
        // 🧾 CREAR FACTURA
        // =========================
        $factura = \App\Models\Factura::create([
            'cliente_id'     => $cliente->id,
            'venta_id'       => $suscripcion->venta_origen_id,
            'serie'          => $datosFactura['serie'],
            'numero_factura' => $datosFactura['numero_factura'],
            'estado'         => $estado,
            'metodo_pago'    => $estado === \App\Enums\FacturaEstadoEnum::PAGADA ? 'tarjeta' : 'sepa',
            'fecha_emision'  => $fechaEmision,
            'fecha_vencimiento' => $fechaEmision->copy()->addDays(15),
            'base_imponible' => $baseImponible,
            'total_iva'      => $iva,
            'total_factura'  => $total,
            'observaciones_publicas' => $descripcion,
        ]);

        // =========================
        // 🧾 LÍNEA DE FACTURA
        // =========================
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

        \Log::info('[FACTURA RECURRENTE MANUAL]', [
            'factura_id' => $factura->id,
            'suscripcion_id' => $suscripcion->id,
            'base' => $baseImponible,
            'iva' => $iva,
            'total' => $total,
            'estado' => $estado->value,
        ]);

        return $factura;
    });
}



}