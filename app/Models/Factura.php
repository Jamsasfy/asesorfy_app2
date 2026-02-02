<?php

namespace App\Models;

use App\Enums\FacturaEstadoEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;


class Factura extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id', 'venta_id', 'serie', 'numero_factura', 'fecha_emision',
        'fecha_vencimiento', 'estado', 'metodo_pago', 'stripe_invoice_id',
        'stripe_payment_intent_id', 'base_imponible', 'total_iva', 'total_factura',
        'observaciones_publicas', 'observaciones_privadas', 'factura_rectificada_id',
        'motivo_rectificacion',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
        'fecha_vencimiento' => 'date',
        'base_imponible' => 'decimal:2',
        'total_iva' => 'decimal:2',
        'total_factura' => 'decimal:2',
                'estado' => FacturaEstadoEnum::class, // <-- ¡Cambiado a usar el Enum!

    ];

    // Una factura pertenece a un cliente
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    // Una factura tiene muchas líneas o items
    public function items(): HasMany
    {
        return $this->hasMany(FacturaItem::class);
    }
    public function venta(): BelongsTo
        {
            return $this->belongsTo(Venta::class);
        }
  

}