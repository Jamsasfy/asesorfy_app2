<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContratoResponsabilidad extends Model
{
    protected $table = 'contratos_responsabilidad';

    protected $guarded = [];

    protected $casts = [
        'signed_at' => 'datetime',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function asesor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asesor_id');
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(Documento::class);
    }

    public function esFirmado(): bool
    {
        return !is_null($this->signed_at);
    }
}
