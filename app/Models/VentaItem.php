<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute; // Mantener si lo usas en otros accesors
use Illuminate\Database\Eloquent\Relations\HasOne;

class VentaItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'venta_id',
        'servicio_id',
        'cantidad',
        'precio_unitario',          // Precio base del servicio sin descuento, sin IVA
        'subtotal',                 // Subtotal base del servicio (cantidad * precio_unitario), sin descuento, sin IVA
        'subtotal_con_iva', // <<< NUEVO CAMPO AÑADIDO
        'observaciones_item',
        'fecha_inicio_servicio',    // Campo opcional para recurrentes
        'precio_unitario_aplicado', // NUEVO: Precio unitario FINAL aplicado a esta línea (con descuento si existe, sin IVA)
        'subtotal_aplicado',        // NUEVO: Subtotal FINAL de la línea (cantidad * precio_unitario_aplicado), sin IVA
        'descuento_tipo',
        'descuento_valor',
        'descuento_duracion_meses',
        'descuento_valido_hasta',
        'observaciones_descuento',  // Campo para la descripción del descuento
        'subtotal_aplicado_con_iva', // <<< NUEVO CAMPO AÑADIDO
        'requiere_proyecto',
        'bloquea_recurrente',
        'nombre_personalizado',
        'cliente_suscripcion_id',



    ];

    protected $casts = [
        'precio_unitario'          => 'decimal:4', // Mantenemos 4 decimales para mayor precisión
        'subtotal'                 => 'decimal:2', // Subtotal base (cantidad * precio_unitario)
        'subtotal_con_iva'         => 'decimal:2', // <<< NUEVO CAST AÑADIDO
        'fecha_inicio_servicio'    => 'date',     
        'precio_unitario_aplicado' => 'decimal:4',
        'subtotal_aplicado'        => 'decimal:2',
        'descuento_valor'          => 'decimal:2',
        'descuento_duracion_meses' => 'integer',
        'descuento_valido_hasta'   => 'date',
        'subtotal_aplicado_con_iva' => 'decimal:2',
        'requiere_proyecto' => 'boolean',
        'bloquea_recurrente' => 'boolean',
        
    ];

    // Relación muchos-a-uno con Venta (la venta padre)
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    // Relación muchos-a-uno con Servicio (el servicio vendido)
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    // Un VentaItem puede tener un Proyecto asociado
public function proyecto(): HasOne
{
    return $this->hasOne(Proyecto::class);
}

// Un VentaItem puede tener una Suscripción asociada
public function suscripcion(): BelongsTo
{
    return $this->belongsTo(ClienteSuscripcion::class, 'cliente_suscripcion_id');
}



protected static function booted(): void
{
    static::saving(function (VentaItem $ventaItem) {
        // Limpiar campos de descuento si no hay tipo definido
        if (empty($ventaItem->descuento_tipo)) {
            $ventaItem->descuento_valor = null;
            $ventaItem->descuento_duracion_meses = null;
            $ventaItem->descuento_valido_hasta = null;
            $ventaItem->observaciones_descuento = null;
        }

        // Limpiar campos vacíos si son string vacíos
        foreach (['descuento_valor', 'descuento_duracion_meses', 'descuento_valido_hasta', 'observaciones_descuento'] as $campo) {
            if ($ventaItem->{$campo} === '') {
                $ventaItem->{$campo} = null;
            }
        }
        
        // La lógica para calcular 'descuento_valido_hasta' se ha quitado de aquí.
        // Ahora vive en el modelo ClienteSuscripcion.

        // Cálculo de precio aplicado y subtotal (esto se queda como estaba)
        if (!is_null($ventaItem->precio_unitario)) {
            $cantidad = (float)($ventaItem->cantidad ?? 1);
            $precioUnitario = (float)$ventaItem->precio_unitario;
            $subtotal = $cantidad * $precioUnitario;
            $precioFinal = $subtotal;

            if (!empty($ventaItem->descuento_tipo) && is_numeric($ventaItem->descuento_valor)) {
                switch ($ventaItem->descuento_tipo) {
                    case 'porcentaje':
                        $precioFinal = $subtotal - ($subtotal * ($ventaItem->descuento_valor / 100));
                        break;
                    case 'fijo':
                        $precioFinal = $subtotal - $ventaItem->descuento_valor;
                        break;
                    case 'precio_final':
                        $precioFinal = $ventaItem->descuento_valor;
                        break;
                }
            }

            $precioFinal = max(0, $precioFinal);
            $ventaItem->precio_unitario_aplicado = round($precioFinal / max(1, $cantidad), 4);
            $ventaItem->subtotal_aplicado = round($precioFinal, 2);
        }
    });

    // Los otros eventos se quedan igual para que el total de la venta se actualice.
    static::created(function (VentaItem $ventaItem) {
        $ventaItem->venta->updateTotal();
    });

    static::updated(function (VentaItem $ventaItem) {
        if ($ventaItem->isDirty([
            'cantidad', 'precio_unitario', 'precio_unitario_aplicado', 
            'subtotal_aplicado', 'subtotal_aplicado_con_iva', 'descuento_tipo', 
            'descuento_valor', 'descuento_duracion_meses', 'descuento_valido_hasta',
            'observaciones_descuento', 'observaciones_item', 'fecha_inicio_servicio',
        ])) {
            $ventaItem->venta->updateTotal();
        }
    });

    static::deleted(function (VentaItem $ventaItem) {
        $ventaItem->venta->updateTotal();
    });
}
    /**
 * Devuelve el nombre final del servicio, usando el personalizado si existe.
 */
protected function nombreFinal(): Attribute
{
    return Attribute::make(
        get: fn () => $this->nombre_personalizado ?: $this->servicio?->nombre
    );
}


    
}