<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMensaje extends Model
{
    protected $table = 'chat_mensajes';

    protected $fillable = [
        'chat_id',
        'origen',
        'tipo',
        'contenido',

        // adjuntos
        'file_path',
        'file_original_name',
        'file_mime',
        'file_size',
        'caption',

        // telegram refs
        'telegram_message_id',
        'telegram_update_id',
        'telegram_file_id',
        'telegram_file_unique_id',

        // meta
        'payload',
        'leido',
        'read_at',

        // salientes
        'estado_envio',
        'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'leido'   => 'boolean',
        'read_at' => 'datetime',
    ];

    public function chat(): BelongsTo
    {
        return $this->belongsTo(ChatConversacion::class, 'chat_id');
    }
}
