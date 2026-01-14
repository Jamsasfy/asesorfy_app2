<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\VentaEstadoEnum;
use Throwable;
use Stripe\Invoice;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Cliente;
use App\Models\Venta;
use App\Models\ClienteSuscripcion;
use App\Models\Factura;
use App\Services\FacturacionService;
use Carbon\Carbon;
use Stripe\Stripe;

class StripeWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $payload = $request->all();
        $type = $payload['type'] ?? 'unknown';

        Log::info("🔔 Stripe Webhook recibido: {$type}");

        switch ($type) {

            case 'customer.updated':
                return $this->handleCustomerUpdated($payload['data']['object']);

            case 'invoice.payment_succeeded':
                return $this->handleInvoicePaymentSucceeded($payload['data']['object']);

            default:
                return response('Ignored', 200);
        }
    }

    /**
     * =========================================================
     * CUSTOMER UPDATED
     * 👉 ÚNICO PUNTO VÁLIDO PARA ACTIVAR VENTAS PENDIENTES
     * =========================================================
     */
protected function handleCustomerUpdated(array $customer)
{
    Stripe::setApiKey(config('services.stripe.secret'));
    if (app()->isLocal()) Stripe::setVerifySslCerts(false);

    $customerId = $customer['id'] ?? null;
    $defaultPm  = $customer['invoice_settings']['default_payment_method'] ?? null;

    if (!$customerId || !$defaultPm) {
        return response('No default PM yet', 200);
    }

    $cliente = Cliente::where('stripe_customer_id', $customerId)->first();
    if (!$cliente) {
        Log::warning("⚠️ Cliente no encontrado para customer {$customerId}");
        return response('No client', 200);
    }

    // Guardamos el PM definitivo
    $cliente->update([
        'stripe_payment_method_id' => $defaultPm,
    ]);

    Log::info("✅ Default payment method confirmado para cliente {$cliente->id}", [
        'pm' => $defaultPm,
    ]);

    // ✅ Solo activar ventas pendientes que sean "SOLO RECURRENTE" (sin cobro inicial)
    $ventasPendientes = Venta::with('items.servicio')
        ->where('cliente_id', $cliente->id)
        ->where('estado', VentaEstadoEnum::PENDIENTE)
        ->get();

    foreach ($ventasPendientes as $venta) {
        try {
            // Si el pago inicial es transferencia, JAMÁS auto-completar
            if (($venta->pago_inicial_metodo ?? null) === 'transferencia') {
                Log::info("⏭️ Venta {$venta->id}: pago inicial transferencia. No auto-activar por customer.updated.");
                continue;
            }

            $totalCobroInicial = 0;
            $tieneRecurrente = false;

            foreach ($venta->items as $item) {
                if (! $item->servicio) continue;

                $tipo = $item->servicio->tipo instanceof \BackedEnum
                    ? $item->servicio->tipo->value
                    : $item->servicio->tipo;

                if ($tipo === 'unico') {
                    $totalCobroInicial += (float) $item->subtotal_aplicado;
                }
                if ($tipo === 'recurrente') {
                    $tieneRecurrente = true;
                }
            }

            // Solo recurrente: total inicial = 0
            if (! $tieneRecurrente || $totalCobroInicial > 0) {
                Log::info("⏭️ Venta {$venta->id}: no es solo recurrente. No auto-activar.", [
                    'tiene_recurrente' => $tieneRecurrente,
                    'total_inicial' => $totalCobroInicial,
                ]);
                continue;
            }

            Log::info("🚀 Activando venta SOLO recurrente tras customer.updated", [
                'venta_id' => $venta->id,
            ]);

            $venta->procesarCobroInicial(
                fechaPago: now(),
                metodoPago: 'suscripcion_directa',
                paymentIntentId: null,
                extraData: []
            );

        } catch (Throwable $e) {
            Log::error("❌ Error activando venta {$venta->id}: " . $e->getMessage());
        }
    }

    return response('Customer processed', 200);
}

    /**
     * =========================================================
     * INVOICE PAYMENT SUCCEEDED
     * 👉 Stripe ya ha cobrado → crear factura local
     * =========================================================
     */
