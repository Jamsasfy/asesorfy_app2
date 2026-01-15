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
}
