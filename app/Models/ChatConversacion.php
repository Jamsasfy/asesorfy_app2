<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversacion extends Model
{
    protected $table = 'chat_conversaciones';

    protected $fillable = [
        'tipo',
        'cliente_id',
        'asesor_id',
        'telegram_chat_id',
        'telegram_thread_id',
        'telegram_username',   // ✅ NUEVO
        'telegram_first_name', // ✅ NUEVO
        'estado',
        'unread_count',
        'last_message_at',

        // ✅ NUEVOS (metrics / estado conversación)
        'last_cliente_message_id',
        'last_cliente_at',
        'last_asesor_message_id',
        'last_asesor_at',
        'pendiente_respuesta',
        'pendiente_since_at',
        'last_response_seconds',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'telegram_thread_id' => 'integer',

        // ✅ NUEVOS
        'last_cliente_at' => 'datetime',
        'last_asesor_at' => 'datetime',
        'pendiente_since_at' => 'datetime',
        'pendiente_respuesta' => 'boolean',
        'last_response_seconds' => 'integer',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function asesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asesor_id');
    }

    public function mensajes(): HasMany
    {
        return $this->hasMany(ChatMensaje::class, 'chat_id')->orderBy('id');
    }

    /**
     * Asegura que esta conversación tiene un topic de Telegram asignado.
     * Si ya tiene telegram_thread_id, no hace nada (idempotente).
     * Si no tiene, crea el topic en Telegram y guarda el thread_id.
     *
     * @return int|null El telegram_thread_id (null si no hay telegram_chat_id)
     */
    public function ensureTopicForCliente(): ?int
    {
        // Ya tiene topic → devolver directamente
        if ($this->telegram_thread_id) {
            return (int) $this->telegram_thread_id;
        }

        // Sin telegram_chat_id no podemos crear topic
        if (! $this->telegram_chat_id) {
            return null;
        }

        // Cargar cliente para el nombre del topic
        $cliente = $this->cliente ?? Cliente::find($this->cliente_id);

        if (! $cliente) {
            return null;
        }

        // Nombre del topic: razón social o nombre del cliente
        $topicName = $cliente->razon_social ?: "Cliente #{$cliente->id}";

        // Prefijo según tipo de cliente
        $tipoNombre = $cliente->tipoCliente?->nombre ?? '';
        $emoji = str_contains(strtolower($tipoNombre), 'autónomo') ? '👤' : '📋';
        $topicName = "{$emoji} {$topicName}";

        try {
            /** @var \App\Services\TelegramService $tg */
            $tg = app(\App\Services\TelegramService::class);

            $threadId = $tg->createForumTopic(
                chatId: $this->telegram_chat_id,
                name: mb_substr($topicName, 0, 128) // Telegram limita a 128 chars
            );

            $this->update(['telegram_thread_id' => $threadId]);

            // Enviar mensaje de bienvenida dentro del topic
            try {
                $tg->sendMessage(
                    $this->telegram_chat_id,
                    "✅ Este es tu hilo de {$topicName}. Escribe aquí para contactar con tu asesor.",
                    $threadId
                );
            } catch (\Throwable $e) {
                // No crítico
            }

            return $threadId;

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('ensureTopicForCliente failed', [
                'chat_conversacion_id' => $this->id,
                'cliente_id'           => $this->cliente_id,
                'telegram_chat_id'     => $this->telegram_chat_id,
                'error'                => $e->getMessage(),
            ]);

            return null;
        }
    }
}