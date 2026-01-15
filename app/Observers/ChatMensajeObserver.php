<?php

namespace App\Observers;

use App\Models\ChatMensaje;
use Illuminate\Support\Facades\DB;

class ChatMensajeObserver
{
    public function created(ChatMensaje $msg): void
    {
        // Solo nos interesa cliente/asesor (sistema no cambia “pendiente”)
        $origen = (string) ($msg->origen ?? '');
        if (! in_array($origen, ['cliente', 'asesor'], true)) {
            return;
        }

        DB::transaction(function () use ($msg, $origen) {
            // Lock conversación para evitar carreras (telegram + asesor enviando a la vez)
            $chat = $msg->chat()->lockForUpdate()->first();
            if (! $chat) {
                return;
            }

            // Por si vienen created_at raros, siempre tratamos “último” por ID
            if ($origen === 'cliente') {
                $chat->last_cliente_message_id = $msg->id;
                $chat->last_cliente_at = $msg->created_at ?? now();

                $chat->pendiente_respuesta = true;
                $chat->pendiente_since_at = $chat->last_cliente_at;

                $chat->last_message_at = $msg->created_at ?? now();
                $chat->save();

                return;
            }

            // asesor
            $chat->last_asesor_message_id = $msg->id;
            $chat->last_asesor_at = $msg->created_at ?? now();
            $chat->last_message_at = $msg->created_at ?? now();

            // Si estaba pendiente, cerramos “pendiente” y registramos tiempo de respuesta
            if ((bool) $chat->pendiente_respuesta && $chat->pendiente_since_at) {
                // ✅ diff correcto (siempre positivo) => evita que se quede en 0 por signo
                $sec = (int) $chat->pendiente_since_at->diffInSeconds($chat->last_asesor_at);
                $chat->last_response_seconds = max(0, $sec);

                $chat->pendiente_respuesta = false;
                $chat->pendiente_since_at = null;
            }

            $chat->save();
        });
    }
}
