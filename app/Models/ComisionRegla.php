<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ComisionRegla extends Model
{
    protected $table = 'comision_reglas';

    protected $fillable = [
        'nombre',
        'tipo_servicio',
        'servicios_ids',
        'minimo_mensual',
        'porcentaje_comision',
        'penalizacion_baja_antes_meses',
        'activa',
    ];

    protected $casts = [
        'servicios_ids'        => 'array',
        'minimo_mensual'       => 'decimal:2',
        'porcentaje_comision'  => 'decimal:2',
        'activa'               => 'boolean',
    ];

    public function comerciales(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'comercial_reglas', 'regla_id', 'comercial_id')
            ->withPivot('es_obligatoria', 'activa')
            ->withTimestamps();
    }

    public function comisionesMensuales(): HasMany
    {
        return $this->hasMany(ComisionMensual::class, 'regla_id');
    }
}
