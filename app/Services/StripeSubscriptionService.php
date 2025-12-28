<?php

namespace App\Services;

use Exception;
use Stripe\Customer;
use App\Models\Cliente;
use Stripe\Invoice;
use App\Models\ClienteSuscripcion;
use App\Models\Factura;
use App\Enums\FacturaEstadoEnum;
use App\Enums\ClienteSuscripcionEstadoEnum;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\Price;
use Stripe\InvoiceItem;
use Carbon\Carbon;

class StripeSubscriptionService
{
    /**
     * Activa la suscripción en Stripe.
     * Genera SIEMPRE la factura local de la prorrata (Devengo).
     * - Tarjeta: Cobra prorrata YA -> Factura PAGADA.
     * - SEPA: Agrupa cobro día 1 -> Factura PENDIENTE.
     */
public static function activarSuscripcion(ClienteSuscripcion $suscripcion): void
{
    $cliente  = $suscripcion->cliente;
    $servicio = $suscripcion->servicio;

    if (! $cliente?->stripe_customer_id) {
        throw new Exception("El cliente no tiene ID de Stripe.");
    }

    Stripe::setApiKey(config('services.stripe.secret'));
    if (app()->isLocal()) Stripe::setVerifySslCerts(false);

    $stripeCustomer = Customer::retrieve([
        'id'     => $cliente->stripe_customer_id,
        'expand' => ['invoice_settings.default_payment_method'],
    ]);

    $defaultPm = $stripeCustomer->invoice_settings->default_payment_method ?? null;
    $pmId      = $defaultPm->id ?? null;
    $tipoPago  = $defaultPm->type ?? 'card';

    Log::info("🔄 Activando suscripción ({$tipoPago}) para #{$suscripcion->id}");

    $ivaPercent = Cliente::getPorcentajeImpuesto($cliente->codigo_postal, $cliente->provincia);
    $factorIva  = 1 + ($ivaPercent / 100);

    $fechaInicio        = now();
    $inicioMesSiguiente = $fechaInicio->copy()->addMonth()->startOfMonth();

    $precioMensualBase  = (float) $suscripcion->precio_acordado;
    $precioMensualBruto = round($precioMensualBase * $factorIva, 2);

    $diasMes       = $fechaInicio->daysInMonth;
    $diasRestantes = ($diasMes - $fechaInicio->day) + 1;

    $prorrataBruta = 0.0;
    if ($fechaInicio->day > 1) {
        $prorrataBruta = round(($precioMensualBruto / $diasMes) * $diasRestantes, 2);
    }

    $nombreServicio = $suscripcion->nombre_personalizado ?? ($servicio->nombre ?? 'Suscripción');
    $descProrrata   = $nombreServicio . ' - Prorrata ' . ucfirst($fechaInicio->locale('es')->translatedFormat('F Y'));

    try {
        $priceId = self::ensureStripePrice($servicio, $precioMensualBruto, $ivaPercent);

        $esSepa        = ($tipoPago === 'sepa_debit');
        $sepaInmediato = $esSepa && ((int) $fechaInicio->day <= 15);

        $stripeSub = null;

        // =========================
        // TARJETA
        // =========================
        if ($tipoPago === 'card') {

            // 1) Prorrata (si aplica) -> Invoice puntual con línea ASIGNADA
            if ($prorrataBruta > 0) {

                $invoice = Invoice::create([
                    'customer'        => $cliente->stripe_customer_id,
                    'auto_advance'    => true,
                    'collection_method' => 'charge_automatically',
                    'metadata'        => ['suscripcion_local_id' => (string) $suscripcion->id],
                ]);

                InvoiceItem::create([
                    'customer'    => $cliente->stripe_customer_id,
                    'invoice'     => $invoice->id,
                    'amount'      => (int) round($prorrataBruta * 100),
                    'currency'    => 'eur',
                    'description' => $descProrrata . ' (Cobro inmediato)',
                    'metadata'    => ['suscripcion_local_id' => (string) $suscripcion->id],
                ]);

                try {
                    $invoice = Invoice::retrieve($invoice->id);
                    $invoice->finalizeInvoice();

                    if ($pmId) {
                        $invoice->pay(['payment_method' => $pmId]);
                    } else {
                        $invoice->pay();
                    }

                    Log::info("💳 Prorrata tarjeta: invoice {$invoice->id} finalizada/pagada");
                } catch (\Throwable $e) {
                    Log::warning("💳 Prorrata tarjeta: no se pudo finalizar/pagar invoice {$invoice->id}: " . $e->getMessage());
                }
            }

            // 2) Suscripción limpia (trial hasta día 1)
            $stripeSub = Subscription::create([
                'customer'               => $cliente->stripe_customer_id,
                'items'                  => [['price' => $priceId, 'quantity' => (int) $suscripcion->cantidad]],
                'trial_end'              => $inicioMesSiguiente->timestamp,
                'default_payment_method' => $pmId,
                'proration_behavior'     => 'none',
                'metadata'               => ['suscripcion_local_id' => (string) $suscripcion->id],
            ]);
        }

        // =========================
        // SEPA
        // =========================
        if ($esSepa) {

            // 1) SEPA 1–15: prorrata ahora (invoice puntual NO vinculada a subscription)
            if ($sepaInmediato && $prorrataBruta > 0) {

                $invoice = Invoice::create([
                    'customer'          => $cliente->stripe_customer_id,
                    'auto_advance'      => true,
                    'collection_method' => 'charge_automatically',
                    'metadata'          => ['suscripcion_local_id' => (string) $suscripcion->id],
                ]);

                InvoiceItem::create([
                    'customer'    => $cliente->stripe_customer_id,
                    'invoice'     => $invoice->id,
                    'amount'      => (int) round($prorrataBruta * 100),
                    'currency'    => 'eur',
                    'description' => $descProrrata . ' (SEPA - se inicia cobro ahora)',
                    'metadata'    => ['suscripcion_local_id' => (string) $suscripcion->id],
                ]);

                try {
                    $invoice = Invoice::retrieve($invoice->id);
                    $invoice->finalizeInvoice();

                    if ($pmId) {
                        $invoice->pay(['payment_method' => $pmId]);
                    } else {
                        $invoice->pay();
                    }

                    Log::info("🏦 SEPA (1-15): prorrata en invoice {$invoice->id} (cobro iniciado)");
                } catch (\Throwable $e) {
                    Log::warning("🏦 SEPA (1-15): no se pudo finalizar/iniciar pay invoice {$invoice->id}: " . $e->getMessage());
                }
            }

            // 2) Suscripción limpia siempre (trial hasta día 1)
            $stripeSub = Subscription::create([
                'customer'               => $cliente->stripe_customer_id,
                'items'                  => [['price' => $priceId, 'quantity' => (int) $suscripcion->cantidad]],
                'trial_end'              => $inicioMesSiguiente->timestamp,
                'default_payment_method' => $pmId,
                'proration_behavior'     => 'none',
                'metadata'               => ['suscripcion_local_id' => (string) $suscripcion->id],
            ]);

            Log::info("🏦 SEPA: suscripción creada (trial). ID: {$stripeSub->id}");

            // 3) SEPA 16–fin: prorrata diferida vinculada a subscription
            if (! $sepaInmediato && $prorrataBruta > 0) {
                InvoiceItem::create([
                    'customer'     => $cliente->stripe_customer_id,
                    'subscription' => $stripeSub->id,
                    'amount'       => (int) round($prorrataBruta * 100),
                    'currency'     => 'eur',
                    'description'  => $descProrrata . ' (SEPA - se cobrará día 1)',
                    'metadata'     => ['suscripcion_local_id' => (string) $suscripcion->id],
                ]);

                Log::info("🏦 SEPA (16-fin): prorrata vinculada a suscripción (cobro diferido).");
            }
        }

        if (! $stripeSub) {
            throw new Exception("No se pudo crear la suscripción en Stripe (stripeSub null).");
        }

        $suscripcion->update([
            'stripe_subscription_id' => $stripeSub->id,
            'stripe_status'          => $stripeSub->status,
            'estado'                 => ClienteSuscripcionEstadoEnum::ACTIVA,
            'fecha_inicio'           => $fechaInicio,
        ]);

        if ($prorrataBruta > 0) {
            $baseProrrata = round($prorrataBruta / $factorIva, 2);

            $estadoFactura = ($tipoPago === 'sepa_debit')
                ? FacturaEstadoEnum::PENDIENTE_PAGO
                : FacturaEstadoEnum::PAGADA;

            FacturacionRecurrenteService::crearFacturaRecurrenteManual(
                $suscripcion,
                now(),
                $baseProrrata,
                $estadoFactura,
                $descProrrata
            );

            Log::info("🧾 Factura Prorrata local creada. Estado: {$estadoFactura->value}");
        }

    } catch (Exception $e) {
        Log::error("❌ Error activando suscripción Stripe: " . $e->getMessage());
        throw $e;
    }
}


    private static function ensureStripePrice($servicio, $precioBruto, $ivaPercent)
    {
        $prices = Price::all(['product' => $servicio->stripe_product_id, 'limit' => 10]);
        foreach ($prices->data as $p) {
            if (isset($p->metadata['iva_percent']) && 
                (float)$p->metadata['iva_percent'] == (float)$ivaPercent &&
                $p->unit_amount == (int)round($precioBruto * 100)) {
                return $p->id;
            }
        }

        return Price::create([
            'product' => $servicio->stripe_product_id,
            'unit_amount' => (int)round($precioBruto * 100),
            'currency' => 'eur',
            'recurring' => ['interval' => 'month'],
            'metadata' => ['iva_percent' => (string)$ivaPercent]
        ])->id;
    }
}