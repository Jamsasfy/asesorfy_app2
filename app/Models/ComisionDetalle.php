<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComisionDetalle extends Model
{
    protected $table = 'comision_detalles';

    public $timestamps = false;

    protected $fillable = [
        'comision_mensual_id',
        'tipo',
        'factura_id',
        'factura_item_id',
        'servicio_id',
        'cliente_suscripcion_id',
        'fecha_baja',
        'meses_activo',
        'comision_original_id',
        'importe',
        'created_at',
    ];

    protected $casts = [
        'importe'    => 'decimal:2',
        'fecha_baja' => 'date',
        'created_at' => 'datetime',
    ];

    public function comisionMensual(): BelongsTo
    {
        return $this->belongsTo(ComisionMensual::class, 'comision_mensual_id');
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function facturaItem(): BelongsTo
    {
        return $this->belongsTo(FacturaItem::class);
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    public function clienteSuscripcion(): BelongsTo
    {
        return $this->belongsTo(ClienteSuscripcion::class);
    }

    public function comisionOriginal(): BelongsTo
    {
        return $this->belongsTo(ComisionMensual::class, 'comision_original_id');
    }
}
