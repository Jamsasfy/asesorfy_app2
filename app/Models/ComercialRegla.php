<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComercialRegla extends Pivot
{
    protected $table = 'comercial_reglas';

    public $incrementing = true;

    protected $fillable = [
        'comercial_id',
        'regla_id',
        'es_obligatoria',
        'activa',
    ];

    protected $casts = [
        'es_obligatoria' => 'boolean',
        'activa'         => 'boolean',
    ];

    public function comercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comercial_id');
    }

    public function regla(): BelongsTo
    {
        return $this->belongsTo(ComisionRegla::class, 'regla_id');
    }
}
