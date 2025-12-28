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
        if (app()->isLocal()) Stripe::setVerifySslCerts(false);

        $invoiceId = $invoicePayload['id'];

        try {
            $invoice = Invoice::retrieve([
                'id' => $invoiceId,
                'expand' => ['lines.data.price', 'subscription'],
            ]);
        } catch (Throwable $e) {
            Log::error("❌ Error recuperando invoice Stripe: " . $e->getMessage());
            return response('Error', 500);
        }

        $customerId = $invoice->customer;
        $cliente = Cliente::where('stripe_customer_id', $customerId)->first();
        if (!$cliente) {
            return response('No client', 200);
        }

        // Evitar duplicados
        $numeroStripe = $invoice->number ?? $invoice->id;
        if (
            Factura::where('observaciones_publicas', 'LIKE', "%{$numeroStripe}%")->exists()
        ) {
            Log::info("⏭️ Factura {$numeroStripe} ya existe");
            return response('Duplicate', 200);
        }

        // Localizar suscripción local
        $suscripcionLocal = null;

        if (!empty($invoice->lines->data)) {
            foreach ($invoice->lines->data as $line) {
                if (isset($line->price->metadata['suscripcion_id'])) {
                    $suscripcionLocal = ClienteSuscripcion::find(
                        $line->price->metadata['suscripcion_id']
                    );
                    break;
                }
            }
        }

        if (!$suscripcionLocal && $invoice->subscription) {
            $stripeSubId = is_string($invoice->subscription)
                ? $invoice->subscription
                : $invoice->subscription->id;

            $suscripcionLocal = ClienteSuscripcion::where(
                'stripe_subscription_id',
                $stripeSubId
            )->first();
        }

        if (!$suscripcionLocal) {
            Log::warning("⚠️ No se pudo vincular invoice {$invoiceId}");
            return response('No sub', 200);
        }

        try {
            $fechaPago = Carbon::createFromTimestamp(
                $invoice->status_transitions->paid_at ?? now()->timestamp
            );

            $factura = FacturacionService::crearFacturaRecurrente(
                cliente: $cliente,
                suscripcion: $suscripcionLocal,
                amountCents: $invoice->amount_paid,
                fechaPago: $fechaPago,
                numeroStripe: $numeroStripe
            );

            Log::info("✅ Factura local creada {$factura->numero_factura}");
            return response('Created', 200);

        } catch (Throwable $e) {
            Log::error("❌ Error creando factura local: " . $e->getMessage());
            return response('DB error', 500);
        }
    }
}
