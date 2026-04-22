<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ComisionMensual extends Model
{
    protected $table = 'comisiones_mensuales';

    protected $fillable = [
        'comercial_id',
        'año',
        'mes',
        'regla_id',
        'facturacion_bruta',
        'bajas_mes',
        'facturacion_neta',
        'minimo_aplicable',
        'base_comisionable',
        'porcentaje',
        'importe_comision_calculado',
        'bonos',
        'total_bonos',
        'importe_final',
        'estado',
        'alcanzo_minimo',
        'es_regla_obligatoria',
        'aprobada_por_id',
        'aprobada_at',
        'pagada_at',
        'pagada_referencia',
    ];

    protected $casts = [
        'facturacion_bruta'          => 'decimal:2',
        'bajas_mes'                  => 'decimal:2',
        'facturacion_neta'           => 'decimal:2',
        'minimo_aplicable'           => 'decimal:2',
        'base_comisionable'          => 'decimal:2',
        'porcentaje'                 => 'decimal:2',
        'importe_comision_calculado' => 'decimal:2',
        'bonos'                      => 'array',
        'total_bonos'                => 'decimal:2',
        'importe_final'              => 'decimal:2',
        'alcanzo_minimo'             => 'boolean',
        'es_regla_obligatoria'       => 'boolean',
        'aprobada_at'                => 'datetime',
        'pagada_at'                  => 'datetime',
    ];

    public function comercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comercial_id');
    }

    public function regla(): BelongsTo
    {
        return $this->belongsTo(ComisionRegla::class, 'regla_id');
    }

    public function aprobadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobada_por_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(ComisionDetalle::class, 'comision_mensual_id');
    }
}
