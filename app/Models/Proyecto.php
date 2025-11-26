<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany; // Para comentarios morfológicos
use App\Enums\ProyectoEstadoEnum; // Si defines un Enum para los estados del proyecto
use Illuminate\Database\Eloquent\Casts\Attribute; // Asegúrate de importar Attribute
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class Proyecto extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'cliente_id',
        'venta_id',
        'servicio_id', // Si vinculas a Servicio recurrente genérico
        'venta_item_id', // Si vinculas al VentaItem específico
        'user_id', // Asesor asignado
        'estado',
        'agenda',
        'fecha_finalizacion',
        'descripcion',
         'llamadas',
        'emails',
        'chats',
        'otros_acciones',
        'lead_id', // <--- ¡IMPORTANTE!
    ];

    protected $casts = [
        'agenda' => 'datetime',
        'fecha_finalizacion' => 'datetime', // 'datetime' para guardar hora también
        'estado' => ProyectoEstadoEnum::class, // Si usas un Enum para estados
         'llamadas' => 'integer',
        'emails' => 'integer',
        'chats' => 'integer',
        'otros_acciones' => 'integer',
    ];

    protected $attributes = [
    'agenda' => null,
];



    // --- Relaciones ---
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
    

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    public function ventaItem(): BelongsTo
    {
        return $this->belongsTo(VentaItem::class);
    }

    public function user(): BelongsTo // El usuario asignado al proyecto
    {
        return $this->belongsTo(User::class);
    }

    // --- Relación Morfológica con Comentarios ---
     public function comentarios(): MorphMany
{
    return $this->morphMany(Comentario::class, 'comentable')->latest();
}
     protected function totalInteracciones(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->llamadas + $this->emails + $this->chats + $this->otros_acciones,
        );
    }

    public function clienteSuscripcion(): HasOneThrough
{
    return $this->hasOneThrough(
        \App\Models\ClienteSuscripcion::class,    // Modelo final
        \App\Models\VentaItem::class,             // Modelo intermedio
        'id',                                     // Clave local en VentaItem (intermedia)
        'id',                                     // Clave local en ClienteSuscripcion
        'venta_item_id',                          // Foreign key en Proyecto
        'cliente_suscripcion_id'                  // Foreign key en VentaItem (hacia ClienteSuscripcion)
    );
}



    // app/Models/Proyecto.php

        public function documentosPolimorficos()
        {
            return $this->morphMany(\App\Models\Documento::class, 'documentable');
        }


    // --- Hooks --- 
    protected static function booted(): void
    {
        static::updating(function (Proyecto $proyecto) {
            if ($proyecto->isDirty('estado') && $proyecto->estado->value === ProyectoEstadoEnum::Finalizado->value && is_null($proyecto->fecha_finalizacion)) {
                $proyecto->fecha_finalizacion = now();
            }
        });

        static::updated(function (Proyecto $proyecto) {
            // Si el proyecto acaba de ser finalizado, notificar a la Venta para re-evaluar suscripciones
            if ($proyecto->wasChanged('estado') && $proyecto->estado->value === ProyectoEstadoEnum::Finalizado->value) {
                // Solo si el proyecto está vinculado a una venta
                if ($proyecto->venta) {
                    $proyecto->venta->checkAndActivateSubscriptions(); // <<< NUEVO MÉTODO en Venta
                }
            }
        });

         static::deleting(function (Proyecto $proyecto) {
        // Eliminar comentarios relacionados
        $proyecto->comentarios()->delete();

        // Eliminar documentos relacionados
        $proyecto->documentosPolimorficos()->each(function ($documento) {
            $documento->delete(); // Esto dispara el delete del documento (incluye eliminación física si corresponde)
        });
    });

    
    }
}