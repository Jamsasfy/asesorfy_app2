<?php

namespace App\Console\Commands;

use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Models\ClienteSuscripcion;
use App\Services\StripeSuscripcionSyncService;
use Illuminate\Console\Command;

class SincronizarSuscripcionesStripe extends Command
{
    protected $signature = 'stripe:sync {--suscripcion_id=} {--force}';

    protected $description = 'Sincroniza suscripciones con Stripe usando StripeSuscripcionSyncService (fuente única)';

    public function handle(StripeSuscripcionSyncService $service): int
    {
        $this->info('🔄 Iniciando sincronización con Stripe (service)...');
        $this->newLine();

        // Filtrar suscripciones
        $query = ClienteSuscripcion::query()
            ->whereNotNull('stripe_subscription_id');

        // ✅ Opción: sincronizar solo una suscripción específica
        if ($id = $this->option('suscripcion_id')) {
            $query->where('id', (int) $id);
            $this->info("📌 Sincronizando solo suscripción ID: {$id}");
        } else {
            // Solo suscripciones activas o con problemas
            if (! $this->option('force')) {
                $query->whereIn('estado', [
                    ClienteSuscripcionEstadoEnum::ACTIVA,
                    ClienteSuscripcionEstadoEnum::IMPAGADA,
                    ClienteSuscripcionEstadoEnum::EN_PRUEBA,
                    ClienteSuscripcionEstadoEnum::PAUSADA,
                ]);
            }
        }

        $suscripciones = $query->get();

        if ($suscripciones->isEmpty()) {
            $this->warn('⚠️  No hay suscripciones para sincronizar');
            return self::SUCCESS;
        }

        $this->info("📊 Total de suscripciones: {$suscripciones->count()}");
        $this->newLine();

        $subsActualizadas = 0;

        $facturasCreadas  = 0;
        $facturasSaltadas = 0;
        $facturasErrores  = 0;

        $errores = 0;

        $progressBar = $this->output->createProgressBar($suscripciones->count());
        $progressBar->start();

        foreach ($suscripciones as $suscripcion) {
            try {
                $res = $service->syncOne(
                    suscripcion: $suscripcion,
                    backfillInvoices: true,
                    from: null,
                    to: null,
                );

                if (! empty($res['subscription_updated'])) {
                    $subsActualizadas++;
                }

                $facturasCreadas  += (int) ($res['invoices_created'] ?? 0);
                $facturasSaltadas += (int) ($res['invoices_skipped'] ?? 0);
                $facturasErrores  += (int) ($res['invoices_errors'] ?? 0);

            } catch (\Throwable $e) {
                $errores++;
                // Si quieres ver el motivo:
                $this->newLine();
                $this->error("  ❌ Suscripción #{$suscripcion->id}: {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // ✅ Resumen
        $this->info('═══════════════════════════════════════');
        $this->info('            RESUMEN                    ');
        $this->info('═══════════════════════════════════════');
        $this->line("  📊 Total procesadas:           {$suscripciones->count()}");
        $this->line("  ✅ Suscripciones actualizadas: {$subsActualizadas}");
        $this->line("  🧾 Facturas creadas:           {$facturasCreadas}");
        $this->line("  ⚪ Facturas saltadas:          {$facturasSaltadas}");
        $this->line("  ❌ Errores facturas:           {$facturasErrores}");
        $this->line("  ❌ Errores (generales):        {$errores}");
        $this->info('═══════════════════════════════════════');

        return self::SUCCESS;
    }
}
