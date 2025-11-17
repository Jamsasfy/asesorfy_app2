<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAutoEmailLog extends Model
{
    protected $fillable = [
        'lead_id',
        'estado',
        'intento',
        'template_identifier',
        'subject',
        'body_preview',
        'scheduled_at',
        'sent_at',
        'status',
        'mail_driver',
        'provider',
        'provider_message_id',
        'rate_limited',
        'error_code',
        'error_message',
        'triggered_by_user_id',
        'trigger_source',
        'meta',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at'      => 'datetime',
        'rate_limited' => 'boolean',
        'meta'         => 'array',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
