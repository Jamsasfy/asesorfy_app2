<?php

namespace App\Jobs;

use App\Models\ChatMensaje;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SendTelegramMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Reintentos (errores temporales).
     */
    public int $tries = 5;

    /**
     * Timeout del job (segundos).
     */
    public int $timeout = 60;

    /**
     * Backoff progresivo base (segundos).
     * Laravel soporta backoff() como método en muchas versiones.
     */
    public function backoff(): array
    {
        return [10, 30, 90, 180, 300];
    }

    public function __construct(public int $chatMensajeId) {}

    public function handle(TelegramService $telegram): void
    {
        $msg = ChatMensaje::query()->find($this->chatMensajeId);
        if (! $msg) {
            return;
        }

        // Evita reenviar si ya se envió
        if ($msg->estado_envio === 'sent') {
            return;
        }

        $chat = $msg->chat;
        if (! $chat || ! $chat->telegram_chat_id) {
            $this->markFailed($msg, 'Chat no vinculado a Telegram.');
            return;
        }

        // Marcamos pending al entrar (si venía null/failed por intentos anteriores)
        if ($msg->estado_envio !== 'pending') {
            $msg->update([
                'estado_envio' => 'pending',
                'last_error'   => null,
            ]);
        }

        try {
            $tipo = (string) ($msg->tipo ?? 'text');
            $result = null;

            if ($tipo === 'text') {
                $text = trim((string) $msg->contenido);
                if ($text === '') {
                    $this->markFailed($msg, 'Mensaje vacío.');
                    return;
                }

                $result = $telegram->sendMessage($chat->telegram_chat_id, $text);

            } elseif (in_array($tipo, ['photo', 'document'], true)) {
                $rel = trim((string) ($msg->file_path ?? ''));

                if ($rel === '') {
                    $this->markFailed($msg, "Mensaje {$tipo} sin file_path.");
                    return;
                }

                if (! Storage::disk('local')->exists($rel)) {
                    $this->markFailed($msg, "Archivo no encontrado en storage(local): {$rel}");
                    return;
                }

                $abs = Storage::disk('local')->path($rel);

                if ($tipo === 'photo') {
                    $result = $telegram->sendPhoto(
                        chatId: $chat->telegram_chat_id,
                        absolutePath: $abs,
                        caption: $msg->caption ? (string) $msg->caption : null
                    );
                } else {
                    $result = $telegram->sendDocument(
                        chatId: $chat->telegram_chat_id,
                        absolutePath: $abs,
                        filename: (string) ($msg->file_original_name ?? 'documento'),
                        caption: $msg->caption ? (string) $msg->caption : null
                    );
                }

            } else {
                $this->markFailed($msg, "Tipo de mensaje no soportado: {$tipo}");
                return;
            }

            $telegramMessageId = data_get($result, 'result.message_id');

            $msg->update([
                'estado_envio'        => 'sent',
                'telegram_message_id' => $telegramMessageId ?: $msg->telegram_message_id,
                'last_error'          => null,
            ]);

        } catch (\Throwable $e) {
            $message = Str::limit($e->getMessage(), 1000);

            // Si es permanente, cortamos.
            if ($this->isPermanentTelegramError($message)) {
                $this->markFailed($msg, $message);
                return;
            }

            /**
             * Temporal:
             * - NO marcamos failed aquí (porque se va a reintentar y no queremos “triángulo” prematuro).
             * - mantenemos pending y guardamos last_error para debug.
             */
            $msg->update([
                'estado_envio' => 'pending',
                'last_error'   => $message,
            ]);

            // Reintento por cola
            throw $e;
        }
    }

    private function markFailed(ChatMensaje $msg, string $error): void
    {
        $msg->update([
            'estado_envio' => 'failed',
            'last_error'   => Str::limit($error, 1000),
        ]);
    }

    /**
     * Heurística: errores que no merece reintentar.
     * Telegram suele devolver: "Bad Request: ..." / "Forbidden: ..."
     */
    private function isPermanentTelegramError(string $error): bool
    {
        $e = strtolower($error);

        // Permanentes típicos
        if (str_contains($e, 'bad request')) return true;
        if (str_contains($e, 'forbidden')) return true;
        if (str_contains($e, 'chat not found')) return true;
        if (str_contains($e, 'user is deactivated')) return true;
        if (str_contains($e, 'bot was blocked')) return true;

        // Fichero/datos mal guardados (permanente)
        if (str_contains($e, 'file not found')) return true;
        if (str_contains($e, 'sin file_path')) return true;
        if (str_contains($e, 'no encontrado')) return true;

        // OJO: "Too Many Requests" (429) NO es permanente
        if (str_contains($e, 'too many requests')) return false;

        return false;
    }
}
