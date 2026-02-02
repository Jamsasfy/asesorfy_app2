<?php

namespace App\Jobs;

use App\Enums\DocumentoEstadoEnum;
use App\Models\Cliente;
use App\Models\Documento;
use App\Models\User;
use App\Models\ChatConversacion;
use App\Models\ChatMensaje;
use App\Notifications\ClienteTienePendientesDeRespuesta;
use App\Jobs\SendTelegramMessageJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class NotificarPendientesRespuestaClienteJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $clienteId) {}

    // ✅ “anti-spam” en cola: colapsa disparos seguidos (pero permite otro disparo más tarde)
    public $uniqueFor = 600; // 10 min

    public function uniqueId(): string
    {
        return "cliente_pendientes_respuesta:{$this->clienteId}";
    }

    public function handle(): void
    {
        $tz = 'Europe/Madrid';

        $cliente = Cliente::find($this->clienteId);
        if (! $cliente) {
            return;
        }

        // ✅ ¿Sigue habiendo pendientes de respuesta del cliente?
        $count = Documento::query()
            ->where('cliente_id', $this->clienteId)
            ->where('estado', DocumentoEstadoEnum::NECESITA_ACLARACION->value)
            ->whereNull('aclaracion_respondida_at')
            ->count();

        if ($count <= 0) {
            return;
        }

        // ✅ Regla: máximo 1 aviso al día por cliente (email + db + telegram)
        $last = $cliente->pendientes_respuesta_notificado_at;
        if ($last && $last->timezone($tz)->isSameDay(now($tz))) {
            return;
        }

        // ✅ Usuarios del portal vinculados al cliente (pivot cliente_user)
        $userIds = DB::table('cliente_user')
            ->where('cliente_id', $this->clienteId)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Si no hay usuarios vinculados al portal, NO notificamos nada (ni telegram)
        if (empty($userIds)) {
            return;
        }

        // 1) Notificación interna + email (por usuario portal)
        $users = User::query()->whereIn('id', $userIds)->get();
        foreach ($users as $user) {
            $user->notify(new ClienteTienePendientesDeRespuesta($cliente, $count));
        }

        // 2) Telegram (solo si el cliente está vinculado por Telegram)
        $chat = ChatConversacion::query()
            ->where('cliente_id', $this->clienteId)
            ->where('tipo', 'cliente')
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->first();
                if ($chat?->telegram_chat_id) {
                    // ✅ Mensaje que verá el asesor en MisChats (FIN, sin enlaces)
                    ChatMensaje::create([
                        'chat_id'    => $chat->id,
                        'user_id'    => 9999,       // ✅ IAFy
                        'origen'     => 'sistema',
                        'tipo'       => 'text',
                        'contenido'  => '🤖 IAFy ha avisado al cliente por Telegram: tiene documentos pendientes de respuesta.',
                        'payload'    => [
                            'kind'  => 'iafy_pendientes_respuesta_ui',
                            'count' => $count,
                        ],
                        'leido'      => true,
                        'read_at'    => now(),
                    ]);

                    // ✅ Mensaje que recibirá el cliente en Telegram (SIN enlaces) -> ENVIAR DIRECTO, SIN GUARDAR EN BD
                    $clienteText =
                        "📌 Tienes documentos pendientes de respuesta\n\n"
                        . "Entra al portal para responder, en apartado Mis Documentos los verás en Requiere tu respuesta.\n\n"
                        . "Cualquier duda escribe por aquí a tu asesor, gracias.";

                    try {
                        app(\App\Services\TelegramService::class)->sendMessage(
                            (int) $chat->telegram_chat_id,
                            $clienteText
                        );
                    } catch (\Throwable $e) {
                        // silent fail: no rompemos el job
                    }
                }


        // ✅ Marcamos “ya avisado hoy”
        $cliente->forceFill([
            'pendientes_respuesta_notificado_at' => now($tz),
        ])->save();
    }
}
