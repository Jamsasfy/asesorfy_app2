<?php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\StripeClient;

class StripePaymentMethodResolver
{
    public static function resolve(Cliente $cliente): array
    {
        if (! $cliente->stripe_customer_id) {
            return self::empty();
        }

        try {
            $stripe = self::makeClient();

            /**
             * 1️⃣ SUSCRIPCIÓN ACTIVA (prioridad máxima)
             */
            $subscriptions = $stripe->subscriptions->all([
                'customer' => $cliente->stripe_customer_id,
                'limit'    => 5,
                'status'   => 'all',
            ]);

            foreach ($subscriptions->data as $subscription) {
                // default_payment_method directo
                if (! empty($subscription->default_payment_method)) {
                    return self::fromPaymentMethod(
                        $stripe->paymentMethods->retrieve($subscription->default_payment_method),
                        'subscription'
                    );
                }

                /**
                 * latest_invoice puede venir:
                 * - como string ID ("in_...")
                 * - como objeto invoice expandido
                 *
                 * y payment_intent puede venir:
                 * - como string ID ("pi_...")
                 * - como objeto paymentIntent expandido
                 */
                $latestInvoice = $subscription->latest_invoice ?? null;

                $paymentIntentId = null;

                if (is_object($latestInvoice)) {
                    $pi = $latestInvoice->payment_intent ?? null;
                    if (is_string($pi)) {
                        $paymentIntentId = $pi;
                    } elseif (is_object($pi) && ! empty($pi->id)) {
                        $paymentIntentId = $pi->id;
                    }
                } elseif (is_string($latestInvoice) && $latestInvoice !== '') {
                    // Si latest_invoice es string, lo recuperamos para sacar el payment_intent
                    try {
                        $inv = $stripe->invoices->retrieve($latestInvoice, []);
                        $pi = $inv->payment_intent ?? null;

                        if (is_string($pi)) {
                            $paymentIntentId = $pi;
                        } elseif (is_object($pi) && ! empty($pi->id)) {
                            $paymentIntentId = $pi->id;
                        }
                    } catch (\Throwable) {
                        // si falla, seguimos con siguientes estrategias
                    }
                }

                if ($paymentIntentId) {
                    $piObj = $stripe->paymentIntents->retrieve($paymentIntentId, []);

                    $pmId = $piObj->payment_method ?? null;
                    if (is_string($pmId) && $pmId !== '') {
                        return self::fromPaymentMethod(
                            $stripe->paymentMethods->retrieve($pmId),
                            'subscription_invoice'
                        );
                    } elseif (is_object($pmId) && ! empty($pmId->id)) {
                        return self::fromPaymentMethod(
                            $stripe->paymentMethods->retrieve($pmId->id),
                            'subscription_invoice'
                        );
                    }
                }
            }

            /**
             * 2️⃣ CUSTOMER → default_payment_method
             */
            $customer = $stripe->customers->retrieve($cliente->stripe_customer_id);

            if ($customer->invoice_settings?->default_payment_method) {
                return self::fromPaymentMethod(
                    $stripe->paymentMethods->retrieve(
                        $customer->invoice_settings->default_payment_method
                    ),
                    'customer'
                );
            }

            /**
             * 3️⃣ ÚLTIMA FACTURA PAGADA
             */
            $invoices = $stripe->invoices->all([
                'customer' => $cliente->stripe_customer_id,
                'limit'    => 5,
                'status'   => 'paid',
            ]);

            foreach ($invoices->data as $invoice) {
                $piId = $invoice->payment_intent ?? null;

                if (is_string($piId) && $piId !== '') {
                    $pi = $stripe->paymentIntents->retrieve($piId);

                    $pmId = $pi->payment_method ?? null;
                    if (is_string($pmId) && $pmId !== '') {
                        return self::fromPaymentMethod(
                            $stripe->paymentMethods->retrieve($pmId),
                            'invoice'
                        );
                    } elseif (is_object($pmId) && ! empty($pmId->id)) {
                        return self::fromPaymentMethod(
                            $stripe->paymentMethods->retrieve($pmId->id),
                            'invoice'
                        );
                    }
                }
            }

            /**
             * 4️⃣ LISTADO DE PAYMENT METHODS (fallback final)
             */
            $methods = $stripe->paymentMethods->all([
                'customer' => $cliente->stripe_customer_id,
                'type'     => 'card',
                'limit'    => 1,
            ]);

            if (! empty($methods->data)) {
                return self::fromPaymentMethod($methods->data[0], 'fallback');
            }
        } catch (\Throwable $e) {
            Log::error('StripePaymentMethodResolver error', [
                'cliente_id' => $cliente->id,
                'error'      => $e->getMessage(),
            ]);
        }

        return self::empty();
    }

    /**
     * Igual que tus controllers: en local desactiva verificación SSL para evitar el error de CA.
     * (En prod NO se toca)
     */
    protected static function makeClient(): StripeClient
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        if (app()->isLocal()) {
            Stripe::setVerifySslCerts(false);
        }

        return new StripeClient(config('services.stripe.secret'));
    }

    protected static function fromPaymentMethod($pm, string $source): array
    {
        if (! isset($pm->type)) {
            return self::empty();
        }

        if ($pm->type === 'card') {
            return [
                'type'      => 'card',
                'label'     => 'Tarjeta',
                'last4'     => $pm->card->last4 ?? null,
                'brand'     => $pm->card->brand ?? null,
                'exp_month' => $pm->card->exp_month ?? null,
                'exp_year'  => $pm->card->exp_year ?? null,
                'source'    => $source,
            ];
        }

        if ($pm->type === 'sepa_debit') {
            return [
                'type'   => 'sepa_debit',
                'label'  => 'SEPA',
                'last4'  => $pm->sepa_debit->last4 ?? null,
                'brand'  => 'SEPA',
                'source' => $source,
            ];
        }

        return self::empty();
    }

    protected static function empty(): array
    {
        return [
            'type'      => null,
            'label'     => 'Sin método configurado',
            'last4'     => null,
            'brand'     => null,
            'exp_month' => null,
            'exp_year'  => null,
            'source'    => null,
        ];
    }
}
