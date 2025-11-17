<?php

namespace App\Console\Commands;

use App\Enums\LeadEstadoEnum;
use App\Jobs\SendLeadEstadoChangedEmailJob;
use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Console\Command;

class EnviarLeadsRecordatoriosCommand extends Command
{
    protected $signature = 'leads:enviar-recordatorios';

    protected $description = 'Envía emails de recordatorio a leads según su estado, intentos y reglas de configuración';

    public function handle(): int
    {
        $this->info('➡ Iniciando proceso de recordatorios de leads...');

        // Estados que tienen recordatorios definidos en la config
        $statesConfig = config('lead_emails.states', []);

        $estadosConRecordatorios = collect($statesConfig)
            ->filter(fn ($cfg) => isset($cfg['reminders']))
            ->keys()
            ->all();

        if (empty($estadosConRecordatorios)) {
            $this->warn('No hay estados con recordatorios configurados. Saliendo.');
            return self::SUCCESS;
        }

        $cooldownHours = (int) config('lead_emails.manual_interaction_cooldown_hours', 24);

        // Candidatos: leads con estados válidos y condiciones básicas
        $leads = Lead::query()
            ->whereIn('estado', $estadosConRecordatorios)
            ->whereNotNull('email')
            ->whereNotNull('estado_email_ultima_fecha')
            ->where('estado_email_intentos', '>=', 1)
            ->get();

        // 🔥 Estadísticas de autospam
        $totalCargados = $leads->count();
        $totalConAutospam = $leads->where('autospam_activo', true)->count();
        $totalSinAutospam = $leads->where('autospam_activo', false)->count();

        $this->info("Leads candidatos cargados: {$totalCargados}");
        $this->line(" - Con autospam activo: {$totalConAutospam}");
        $this->line(" - Con autospam desactivado: {$totalSinAutospam}");

        $ahora = now();

        // Aplicar filtro completo (incluye autospam)
        $leadsParaRecordatorio = $leads->filter(function (Lead $lead) use ($statesConfig, $ahora, $cooldownHours) {

            // 🔥 Si el autospam está desactivado → fuera
            if (! $lead->autospam_activo) {
                return false;
            }

            $estadoValue = $lead->estado instanceof LeadEstadoEnum
                ? $lead->estado->value
                : $lead->estado;

            $configEstado = $statesConfig[$estadoValue] ?? null;

            if (! $configEstado || ! isset($configEstado['reminders'])) {
                return false;
            }

            $reminders = $configEstado['reminders'];
            $maxAttempts = (int) ($reminders['max_attempts'] ?? 0);
            $intentosActuales = (int) ($lead->estado_email_intentos ?? 0);

            if ($maxAttempts > 0 && $intentosActuales >= $maxAttempts) {
                return false;
            }

            $delays = $reminders['delays'] ?? [];

            if (! isset($delays[$intentosActuales])) {
                return false;
            }

            $diasMinimos = (int) $delays[$intentosActuales];

            $ultimaFecha = $lead->estado_email_ultima_fecha
                ? Carbon::parse($lead->estado_email_ultima_fecha)
                : null;

            if (! $ultimaFecha) {
                return false;
            }

            $diffDias = $ultimaFecha->diffInDays($ahora);

            if ($diffDias < $diasMinimos) {
                return false;
            }

            if ($lead->ultima_interaccion_manual_at) {
                $ultimaInteraccion = Carbon::parse($lead->ultima_interaccion_manual_at);
                $diffHoras = $ultimaInteraccion->diffInHours($ahora);

                if ($diffHoras < $cooldownHours) {
                    return false;
                }
            }

            return true;
        });

        $this->info('Leads a los que se les enviará recordatorio: ' . $leadsParaRecordatorio->count());

        foreach ($leadsParaRecordatorio as $lead) {
            $estadoValue = $lead->estado instanceof LeadEstadoEnum
                ? $lead->estado->value
                : $lead->estado;

            $this->line(" - Lead {$lead->id} ({$lead->email}), estado {$estadoValue}, intentos: {$lead->estado_email_intentos}");

            SendLeadEstadoChangedEmailJob::dispatch($lead->id, $estadoValue);
        }

        $this->info('✅ Proceso de recordatorios de leads finalizado.');

        return self::SUCCESS;
    }
}
