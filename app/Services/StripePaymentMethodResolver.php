<?php

namespace App\Services;

use Stripe\StripeClient;
use App\Models\Cliente;
use Illuminate\Support\Facades\Log;

class StripePaymentMethodResolver
{
    public static function resolve(Cliente $cliente): array
    {
        if (! $cliente->stripe_customer_id) {
            return self::empty();
        }

        try {
            $stripe = new StripeClient(config('services.stripe.secret'));

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
                if ($subscription->default_payment_method) {
                    return self::fromPaymentMethod(
                        $stripe->paymentMethods->retrieve($subscription->default_payment_method),
                        'subscription'
                    );
                }

                // latest invoice → payment intent
                if ($subscription->latest_invoice?->payment_intent) {
                    $pi = $stripe->paymentIntents->retrieve(
                        $subscription->latest_invoice->payment_intent
                    );

                    if ($pi->payment_method) {
                        return self::fromPaymentMethod(
                            $stripe->paymentMethods->retrieve($pi->payment_method),
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
                if ($invoice->payment_intent) {
                    $pi = $stripe->paymentIntents->retrieve($invoice->payment_intent);

                    if ($pi->payment_method) {
                        return self::fromPaymentMethod(
                            $stripe->paymentMethods->retrieve($pi->payment_method),
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

            if (!empty($methods->data)) {
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

    protected static function fromPaymentMethod($pm, string $source): array
    {
        if ($pm->type === 'card') {
            return [
                'type'   => 'card',
                'label'  => 'Tarjeta',
                'last4'  => $pm->card->last4,
                'brand'  => $pm->card->brand,
                'source' => $source,
            ];
        }

        if ($pm->type === 'sepa_debit') {
            return [
                'type'   => 'sepa_debit',
                'label'  => 'SEPA',
                'last4'  => $pm->sepa_debit->last4,
                'brand'  => 'SEPA',
                'source' => $source,
            ];
        }

        return self::empty();
    }

    protected static function empty(): array
    {
        return [
            'type'   => null,
            'label'  => 'Sin método configurado',
            'last4'  => null,
            'brand'  => null,
            'source' => null,
        ];
    }
}
