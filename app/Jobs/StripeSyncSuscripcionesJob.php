<?php

namespace App\Jobs;

use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Models\ClienteSuscripcion;
use App\Models\StripeSyncRun;
use App\Models\StripeSyncRunLog;
use App\Services\StripeSuscripcionSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stripe\Exception\InvalidRequestException as StripeInvalidRequestException;

class StripeSyncSuscripcionesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $runId)
    {
    }

    public function handle(StripeSuscripcionSyncService $service): void
    {
        $run = StripeSyncRun::find($this->runId);
        if (! $run) {
            return;
        }

        $run->update([
            'status'        => 'running',
            'started_at'    => now(),
            'finished_at'   => null,
            'error_message' => null,
        ]);

        $q = ClienteSuscripcion::query()
            ->whereNotNull('stripe_subscription_id')
            ->where('stripe_subscription_id', '!=', '')
            ->whereRaw('TRIM(stripe_subscription_id) != ""');

        if ($run->solo_activas) {
            $q->whereIn('estado', [
                ClienteSuscripcionEstadoEnum::ACTIVA,
                ClienteSuscripcionEstadoEnum::IMPAGADA,
                ClienteSuscripcionEstadoEnum::EN_PRUEBA,
                ClienteSuscripcionEstadoEnum::PAUSADA,
            ]);
        }

        $total = (int) (clone $q)->count();
        $run->update(['total' => $total]);

        $subsUpdated    = 0;
        $subsNoChange   = 0;

        $invCreated     = 0;
        $invSkipped     = 0;
        $invErrors      = 0;

        $errors         = 0;
        $skippedInvalid = 0;

        $chunkSize = max(10, min(200, (int) $run->chunk_size));

        try {
            $q->orderBy('id')->chunkById($chunkSize, function ($chunk) use (
                $service,
                $run,
                &$subsUpdated,
                &$subsNoChange,
                &$invCreated,
                &$invSkipped,
                &$invErrors,
                &$errors,
                &$skippedInvalid
            ) {
                foreach ($chunk as $suscripcion) {
                    // ✅ 1) ID vacío/raruno: warning + skip
                    if (! filled($suscripcion->stripe_subscription_id)) {
                        $skippedInvalid++;

                        StripeSyncRunLog::create([
                            'stripe_sync_run_id'     => $run->id,
                            'level'                  => 'warning',
                            'cliente_suscripcion_id' => $suscripcion->id,
                            'stripe_subscription_id' => $suscripcion->stripe_subscription_id,
                            'message'                => 'Saltada: stripe_subscription_id vacío / inválido',
                            'context'                => [
                                'handled_as' => 'skip_warning',
                                'reason'     => 'empty_or_invalid_stripe_subscription_id',
                            ],
                        ]);

                        continue;
                    }

                    try {
                        $res = $service->syncOne(
                            suscripcion: $suscripcion,
                            backfillInvoices: (bool) $run->backfill_invoices,
                            from: null,
                            to: null,
                        );

                        if ((bool) ($res['subscription_updated'] ?? false)) {
                            $subsUpdated++;
                        } else {
                            $subsNoChange++;
                        }

                        $invCreated += (int) ($res['invoices_created'] ?? 0);
                        $invSkipped += (int) ($res['invoices_skipped'] ?? 0);
                        $invErrors  += (int) ($res['invoices_errors'] ?? 0);

                    // ✅ 2) Caso clave: Stripe dice "no existe esa suscripción" → WARNING + SKIP
                    } catch (StripeInvalidRequestException $e) {
                        $stripeCode = method_exists($e, 'getStripeCode') ? $e->getStripeCode() : null;
                        $msg = (string) $e->getMessage();

                        $isMissing =
                            ($stripeCode === 'resource_missing') ||
                            str_contains($msg, 'No such subscription');

                        if ($isMissing) {
                            $skippedInvalid++;

                            StripeSyncRunLog::create([
                                'stripe_sync_run_id'     => $run->id,
                                'level'                  => 'warning',
                                'cliente_suscripcion_id' => $suscripcion->id,
                                'stripe_subscription_id' => $suscripcion->stripe_subscription_id,
                                'message'                => "Saltada: suscripción no existe en Stripe ({$suscripcion->stripe_subscription_id})",
                                'context'                => [
                                    'handled_as'   => 'skip_warning',
                                    'reason'       => 'stripe_subscription_not_found',
                                    'stripe_code'  => $stripeCode,
                                    'exception'    => class_basename($e),
                                    'message'      => $msg,
                                ],
                            ]);

                            // ✅ OJO: no tocamos la BD de suscripciones
                            continue;
                        }

                        // Si es otro InvalidRequest distinto, lo tratamos como error normal
                        throw $e;

                    } catch (\Throwable $e) {
                        $errors++;

                        StripeSyncRunLog::create([
                            'stripe_sync_run_id'     => $run->id,
                            'level'                  => 'error',
                            'cliente_suscripcion_id' => $suscripcion->id,
                            'stripe_subscription_id' => $suscripcion->stripe_subscription_id,
                            'message'                => $e->getMessage(),
                            'context'                => [
                                'handled_as' => 'error',
                                'exception'  => class_basename($e),
                                'trace'      => substr($e->getTraceAsString(), 0, 3000),
                            ],
                        ]);

                        // último error (solo para ver rápido)
                        $run->update(['error_message' => $e->getMessage()]);
                    }
                }

                // ✅ checkpoint
                $run->update([
                    'subs_updated'    => $subsUpdated,
                    'subs_no_change'  => $subsNoChange,
                    'skipped_invalid' => $skippedInvalid,
                    'inv_created'     => $invCreated,
                    'inv_skipped'     => $invSkipped,
                    'inv_errors'      => $invErrors,
                    'errors'          => $errors,
                ]);
            });

            $run->update([
                'status'          => 'finished',
                'finished_at'     => now(),
                'subs_updated'    => $subsUpdated,
                'subs_no_change'  => $subsNoChange,
                'skipped_invalid' => $skippedInvalid,
                'inv_created'     => $invCreated,
                'inv_skipped'     => $invSkipped,
                'inv_errors'      => $invErrors,
                'errors'          => $errors,
            ]);

        } catch (\Throwable $e) {
            // error general del job
            StripeSyncRunLog::create([
                'stripe_sync_run_id'     => $run->id,
                'level'                  => 'error',
                'cliente_suscripcion_id' => null,
                'stripe_subscription_id' => null,
                'message'                => $e->getMessage(),
                'context'                => [
                    'handled_as' => 'job_failed',
                    'exception'  => class_basename($e),
                    'trace'      => substr($e->getTraceAsString(), 0, 3000),
                ],
            ]);

            $run->update([
                'status'        => 'failed',
                'finished_at'   => now(),
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
