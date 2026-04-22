<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComercialAlerta extends Model
{
    protected $table = 'comercial_alertas';

    protected $fillable = [
        'comercial_id',
        'año',
        'mes',
        'tipo',
        'regla_id',
        'facturacion_neta',
        'minimo_requerido',
        'importe_comision',
        'email_enviado_at',
        'plantilla_codigo',
        'destinatarios',
    ];

    protected $casts = [
        'facturacion_neta'  => 'decimal:2',
        'minimo_requerido'  => 'decimal:2',
        'importe_comision'  => 'decimal:2',
        'email_enviado_at'  => 'datetime',
        'destinatarios'     => 'array',
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
