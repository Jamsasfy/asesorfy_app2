<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ClienteSuscripcion;
use App\Models\Factura;
use App\Enums\FacturaEstadoEnum;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB; // Faltaba este import para las transacciones

class FacturacionRecurrenteService
{
    /**
     * Crea una factura recurrente manual (sin Stripe directo).
     * Usado para: Prorratas manuales, Cuotas SEPA pendientes, etc.
     */
public static function crearFacturaRecurrenteManual(
    ClienteSuscripcion $suscripcion,
    Carbon $fechaFactura,
    float $baseImponible,
    FacturaEstadoEnum $estado,
    string $descripcion,
    ?string $stripeInvoiceId = null,
    ?string $stripePaymentIntentId = null
): Factura {

    return DB::transaction(function () use (
        $suscripcion,
        $fechaFactura,
        $baseImponible,
        $estado,
        $descripcion,
        $stripeInvoiceId,
        $stripePaymentIntentId
    ) {
        $cliente = $suscripcion->cliente;

        // 1. Detectar Impuestos
        $porcentajeIva = Cliente::getPorcentajeImpuesto(
            $cliente->codigo_postal,
            $cliente->provincia
        );

        // 2. Calcular Totales
        $iva = round($baseImponible * ($porcentajeIva / 100), 2);
        $total = round($baseImponible + $iva, 2);

        // 3. Obtener Numeración
        $datosFactura = FacturacionService::generarSiguienteNumeroFactura();
        $fechaEmision = $fechaFactura->copy()->startOfDay();

        // 4. Crear Cabecera Factura
        $factura = Factura::create([
            'cliente_id'        => $cliente->id,
            'venta_id'          => $suscripcion->venta_origen_id,
            'serie'             => $datosFactura['serie'],
            'numero_factura'    => $datosFactura['numero_factura'],
            'estado'            => $estado,
            'metodo_pago'       => $estado === FacturaEstadoEnum::PAGADA ? 'stripe' : 'domiciliacion',
            'fecha_emision'     => $fechaEmision,
            'fecha_vencimiento' => $estado === FacturaEstadoEnum::PAGADA
                ? $fechaEmision->copy()
                : $fechaEmision->copy()->addDays(30),

            // ✅ Stripe trazabilidad (para enlazar a dashboard)
            'stripe_invoice_id'        => $stripeInvoiceId,
            'stripe_payment_intent_id' => $stripePaymentIntentId,

            'base_imponible'    => $baseImponible,
            'total_iva'         => $iva,
            'total_factura'     => $total,
            'observaciones_publicas' => $descripcion,
        ]);

        // 5. Crear Línea de Factura
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

        Log::info("🧾 Factura Manual Creada #{$factura->numero_factura} ({$estado->value})", [
            'suscripcion_id' => $suscripcion->id,
            'stripe_invoice_id' => $stripeInvoiceId,
            'stripe_payment_intent_id' => $stripePaymentIntentId,
        ]);

        return $factura;
    });
}

    // ... (Mantén aquí el resto de métodos antiguos si los usas, como generarFacturas, etc.)
}