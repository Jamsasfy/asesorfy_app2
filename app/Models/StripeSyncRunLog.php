<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StripeSyncRunLog extends Model
{
    protected $table = 'stripe_sync_run_logs';

    protected $fillable = [
        'stripe_sync_run_id',
        'level',
        'cliente_suscripcion_id',
        'stripe_subscription_id',
        'message',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(StripeSyncRun::class, 'stripe_sync_run_id');
    }

    public function suscripcion(): BelongsTo
    {
        return $this->belongsTo(ClienteSuscripcion::class, 'cliente_suscripcion_id');
    }
}