protected function handleInvoicePaymentSucceeded(array $invoicePayload)
{
    Stripe::setApiKey(config('services.stripe.secret'));
    if (app()->isLocal()) {
        Stripe::setVerifySslCerts(false);
    }

    // =========================================================
    // 0) Resolver invoiceId aunque el evento sea invoice_payment.paid
    // - invoice.payment_* => payload.id = in_...
    // - invoice_payment.* => payload.invoice = in_...
    // =========================================================
    $invoiceId = $invoicePayload['id'] ?? null;

    if (! empty($invoicePayload['invoice'])) {
        $invoiceId = $invoicePayload['invoice']; // in_...
    }

    if (! $invoiceId) {
        Log::warning("⚠️ webhook sin invoice id (ni id ni invoice) en payload");
        return response('No invoice id', 200);
    }

    // PI: puede venir del invoice_payment.paid aunque invoice.payment_intent venga null
    $piFromInvoicePayment = $invoicePayload['payment']['payment_intent'] ?? null;

    try {
        $invoice = Invoice::retrieve([
            'id' => $invoiceId,
            'expand' => ['lines.data.price', 'subscription'],
        ]);
    } catch (Throwable $e) {
        Log::error("❌ Error recuperando invoice Stripe {$invoiceId}: " . $e->getMessage());
        return response('Error', 500);
    }

    // ✅ Ignorar invoices 0€
    $amountPaid = (int) ($invoice->amount_paid ?? 0);
    if ($amountPaid <= 0) {
        Log::info("⏭️ Invoice {$invoiceId} pagada a 0€ (trial/discount). No se crea/actualiza factura local.");
        return response('Ignored zero invoice', 200);
    }

    $customerId = $invoice->customer ?? null;
    if (! $customerId) {
        Log::warning("⚠️ Invoice {$invoiceId} sin customer");
        return response('No customer', 200);
    }

    $cliente = Cliente::where('stripe_customer_id', $customerId)->first();
    if (! $cliente) {
        Log::warning("⚠️ No existe cliente local para stripe_customer_id {$customerId}");
        return response('No client', 200);
    }

    $stripeInvoiceId       = (string) $invoice->id;
    $stripeInvoiceNumber   = $invoice->number ?? null;
    $stripePaymentIntentId = $invoice->payment_intent ?? $piFromInvoicePayment;

    // =========================================================
    // Helper inline para leer metadata de StripeObject/array
    // =========================================================
    $mdGet = static function ($md, string $key) {
        if (! $md) return null;

        // StripeObject implementa ArrayAccess (esto sí funciona)
        try {
            if (is_array($md)) {
                return $md[$key] ?? null;
            }
            return $md[$key] ?? null;
        } catch (Throwable $e) {
            return null;
        }
    };

    // =========================================================
    // 1) Intentar ACTUALIZAR factura local pendiente por metadata (SEPA)
    // =========================================================
    $localFacturaId = null;

    // 1.1 invoice.metadata
    $localFacturaId = $mdGet($invoice->metadata ?? null, 'asesorfy_factura_local_id');
    if ($localFacturaId) {
        $localFacturaId = (int) $localFacturaId;
    }

    // 1.2 fallback: lines.metadata
    if (! $localFacturaId && ! empty($invoice->lines?->data)) {
        foreach ($invoice->lines->data as $line) {
            $id = $mdGet($line->metadata ?? null, 'asesorfy_factura_local_id');
            if ($id) {
                $localFacturaId = (int) $id;
                break;
            }
        }
    }

    // 1.3 fallback extra: número de factura
    $localFacturaNumero = null;
    if (! $localFacturaNumero && ! empty($invoice->lines?->data)) {
        foreach ($invoice->lines->data as $line) {
            $num = $mdGet($line->metadata ?? null, 'asesorfy_factura_local_numero');
            if ($num) {
                $localFacturaNumero = (string) $num;
                break;
            }
        }
    }

    if ($localFacturaId || $localFacturaNumero) {
        $facturaLocal = $localFacturaId
            ? Factura::find($localFacturaId)
            : Factura::where('numero_factura', $localFacturaNumero)->first();

        if (! $facturaLocal) {
            Log::warning("⚠️ Stripe trae referencia de factura local pero no existe en BBDD", [
                'asesorfy_factura_local_id' => $localFacturaId,
                'asesorfy_factura_local_numero' => $localFacturaNumero,
                'stripe_invoice_id' => $stripeInvoiceId,
            ]);
            // seguimos al flujo normal
        } else {
            try {
                $fechaPago = Carbon::createFromTimestamp(
                    $invoice->status_transitions->paid_at ?? now()->timestamp
                );

                if (
                    (string) $facturaLocal->stripe_invoice_id === (string) $stripeInvoiceId &&
                    $facturaLocal->estado === \App\Enums\FacturaEstadoEnum::PAGADA
                ) {
                    Log::info("⏭️ Factura local {$facturaLocal->id} ya estaba marcada como pagada para invoice {$stripeInvoiceId}");
                    return response('Already updated', 200);
                }

                $facturaLocal->update([
                    'estado' => \App\Enums\FacturaEstadoEnum::PAGADA,
                    'metodo_pago' => $facturaLocal->metodo_pago ?: 'stripe',
                    'stripe_invoice_id' => $stripeInvoiceId,
                    'stripe_payment_intent_id' => $stripePaymentIntentId,
                    'fecha_vencimiento' => $fechaPago,
                    'observaciones_publicas' => $facturaLocal->observaciones_publicas ?: (
                        $stripeInvoiceNumber ? "Pago confirmado Stripe: {$stripeInvoiceNumber}" : "Pago confirmado Stripe: {$stripeInvoiceId}"
                    ),
                ]);

                Log::info("✅ Factura local pendiente actualizada a PAGADA", [
                    'factura_id' => $facturaLocal->id,
                    'stripe_invoice_id' => $stripeInvoiceId,
                    'stripe_payment_intent_id' => $stripePaymentIntentId,
                ]);

                return response('Updated existing', 200);

            } catch (Throwable $e) {
                Log::error("❌ Error actualizando factura local {$facturaLocal->id} con invoice {$stripeInvoiceId}: " . $e->getMessage());
                return response('DB error', 500);
            }
        }
    }

    // =========================================================
    // 2) Idempotencia por stripe_invoice_id
    // =========================================================
    if (Factura::where('stripe_invoice_id', $stripeInvoiceId)->exists()) {
        Log::info("⏭️ Ya existe una factura local con stripe_invoice_id {$stripeInvoiceId}");
        return response('Duplicate', 200);
    }

    // =========================================================
    // 3) Localizar suscripción local
    // =========================================================
    $suscripcionLocal = null;

    $metaSubLocalId = $mdGet($invoice->metadata ?? null, 'suscripcion_local_id');
    if ($metaSubLocalId) {
        $suscripcionLocal = ClienteSuscripcion::find((int) $metaSubLocalId);
    }

    if (! $suscripcionLocal && ! empty($invoice->lines?->data)) {
        foreach ($invoice->lines->data as $line) {
            $sid = $mdGet($line->metadata ?? null, 'suscripcion_local_id');
            if ($sid) {
                $suscripcionLocal = ClienteSuscripcion::find((int) $sid);
                if ($suscripcionLocal) break;
            }
        }
    }

    if (! $suscripcionLocal && $invoice->subscription) {
        $stripeSubId = is_string($invoice->subscription)
            ? $invoice->subscription
            : $invoice->subscription->id;

        $suscripcionLocal = ClienteSuscripcion::where('stripe_subscription_id', $stripeSubId)->first();
    }

    if (! $suscripcionLocal) {
        Log::warning("⚠️ No se pudo vincular invoice {$stripeInvoiceId} a suscripción local");
        return response('No sub', 200);
    }

    // =========================================================
    // 4) Crear factura recurrente (casos sin factura previa)
    // =========================================================
    try {
        $fechaPago = Carbon::createFromTimestamp(
            $invoice->status_transitions->paid_at ?? now()->timestamp
        );

        $factura = FacturacionService::crearFacturaRecurrente(
            cliente: $cliente,
            suscripcion: $suscripcionLocal,
            amountCents: $amountPaid,
            fechaPago: $fechaPago,
            stripeInvoiceId: $stripeInvoiceId,
            stripeInvoiceNumber: $stripeInvoiceNumber,
            stripePaymentIntentId: $stripePaymentIntentId
        );

        Log::info("✅ Factura local creada {$factura->numero_factura} (stripe_invoice_id={$stripeInvoiceId})");
        return response('Created', 200);

    } catch (Throwable $e) {
        Log::error("❌ Error creando factura local para invoice {$stripeInvoiceId}: " . $e->getMessage());
        return response('DB error', 500);
    }
}



}
