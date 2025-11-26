<?php

namespace App\Services;

use App\Models\ContadorFactura;
use App\Models\Factura;
use App\Models\ClienteSuscripcion; // <-- Asegúrate de que esta línea esté
use App\Enums\FacturaEstadoEnum; // <-- Asegúrate de que esta línea esté
use App\Enums\ClienteSuscripcionEstadoEnum; // <-- Asegúrate de que esta línea esté
use App\Enums\ServicioTipoEnum; // <-- Asegúrate de que esta línea esté
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FacturacionService
{
    /**
     * Genera el siguiente número de factura usando la tabla de contadores.
     * Este método es usado tanto por facturas únicas como recurrentes YH RECTIFICATVAS.
     */
    public static function generarSiguienteNumeroFactura(string $tipo = 'normal'): array
{
    return DB::transaction(function () use ($tipo) {
        // 1. Decidimos qué formato y prefijo usar
        $esRectificativa = ($tipo === 'rectificativa');
        $formatoKey = $esRectificativa ? 'formato_factura_rectificativa' : 'formato_factura';
        $defaultFormato = $esRectificativa ? 'REC{YY}-00000' : 'FR{YY}-00000';
        
        $formato = ConfiguracionService::get($formatoKey, $defaultFormato);
        $prefijoSerie = substr($formato, 0, strpos($formato, '{'));

        // 2. Obtenemos el año actual
        $anoActual = Carbon::now()->year;

        // 3. Buscamos o creamos el contador para esta serie y año
        $contador = ContadorFactura::lockForUpdate()->firstOrCreate(
            ['serie' => $prefijoSerie, 'anio' => $anoActual],
            ['ultimo_numero' => 0]
        );

        // 4. Incrementamos y guardamos
        $nuevoNumero = $contador->ultimo_numero + 1;
        $contador->update(['ultimo_numero' => $nuevoNumero]);

        // 5. Construimos el número de factura final
        $anoDosDigitos = Carbon::now()->format('y');
        $serieCompleta = "{$prefijoSerie}{$anoDosDigitos}-";
        $padding = strlen(substr($formato, strrpos($formato, '-') + 1));
        $numeroConPadding = str_pad($nuevoNumero, $padding, '0', STR_PAD_LEFT);

        return [
            'serie' => $serieCompleta,
            'numero_factura' => $serieCompleta . $numeroConPadding,
        ];
    });
}

  //no utilizado actualmente
  /*  public static function generarFacturaParaSuscripcionUnica(ClienteSuscripcion $suscripcion): ?Factura
{
    if (
        $suscripcion->servicio->tipo->value !== ServicioTipoEnum::UNICO->value
        || $suscripcion->estado !== ClienteSuscripcionEstadoEnum::ACTIVA
    ) {
        Log::warning("Intento de facturar suscripción no única o no activa (ID: {$suscripcion->id})");
        return null;
    }

    return DB::transaction(function () use ($suscripcion) {
        try {
            $datosFactura = self::generarSiguienteNumeroFactura();
            $fechaEmision = $suscripcion->fecha_inicio ?? now()->startOfDay();
            $fechaVencimiento = $fechaEmision->copy()->addDays(15);

            $cantidad = $suscripcion->cantidad;
            $precioOriginal = ($cantidad > 0)
                ? ($suscripcion->precio_acordado / $cantidad)
                : 0;

            $descripcion = $suscripcion->nombre_final;
            if ($suscripcion->fecha_inicio) {
                $descripcion .= ' - ' . $suscripcion->fecha_inicio->format('d/m/Y');
            }

            if ($suscripcion->descuento_descripcion) {
                $descripcion .= " ({$suscripcion->descuento_descripcion})";
            }

            $descuentoTipo = null;
            $descuentoValor = null;
            $descuentoImporte = 0;
            $precioFinal = $precioOriginal;
            $descuentoVigente = $suscripcion->descuento_tipo && $suscripcion->descuento_valido_hasta && $fechaEmision->lte($suscripcion->descuento_valido_hasta);

            if ($descuentoVigente) {
                $descuentoTipo = $suscripcion->descuento_tipo;
                $descuentoValor = $suscripcion->descuento_valor;

                switch ($descuentoTipo) {
                    case 'porcentaje':
                        $precioFinal = $precioOriginal * (1 - ($descuentoValor / 100));
                        $descuentoImporte = ($precioOriginal - $precioFinal) * $cantidad;
                        break;
                    case 'fijo':
                        $descuentoImporte = $descuentoValor;
                        $precioFinal = max(0, $precioOriginal * $cantidad - $descuentoImporte) / $cantidad;
                        break;
                    case 'precio_final':
                        $precioFinal = ($cantidad > 0) ? $descuentoValor / $cantidad : 0;
                        $descuentoImporte = ($precioOriginal - $precioFinal) * $cantidad;
                        break;
                }
            }

            $subtotal = round($precioFinal * $cantidad, 2);
            $iva = ConfiguracionService::get('IVA_general', 21.00);
            $ivaTotal = $subtotal * ($iva / 100);

            $factura = Factura::create([
                'cliente_id'        => $suscripcion->cliente_id,
                'venta_id'          => $suscripcion->venta_origen_id,
                'serie'             => $datosFactura['serie'],
                'numero_factura'    => $datosFactura['numero_factura'],
                'estado'            => FacturaEstadoEnum::PAGADA,
                'metodo_pago'       => null,
                'fecha_emision'     => $fechaEmision,
                'fecha_vencimiento' => $fechaVencimiento,
                'base_imponible'    => $subtotal,
                'total_iva'         => round($ivaTotal, 2),
                'total_factura'     => round($subtotal + $ivaTotal, 2),
            ]);

            $factura->items()->create([
                'cliente_suscripcion_id' => $suscripcion->id,
                'servicio_id'            => $suscripcion->servicio_id,
                'descripcion'            => $descripcion,
                'cantidad'               => $cantidad,
                'precio_unitario'        => round($precioOriginal, 2),
                'precio_unitario_aplicado' => round($precioFinal, 2),
                'importe_descuento'      => round($descuentoImporte, 2),
                'porcentaje_iva'         => $iva,
                'subtotal'               => $subtotal,
                'descuento_tipo'         => $descuentoTipo,
                'descuento_valor'        => $descuentoValor,
            ]);

            $suscripcion->update(['estado' => ClienteSuscripcionEstadoEnum::FINALIZADA]);

            return $factura;

        } catch (\Throwable $e) {
            Log::error("Error al facturar suscripción única ID {$suscripcion->id}: " . $e->getMessage());
            return null;
        }
    });
} */


 

/**
 * Genera una factura consolidada para todos los servicios de tipo UNICO en una venta.
 *
 * Se usa tanto en la creación inicial como en procesos de corrección.
 * Agrupa todos los items únicos en una única factura, aplicando descuentos, IVA,
 * y vinculando correctamente las suscripciones y la venta.
 */



public static function generarFacturaParaVenta(Venta $venta): ?Factura
{
    $ventaItems = $venta->items()->whereHas('servicio', function ($q) {
        $q->where('tipo', ServicioTipoEnum::UNICO->value);
    })->get();

    if ($ventaItems->isEmpty()) {
        Log::info("La venta {$venta->id} no tiene servicios únicos para facturar.");
        return null;
    }

    return DB::transaction(function () use ($venta, $ventaItems) {
        $datosFactura = self::generarSiguienteNumeroFactura();
        $fechaEmision = now()->startOfDay();
        $fechaVencimiento = $fechaEmision->copy()->addDays(15);
        $ivaGeneral = ConfiguracionService::get('IVA_general', 21.00);

        $baseImponibleTotal = 0;
        $totalIva = 0;

        $factura = Factura::create([
            'cliente_id'        => $venta->cliente_id,
            'venta_id'          => $venta->id,
            'serie'             => $datosFactura['serie'],
            'numero_factura'    => $datosFactura['numero_factura'],
            'estado'            => FacturaEstadoEnum::PENDIENTE_PAGO,
            'metodo_pago'       => null,
            'fecha_emision'     => $fechaEmision,
            'fecha_vencimiento' => $fechaVencimiento,
            'base_imponible'    => 0,
            'total_iva'         => 0,
            'total_factura'     => 0,
        ]);

        foreach ($ventaItems as $item) {
    $cantidad = $item->cantidad;
    $precioOriginal = $item->precio_unitario;
    $descuentoTipo = $item->descuento_tipo;
    $descuentoValor = $item->descuento_valor;
    $descuentoDescripcion = null;

    $precioAplicado = $precioOriginal;
    $importeDescuento = 0;

    if ($descuentoTipo && $descuentoValor) {
        if ($descuentoTipo === 'porcentaje') {
            $importeDescuento = round($precioOriginal * ($descuentoValor / 100), 2);
            $precioAplicado = $precioOriginal - $importeDescuento;
            $descuentoDescripcion = "{$descuentoValor} %";
        } elseif ($descuentoTipo === 'fijo') {
            $importeDescuento = round($descuentoValor, 2);
            $precioAplicado = max(0, $precioOriginal - $importeDescuento);
            $descuentoDescripcion = "-" . number_format($importeDescuento, 2) . " €";
        } elseif ($descuentoTipo === 'precio_final') {
            $precioAplicado = round($descuentoValor, 2);
            $importeDescuento = $precioOriginal - $precioAplicado;
            $descuentoDescripcion = "precio final";
        }
    }

    $subtotal = round($precioAplicado * $cantidad, 2);
    $ivaLinea = round($subtotal * ($ivaGeneral / 100), 2);

    $baseImponibleTotal += $subtotal;
    $totalIva += $ivaLinea;

    // ✅ Descripción: usa la del item, o el nombre del servicio, o fallback
    $descripcion = $item->descripcion ?: ($item->servicio->nombre ?? 'Servicio');

    // if ($descuentoDescripcion) {
    //     $descripcion .= " ({$descuentoDescripcion})";
    // }

    $factura->items()->create([
        'venta_item_id'            => $item->id,
        'servicio_id'              => $item->servicio_id,
        'descripcion'              => $descripcion,
        'cantidad'                 => $cantidad,
        'precio_unitario'          => round($precioOriginal, 2),
        'precio_unitario_aplicado' => round($precioAplicado, 2),
        'importe_descuento'        => round($importeDescuento * $cantidad, 2),
        'porcentaje_iva'           => $ivaGeneral,
        'subtotal'                 => $subtotal,
        'cliente_suscripcion_id' => $item->cliente_suscripcion_id,
        'descuento_tipo'           => $descuentoTipo,
        'descuento_valor'          => $descuentoValor,
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



}