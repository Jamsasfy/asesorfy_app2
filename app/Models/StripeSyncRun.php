<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


class StripeSyncRun extends Model
{
    protected $fillable = [
        'user_id',
        'solo_activas',
        'backfill_invoices',
        'chunk_size',
        'status',
        'total',
        'subs_updated',
        'subs_no_change',
        'skipped_invalid',
        'inv_created',
        'inv_skipped',
        'inv_errors',
        'errors',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'solo_activas' => 'boolean',
        'backfill_invoices' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    
public function logs(): HasMany
{
    return $this->hasMany(\App\Models\StripeSyncRunLog::class, 'stripe_sync_run_id');
}


}
