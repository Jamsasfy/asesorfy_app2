<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComercialHistorialObjetivo extends Model
{
    protected $table = 'comercial_historial_objetivos';

    protected $fillable = [
        'comercial_id',
        'año',
        'mes',
        'alcanzo_todos_minimos_obligatorios',
        'total_comisiones_calculado',
        'total_bonos',
        'total_final',
        'estado',
        'datos_adicionales',
        'informe_pdf_path',
        'informe_hash',
        'informe_generado_at',
    ];

    protected $casts = [
        'alcanzo_todos_minimos_obligatorios' => 'boolean',
        'total_comisiones_calculado'         => 'decimal:2',
        'total_bonos'                        => 'decimal:2',
        'total_final'                        => 'decimal:2',
        'datos_adicionales'                  => 'array',
        'informe_generado_at'                => 'datetime',
    ];

    public function comercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comercial_id');
    }
}
