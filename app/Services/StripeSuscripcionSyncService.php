<?php

namespace App\Services;

use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Models\ClienteSuscripcion;
use App\Models\Factura;
use App\Services\FacturacionService;
use Carbon\Carbon;
use Stripe\Invoice;
use Stripe\Stripe;
use Stripe\Subscription;
use Throwable;

class StripeSuscripcionSyncService
{
    /**
     * Sincroniza una suscripción local con Stripe y (opcional) backfill de invoices pagadas.
     *
     * @return array{
     *   subscription_updated: bool,
     *   updates: array,
     *   invoices_created: int,
     *   invoices_skipped: int,
     *   invoices_errors: int,
     * }
     */
    public function syncOne(
        ClienteSuscripcion $suscripcion,
        bool $backfillInvoices = true,
        ?Carbon $from = null,
        ?Carbon $to = null
    ): array {
        // ✅ Guard: si no hay stripe_subscription_id válido, no hacemos nada (evita errores “legacy”)
        if (! filled($suscripcion->stripe_subscription_id)) {
            return [
                'subscription_updated' => false,
                'updates'              => [],
                'invoices_created'     => 0,
                'invoices_skipped'     => 0,
                'invoices_errors'      => 0,
            ];
        }

        Stripe::setApiKey(config('services.stripe.secret'));
        if (app()->isLocal()) {
            Stripe::setVerifySslCerts(false);
        }

        $from = ($from ?: now()->subDays(90))->startOfDay();
        $to   = ($to   ?: now())->endOfDay();

        // 1) Sync suscripción
        $stripeSub = Subscription::retrieve($suscripcion->stripe_subscription_id);

        $updates = [];

        if ($suscripcion->stripe_status !== (string) $stripeSub->status) {
            $updates['stripe_status'] = (string) $stripeSub->status;
        }

        // ✅ Stripe -> fechas (normalizadas a Europe/Madrid y guardadas como DATE)
        // IMPORTANTE: current_period_end puede venir en la suscripción O en items (según objeto/SDK)
        $periodEndTimestamp = null;

        if (! empty($stripeSub->current_period_end)) {
            $periodEndTimestamp = (int) $stripeSub->current_period_end;
        } elseif (! empty($stripeSub->items?->data)) {
            // ✅ Si hay varios items, nos quedamos con el MÁS TARDE
            $ends = [];

            foreach ($stripeSub->items->data as $it) {
                if (! empty($it->current_period_end)) {
                    $ends[] = (int) $it->current_period_end;
                }
            }

            if (! empty($ends)) {
                $periodEndTimestamp = max($ends);
            }
        }

        if ($periodEndTimestamp) {
            $tz = config('app.timezone', 'Europe/Madrid');

            $periodEndLocal = Carbon::createFromTimestamp($periodEndTimestamp, 'UTC')
                ->timezone($tz)
                ->startOfDay(); // importante: tu cast es 'date'

            $current = $suscripcion->proxima_fecha_facturacion
                ? $suscripcion->proxima_fecha_facturacion->copy()->startOfDay()
                : null;

            // Compara por date string para evitar líos de TZ/hora
            if (! $current || $current->toDateString() !== $periodEndLocal->toDateString()) {
                $updates['proxima_fecha_facturacion'] = $periodEndLocal;
            }

            // ✅ (Opcional) si tienes columna para debug/trazabilidad
            // $updates['stripe_current_period_end'] = $periodEndTimestamp;
        }

        $estadoLocal = $this->mapearEstadoStripe((string) $stripeSub->status, $suscripcion);

        // ✅ OJO: aquí tu estado es enum casteado; comparamos por value de forma segura
        if ($estadoLocal && ((string) $suscripcion->estado?->value) !== (string) $estadoLocal->value) {
            $updates['estado'] = $estadoLocal;

            if ($estadoLocal === ClienteSuscripcionEstadoEnum::CANCELADA && ! $suscripcion->fecha_fin) {
                $updates['fecha_fin'] = now();
            }
        }

        $subscriptionUpdated = false;
        if (! empty($updates)) {
            $suscripcion->update($updates);

            // ✅ Si quieres que el Resource “note” cambios aunque el date sea igual (a veces ayuda)
            // $suscripcion->touch();

            $subscriptionUpdated = true;
        }

        // 2) Backfill invoices pagadas -> Factura local (idempotente por stripe_invoice_id)
        $created = 0;
        $skipped = 0;
        $errors  = 0;

        if ($backfillInvoices) {
            [$created, $skipped, $errors] = $this->backfillPaidInvoices(
                suscripcion: $suscripcion,
                from: $from,
                to: $to,
            );
        }

        return [
            'subscription_updated' => $subscriptionUpdated,
            'updates'              => $updates,
            'invoices_created'     => $created,
            'invoices_skipped'     => $skipped,
            'invoices_errors'      => $errors,
        ];
    }

