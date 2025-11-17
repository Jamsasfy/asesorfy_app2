<?php

namespace App\Jobs;

use App\Mail\LeadGenericTemplateMail;
use App\Models\EmailTemplate;
use App\Models\Lead;
use App\Models\LeadAutoEmailLog;
use App\Enums\LeadEstadoEnum;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendLeadEstadoChangedEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $leadId;
    public string $nuevoEstado;

    public function __construct(int $leadId, LeadEstadoEnum|string $nuevoEstado)
    {
        $this->leadId = $leadId;
        $this->nuevoEstado = $nuevoEstado instanceof LeadEstadoEnum
            ? $nuevoEstado->value
            : (string) $nuevoEstado;
    }

    public function handle(): void
    {
        $lead = Lead::find($this->leadId);

        $estadoValue = $this->nuevoEstado;
        $estadoEnum  = LeadEstadoEnum::tryFrom($estadoValue);

        // ======================
        //  LEAD NO ENCONTRADO
        // ======================
        if (! $lead) {
            Log::warning("Email estado: lead no encontrado (ID {$this->leadId})");
            return;
        }

        // ==========================================
        //  CASO ESPECIAL: LEAD SIN EMAIL → SKIPPED
        // ==========================================
        if (empty($lead->email)) {
            Log::warning("Email estado: lead {$lead->id} sin email, no se puede enviar autospam en estado {$estadoValue}");

            // Registramos un log "skipped", que luego usaremos para sugerir el primer email IA
            try {
                LeadAutoEmailLog::create([
                    'lead_id'             => $lead->id,
                    'estado'              => $estadoValue,
                    'intento'             => 1,
                    'template_identifier' => null,
                    'subject'             => null,
                    'body_preview'        => null,
                    'scheduled_at'        => now(),
                    'status'              => 'skipped',
                    'mail_driver'         => config('mail.default'),
                    'provider'            => config('services.mail_provider_name') ?? null,
                    'rate_limited'        => false,
                    'triggered_by_user_id'=> null,
                    'trigger_source'      => 'auto_job',
                ]);
            } catch (\Throwable $e) {
                Log::error("Error al registrar LeadAutoEmailLog skipped para lead {$lead->id}: {$e->getMessage()}");
            }

            // Comentario de Boot IA explicando por qué no se ha enviado
            try {
                $label = $estadoEnum?->getLabel() ?? $estadoValue;

                $lead->comentarios()->create([
                    'user_id'   => 9999, // Boot IA Fy
                    'contenido' => "🤖 Email automático IA NO enviado en estado {$label} porque el lead no tiene email. ".
                        "Cuando añadas un email podrás lanzar el primer email IA desde esta ficha.",
                ]);
            } catch (\Throwable $e) {
                Log::error("Error al crear comentario skipped por falta de email para lead {$lead->id}: {$e->getMessage()}");
            }

            return;
        }

        /**
         * ============================
         *   CONFIG DE ESTADO / AUTO
         * ============================
         */
        $map = config("lead_emails.states.{$estadoValue}");
        if (! $map || empty($map['auto'])) {
            Log::info("Estado {$estadoValue} sin envío automático.");
            return;
        }

        $slugBase = $map['slug'] ?? null;

        if (! $slugBase) {
            Log::warning("No hay slug configurado para {$estadoValue}");
            return;
        }

        /**
         * ======================================
         *   INTENTOS Y SELECCIÓN DE PLANTILLA
         * ======================================
         */

        // Intentos previos de autospam en este estado
        $intentosPrevios = (int) ($lead->estado_email_intentos ?? 0);
        $nuevoIntento    = $intentosPrevios + 1;

        // 1) Intentamos plantilla específica por intento: slug_base_X
        $slugPorIntento = "{$slugBase}_{$nuevoIntento}";

        $template = EmailTemplate::where('slug', $slugPorIntento)
            ->where('activo', true)
            ->first();

        // 2) Si no existe, usamos la plantilla base como fallback
        if (! $template) {
            $template = EmailTemplate::where('slug', $slugBase)
                ->where('activo', true)
                ->first();
        }

        if (! $template) {
            Log::warning("Plantilla email no encontrada/activa para slug '{$slugPorIntento}' ni para slug base '{$slugBase}'");
            return;
        }

        /**
         * ============================
         *   RATE LIMIT POR ESTADO
         * ============================
         */
        $cacheKey = "lead_email_sent_{$this->leadId}_{$estadoValue}";

        if (Cache::has($cacheKey)) {
            Log::info("Email NO enviado (rate-limited) para lead {$this->leadId}, estado {$estadoValue}");

            // Log de envío automático rate-limited
            try {
                LeadAutoEmailLog::create([
                    'lead_id'             => $lead->id,
                    'estado'              => $estadoValue,
                    'intento'             => $nuevoIntento,
                    'template_identifier' => $template->slug,
                    'subject'             => $template->asunto ?? null,
                    'body_preview'        => mb_substr(strip_tags((string) $template->contenido_html), 0, 400),
                    'scheduled_at'        => now(),
                    'status'              => 'rate_limited',
                    'mail_driver'         => config('mail.default'),
                    'provider'            => config('services.mail_provider_name') ?? null,
                    'rate_limited'        => true,
                    'triggered_by_user_id'=> null,
                    'trigger_source'      => 'auto_job',
                ]);
            } catch (\Throwable $e) {
                Log::error("Error al registrar LeadAutoEmailLog rate-limited para lead {$lead->id}: {$e->getMessage()}");
            }

            // Comentario de antispam (últimos 30 min)
            try {
                $label = $estadoEnum?->getLabel() ?? $estadoValue;

                $lead->comentarios()->create([
                    'user_id'   => 9999, // Boot IA Fy
                    'contenido' => "🤖 Email automático IA NO enviado en estado {$label} porque ya se envió otro hace menos de 30 minutos (protección antispam).",
                ]);
            } catch (\Throwable $e) {
                Log::error("Error al crear comentario de rate-limit para lead {$lead->id}: {$e->getMessage()}");
            }

            return;
        }

        Cache::put($cacheKey, true, now()->addMinutes(30));

        /**
         * ============================
         *   RENDER PLANTILLA
         * ============================
         */
        $data = [
            'lead'         => $lead,
            'estado'       => $estadoValue,
            'estado_label' => $estadoEnum?->getLabel() ?? ucfirst(str_replace('_', ' ', $estadoValue)),
            'app_name'     => config('app.name'),
            'app_url'      => config('app.url'),
            'intento'      => $nuevoIntento,
        ];

        $source = "\n" . str_replace(["\r\n", "\r"], "\n", (string) $template->contenido_html);
        $html   = Blade::render($source, $data);

        /**
         * ============================
         *   LOG DE ENVÍO AUTOMÁTICO
         * ============================
         */
        $log = null;

        try {
            $log = LeadAutoEmailLog::create([
                'lead_id'             => $lead->id,
                'estado'              => $estadoValue,
                'intento'             => $nuevoIntento,
                'template_identifier' => $template->slug,
                'subject'             => $template->asunto ?? null,
                'body_preview'        => mb_substr(strip_tags($html), 0, 400),
                'scheduled_at'        => now(), // el job se ejecuta ahora mismo
                'status'              => 'pending',
                'mail_driver'         => config('mail.default'),
                'provider'            => config('services.mail_provider_name') ?? null,
                'rate_limited'        => false,
                'triggered_by_user_id'=> null,
                'trigger_source'      => 'auto_job',
            ]);
        } catch (\Throwable $e) {
            Log::error("Error al registrar LeadAutoEmailLog (pending) para lead {$lead->id}: {$e->getMessage()}");
        }

        /**
         * ============================
         *   ENVÍO DEL EMAIL
         * ============================
         */
        try {
            Mail::to($lead->email)->send(
                new LeadGenericTemplateMail($template->asunto, $html)
            );

            if ($log) {
                $log->update([
                    'sent_at' => now(),
                    'status'  => 'sent',
                ]);
            }

            // ✅ Sumamos 1 al contador de emails del lead (acciones)
            try {
                $lead->increment('emails');
            } catch (\Throwable $ex) {
                Log::error("No se pudo incrementar el contador 'emails' para lead {$lead->id}: {$ex->getMessage()}");
            }

            Log::info("Email enviado correctamente a {$lead->email} para estado {$estadoValue}, intento {$nuevoIntento}");
        } catch (\Throwable $e) {
            Log::error("Error enviando email a {$lead->email} para estado {$estadoValue}, intento {$nuevoIntento}: {$e->getMessage()}");

            if ($log) {
                $log->update([
                    'status'        => 'failed',
                    'error_code'    => method_exists($e, 'getCode') ? $e->getCode() : null,
                    'error_message' => $e->getMessage(),
                ]);
            }

            throw $e;
        }

        /**
         * ===============================
         *   REGISTRAR INTENTO Y COMENTARIO
         * ===============================
         */
        if ($estadoEnum && in_array($estadoEnum, [
            LeadEstadoEnum::INTENTO_CONTACTO,
            LeadEstadoEnum::ESPERANDO_INFORMACION,
        ], true)) {

            // Actualizamos contador e info de estado_email_* en el lead
            $lead->registrarEnvioEmailEstado();

            $label       = $estadoEnum->getLabel();
            $maxAttempts = (int) ($map['reminders']['max_attempts'] ?? 3);

            // Usamos la config de delays para describir el tiempo entre envíos
            $delaysConfig = $map['reminders']['delays'] ?? [];
            /**
             * Importante:
             *  - $intentosPrevios = nº de emails enviados ANTES de éste
             *  - Para el intento N, el delay usado es el asociado a "intentos previos"
             *    Ej: si ya hubo 2 envíos (intentosPrevios = 2) y este es el 3º,
             *        usamos delays[2].
             */
            $delayDays = $delaysConfig[$intentosPrevios] ?? null;

            // Construimos descripción humana del tiempo, basada en la config
            if ($delayDays === null || $delayDays === 0) {
                $textoTiempo = 'enviado a continuación del anterior';
            } elseif ((int) $delayDays === 1) {
                $textoTiempo = 'enviado 1 día después del anterior';
            } else {
                $textoTiempo = 'enviado tras ' . (int) $delayDays . ' días desde el anterior';
            }

            if ($nuevoIntento === 1) {
                // Primer envío: texto sencillo
                $texto = "🤖 Email automático IA enviado en estado {$label} (primer envío).";

            } elseif ($nuevoIntento < $maxAttempts) {
                // Recordatorios intermedios
                $texto = "🤖 Email automático IA enviado en estado {$label} (recordatorio #{$nuevoIntento}, {$textoTiempo}).";

            } else {
                // Último intento configurado
                $texto = "🤖 Email automático IA enviado en estado {$label} (último recordatorio, intento #{$nuevoIntento}, {$textoTiempo}).";
            }

            try {
                $lead->comentarios()->create([
                    'user_id'   => 9999,
                    'contenido' => $texto,
                ]);
            } catch (\Throwable $e) {
                Log::error("Error al crear comentario autospam para lead {$lead->id}: {$e->getMessage()}");
            }
        }
    }
}