    /**
     * Backfill de invoices pagadas (Stripe) para una suscripción local.
     * - Solo crea facturas cuando: status=paid y amount_paid>0
     * - Idempotente por stripe_invoice_id (si existe, salta)
     */
    private function backfillPaidInvoices(ClienteSuscripcion $suscripcion, Carbon $from, Carbon $to): array
    {
        $created = 0;
        $skipped = 0;
        $errors  = 0;

        $cliente = $suscripcion->cliente;

        if (! $cliente || empty($cliente->stripe_customer_id)) {
            return [0, 0, 0];
        }

        // ✅ Guard extra: si por lo que sea entra aquí sin stripe_subscription_id
        if (! filled($suscripcion->stripe_subscription_id)) {
            return [0, 0, 0];
        }

        $startingAfter = null;

        do {
            $params = [
                'subscription' => $suscripcion->stripe_subscription_id,
                'limit'        => 100,
                'created'      => ['gte' => $from->timestamp, 'lte' => $to->timestamp],
            ];

            if ($startingAfter) {
                $params['starting_after'] = $startingAfter;
            }

            $list = Invoice::all($params);

            foreach ($list->data as $inv) {
                try {
                    $stripeInvoiceId = (string) ($inv->id ?? '');

                    if ($stripeInvoiceId === '') {
                        $errors++;
                        continue;
                    }

                    // ✅ Idempotencia
                    if (Factura::where('stripe_invoice_id', $stripeInvoiceId)->exists()) {
                        $skipped++;
                        continue;
                    }

                    $status     = (string) ($inv->status ?? '');
                    $amountPaid = (int) ($inv->amount_paid ?? 0);

                    // ✅ Solo pagadas + >0€
                    if ($status !== 'paid' || $amountPaid <= 0) {
                        $skipped++;
                        continue;
                    }

                    $invoice = Invoice::retrieve([
                        'id'     => $stripeInvoiceId,
                        'expand' => ['lines.data.price', 'subscription'],
                    ]);

                    $fechaPago = Carbon::createFromTimestamp(
                        (int) ($invoice->status_transitions->paid_at ?? now()->timestamp)
                    );

                    // ✅ Periodo real de la factura (para poner "Febrero 2026", etc.)
                    $periodoInicio = null;

                    try {
                        $periodStartTs = null;

                        // Preferido: la primera línea trae period.start
                        if (! empty($invoice->lines?->data) && isset($invoice->lines->data[0]->period->start)) {
                            $periodStartTs = (int) $invoice->lines->data[0]->period->start;
                        }

                        // Fallback: si no viniera, usa current_period_start de la suscripción expandida
                        if (! $periodStartTs && $invoice->subscription && isset($invoice->subscription->current_period_start)) {
                            $periodStartTs = (int) $invoice->subscription->current_period_start;
                        }

                        if ($periodStartTs) {
                            $periodoInicio = Carbon::createFromTimestamp($periodStartTs, 'UTC')
                                ->timezone(config('app.timezone', 'Europe/Madrid'));
                        }
                    } catch (\Throwable) {
                        $periodoInicio = null;
                    }

                    FacturacionService::crearFacturaRecurrente(
                        cliente: $cliente,
                        suscripcion: $suscripcion,
                        amountCents: $amountPaid,
                        fechaPago: $fechaPago,
                        stripeInvoiceId: $stripeInvoiceId,
                        stripeInvoiceNumber: $invoice->number ?? null,
                        stripePaymentIntentId: $invoice->payment_intent ?? null,
                        periodoInicio: $periodoInicio,
                    );

                    $created++;

                } catch (Throwable) {
                    $errors++;
                }
            }

            $hasMore = (bool) ($list->has_more ?? false);
            $startingAfter = $hasMore && ! empty($list->data) ? end($list->data)->id : null;

        } while ($hasMore);

        return [$created, $skipped, $errors];
    }

    private function mapearEstadoStripe(string $stripeStatus, ClienteSuscripcion $suscripcion): ?ClienteSuscripcionEstadoEnum
    {
        if (in_array($suscripcion->estado, [
            ClienteSuscripcionEstadoEnum::CANCELADA,
            ClienteSuscripcionEstadoEnum::FINALIZADA,
        ], true)) {
            if (in_array($stripeStatus, ['canceled', 'incomplete_expired'], true)) {
                return ClienteSuscripcionEstadoEnum::CANCELADA;
            }
            return null;
        }

        return match ($stripeStatus) {
            'active' => ClienteSuscripcionEstadoEnum::ACTIVA,
            'trialing' => ClienteSuscripcionEstadoEnum::EN_PRUEBA,
            'past_due', 'unpaid' => ClienteSuscripcionEstadoEnum::IMPAGADA,
            'canceled', 'incomplete_expired' => ClienteSuscripcionEstadoEnum::CANCELADA,
            'paused' => ClienteSuscripcionEstadoEnum::PAUSADA,
            default => null,
        };
    }
}
