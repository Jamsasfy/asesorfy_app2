<?php

namespace App\Models;

use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\ProyectoEstadoEnum;
use App\Enums\ServicioTipoEnum;
use App\Enums\VentaCorreccionEstadoEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute; // Si usas accessors/mutators
use App\Models\Cliente;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use App\Filament\Resources\ProyectoResource;
use App\Enums\VentaEstadoEnum;
use App\Enums\FacturaEstadoEnum; // si no lo tenías ya
use Illuminate\Support\Carbon;


class Venta extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'lead_id',
        'user_id', // El comercial
        'fecha_venta',
        'importe_total', // Lo calcularemos automáticamente, pero debe ser fillable
        'observaciones',
        'correccion_estado',
        'correccion_solicitada_at',
        'correccion_solicitada_por_id',
        'correccion_motivo',
        'signed_at',
    ];

    protected $casts = [
        'fecha_venta' => 'datetime',
        'importe_total' => 'decimal:2', // Asegura que se maneja como decimal
        'correccion_estado' => VentaCorreccionEstadoEnum::class,
        'correccion_solicitada_at' => 'datetime',
        'signed_at' => 'datetime',
        'estado'              => VentaEstadoEnum::class,     
        'confirmada_at'       => 'datetime',
        'requiere_pago_inicial' => 'boolean',
    ];

    protected $appends = ['tipo_venta'];


    // Relación muchos-a-uno con Cliente
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    // Relación muchos-a-uno con Lead (nullable)
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function solicitanteCorreccion(): BelongsTo
{
    return $this->belongsTo(User::class, 'correccion_solicitada_por_id');
}

    // Relación muchos-a-uno con User (el comercial), nombrada 'comercial'
    public function comercial(): BelongsTo // <-- Nombre del método cambiado a 'comercial'
    {
        return $this->belongsTo(User::class, 'user_id'); // <-- Sigue relacionándose con el modelo User
    }

    // Relación uno-a-muchos con VentaItems (los items de la venta)
    public function items(): HasMany
    {
        return $this->hasMany(VentaItem::class);
    }

     // Relación de Venta con Proyectos (nueva)
    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class);
    }
 public function suscripciones(): HasMany
{
    return $this->hasMany(ClienteSuscripcion::class, 'venta_origen_id');
}

public function facturas(): HasMany
{
    return $this->hasMany(Factura::class);
}



    // Opcional: Accessor para obtener el tipo de venta (puntual, recurrente, mixta)
    // basado en los tipos de servicios de sus items.
    // Esto demuestra cómo obtener la información sin tener la columna 'tipo_venta'.
    protected function tipoVenta(): Attribute
    {
         return Attribute::make(
             get: function (mixed $value, array $attributes) {
                $itemTypes = $this->items->pluck('servicio.tipo')->unique();

                if ($itemTypes->contains('recurrente') && $itemTypes->contains('unico')) {
                    return 'mixta';
                } elseif ($itemTypes->contains('recurrente')) {
                    return 'recurrente';
                } elseif ($itemTypes->contains('unico')) {
                    return 'puntual';
                }
                return null; // O 'desconocido' si no hay items
             },
         );
    }

      /**
     * Método para recalcular y guardar el importe total en la Venta.
     */
    public function updateTotal(): void
    {
        // Asegura que la relación 'items' esté cargada para sumar
        $this->loadMissing('items'); 
        
        // Suma el campo 'subtotal_aplicado' de cada item, que ya tiene los descuentos.
        $newTotal = $this->items->sum('subtotal_aplicado'); 

        $this->importe_total = $newTotal;

        // Usamos saveQuietly() para guardar el cambio sin disparar más eventos 'updated'
        // y así evitar posibles bucles infinitos.
        $this->saveQuietly();
    }
    /**
     * Este método es llamado por el modelo Proyecto cuando se finaliza.
    
     */
   public function checkAndActivateSubscriptions(): void
{
    // Obtener los items de esta venta cuyo SERVICIO sea tarifa principal y recurrente
    $ventaItems = $this->items()
        ->whereHas('servicio', function (Builder $query) {
            $query->where('tipo', \App\Enums\ServicioTipoEnum::RECURRENTE)
                  ->where('es_tarifa_principal', true); // <-- Condición movida aquí dentro
        })
        ->get();

    // El resto de la lógica no necesita cambios...
    foreach ($ventaItems as $item) {
        $suscripcion = ClienteSuscripcion::where('cliente_id', $this->cliente_id)
            ->where('servicio_id', $item->servicio_id)
            ->where('venta_origen_id', $this->id)
            ->where('estado', \App\Enums\ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION)
            ->first();

        if (! $suscripcion) {
            continue;
        }

        $proyectosIncompletos = $this->proyectos()
            ->whereNot('estado', \App\Enums\ProyectoEstadoEnum::Finalizado)
            ->exists();

        if (! $proyectosIncompletos) {
            $suscripcion->estado = \App\Enums\ClienteSuscripcionEstadoEnum::ACTIVA;
            $suscripcion->fecha_inicio = now();
            $suscripcion->save();
        }
    }
}  

protected function importeBaseSinDescuento(): Attribute
{
    return Attribute::make(
        // Suma el campo 'subtotal' de cada item, que es el precio original sin descuento
        get: fn (): float => $this->items->sum('subtotal')
    );
}
/**
 * Calcula el importe total de la venta CON IVA.
 */
protected function importeTotalConIva(): Attribute
{
    return Attribute::make(
        get: function (): float {
            // Usamos un IVA del 21% por defecto.
            // Puedes cambiarlo o hacerlo dinámico si es necesario.
            $iva = 1.21;
            return round($this->importe_total * $iva, 2);
        }
    );
}

/**
     * Orquesta la creación de proyectos y suscripciones después de que una venta
     * se haya guardado completamente (incluyendo sus items).
     */
/**
     * Crea los proyectos y suscripciones tras cerrar la venta.
     * Recibe $extraData (los datos del formulario) para enriquecer el proyecto.
     */
   public function processSaleAfterCreation(array $extraData = []): void
    {
        $this->loadMissing('items.servicio', 'cliente');

        $ventaRequiereProyecto = $this->items->contains(function ($item) {
            if (!$item->servicio) return false;
            return $item->servicio->es_editable
                ? $item->requiere_proyecto
                : $item->servicio->requiere_proyecto_activacion;
        });

        foreach ($this->items as $item) {
            $servicio = $item->servicio;
            if (!$servicio) continue;

            // --- CREAR PROYECTO ---
            $debeCrearProyecto = $servicio->es_editable
                ? $item->requiere_proyecto
                : $servicio->requiere_proyecto_activacion;

            if ($debeCrearProyecto) {
                $nombreDelProyecto = $item->nombre_personalizado ?: $servicio->nombre;
                $nombreSvcLower = strtolower($servicio->nombre);
                
                $descripcion = "Proyecto generado por la venta #{$this->id}.";

              // 1. DATOS CONSTITUCIÓN S.L. (CORREGIDO: 5 NOMBRES)
                if (str_contains($nombreSvcLower, 'sociedad') || str_contains($nombreSvcLower, 'sl') || str_contains($nombreSvcLower, 'mercantil')) {
                    if (!empty($extraData['extra_sl_nombre1'])) {
                        $descripcion .= "\n\n=== DATOS CONSTITUCIÓN SL ===\n";
                        $descripcion .= "• Denominaciones (Preferencia):\n";
                        $descripcion .= "   1. " . ($extraData['extra_sl_nombre1'] ?? '-') . "\n";
                        $descripcion .= "   2. " . ($extraData['extra_sl_nombre2'] ?? '-') . "\n";
                        $descripcion .= "   3. " . ($extraData['extra_sl_nombre3'] ?? '-') . "\n";
                        $descripcion .= "   4. " . ($extraData['extra_sl_nombre4'] ?? '-') . "\n"; // <--- AÑADIDO
                        $descripcion .= "   5. " . ($extraData['extra_sl_nombre5'] ?? '-') . "\n"; // <--- AÑADIDO
                        
                        $descripcion .= "• Capital: " . number_format((float)($extraData['extra_sl_capital'] ?? 0), 2) . " €\n";
                        $descripcion .= "• Actividad: {$extraData['extra_sl_actividad']}\n";
                        
                        $descripcion .= "\n• Estructura:\n";
                        $descripcion .= "   Admin: " . ucfirst($extraData['extra_sl_tipo_admin'] ?? '-') . " (" . ($extraData['extra_sl_admin_nombre'] ?? '-') . ")\n";
                        $descripcion .= "   Socios: " . ($extraData['extra_sl_socios'] ?? '-') . "\n";
                        $descripcion .= "   Separación Bienes: " . strtoupper($extraData['extra_sl_separacion_bienes'] ?? 'NO') . "\n";
                        
                        $domicilio = !empty($extraData['extra_sl_domicilio_social']) ? $extraData['extra_sl_domicilio_social'] : 'Mismo que domicilio personal';
                        $descripcion .= "• Domicilio Social: {$domicilio}\n";
                        $descripcion .= "• Firma en: " . ($extraData['extra_sl_ciudad_firma'] ?? '-');
                    }
                }

                // 2. DATOS CAPITALIZACIÓN
                if (str_contains($nombreSvcLower, 'capitaliza') || str_contains($nombreSvcLower, 'pago único')) {
                    if (!empty($extraData['extra_cap_forma_juridica'])) {
                        $descripcion .= "\n\n=== DATOS CAPITALIZACIÓN ===\n";
                        $descripcion .= "• Destino: " . strtoupper($extraData['extra_cap_forma_juridica']) . "\n";
                        $descripcion .= "• Modalidad: " . ucfirst(str_replace('_', ' ', $extraData['extra_cap_modalidad'] ?? '-')) . "\n";
                        $inv = $extraData['extra_cap_inversion'] ?? 'No indicado';
                        $sol = $extraData['extra_cap_solicitado'] ?? 'No indicado';
                        $descripcion .= "• Inversión: {$inv} € | Solicita: {$sol} €\n";
                        $descripcion .= "\n[DESEMPLEO]\n";
                        $descripcion .= "• Desde: " . ($extraData['extra_cap_fecha_paro'] ?? '-') . "\n";
                        $descripcion .= "• Percibe: " . ($extraData['extra_cap_prestacion_mensual'] ?? '-') . "\n";
                        $descripcion .= "• Queda: " . ($extraData['extra_cap_duracion_paro'] ?? '-') . "\n";
                        if (!empty($extraData['extra_cap_memoria'])) $descripcion .= "\n[MEMORIA]\n{$extraData['extra_cap_memoria']}";
                    }
                }

                // 3. DATOS ALTA AUTÓNOMO
                if (str_contains($nombreSvcLower, 'alta') && str_contains($nombreSvcLower, 'autónomo')) {
                    if (!empty($extraData['extra_auto_fecha_inicio'])) {
                        $descripcion .= "\n\n=== DATOS ALTA AUTÓNOMO ===\n";
                        $descripcion .= "• Inicio: {$extraData['extra_auto_fecha_inicio']}\n";
                        $descripcion .= "• Actividad: {$extraData['extra_auto_actividad']}\n";
                        $descripcion .= "• Lugar: " . strtoupper($extraData['extra_auto_lugar'] ?? '-') . "\n";
                        if (($extraData['extra_auto_lugar'] ?? '') === 'local') $descripcion .= "   (Dir: {$extraData['extra_auto_direccion_local']})\n";
                        $descripcion .= "• Tarifa Plana: " . strtoupper($extraData['extra_auto_tarifa_plana'] ?? '-') . "\n";
                    }
                }

                // CREAR EL PROYECTO
                $proyecto = \App\Models\Proyecto::create([
                    'nombre'          => "{$nombreDelProyecto} ({$this->cliente->razon_social})",
                    'cliente_id'      => $this->cliente_id,
                    'venta_id'        => $this->id,
                    'lead_id'         => $this->lead_id, // <--- Aquí vinculamos el Proyecto al Lead
                    'venta_item_id'   => $item->id,
                    'servicio_id'     => $servicio->id,
                    'estado'          => \App\Enums\ProyectoEstadoEnum::Pendiente,
                    'descripcion'     => $descripcion, 
                ]);

                // Notificar coordinadores
                $coordinadores = \App\Models\User::whereHas('roles', fn ($q) => $q->where('name', 'coordinador'))->get();
                if ($coordinadores->isNotEmpty()) {
                    \Filament\Notifications\Notification::make()
                        ->title('Nuevo Proyecto Pendiente')
                        ->body("Proyecto: {$proyecto->nombre}")
                        ->actions([\Filament\Notifications\Actions\Action::make('view')->label('Ver')->url(\App\Filament\Resources\ProyectoResource::getUrl('view', ['record' => $proyecto]))])
                        ->sendToDatabase($coordinadores);
                }
            }

            // --- CREAR SUSCRIPCIÓN ---
            $estadoInicial = null;
            $fechaInicio = null;

            if ($servicio->tipo === \App\Enums\ServicioTipoEnum::UNICO) {
                $estadoInicial = \App\Enums\ClienteSuscripcionEstadoEnum::ACTIVA;
                $fechaInicio = now();
            } elseif ($servicio->tipo === \App\Enums\ServicioTipoEnum::RECURRENTE) {
                $estadoInicial = $ventaRequiereProyecto 
                    ? \App\Enums\ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION 
                    : \App\Enums\ClienteSuscripcionEstadoEnum::ACTIVA;
                $fechaInicio = $ventaRequiereProyecto ? null : ($item->fecha_inicio_servicio ?? now());
            }

            if ($estadoInicial) {
                $suscripcion = \App\Models\ClienteSuscripcion::create([
                    'cliente_id'             => $this->cliente_id,
                    'servicio_id'            => $item->servicio_id,
                    'venta_origen_id'        => $this->id,
                    'nombre_personalizado'   => $item->nombre_personalizado,
                    'es_tarifa_principal'    => $servicio->es_tarifa_principal,
                    'precio_acordado'        => $item->subtotal_aplicado,
                    'cantidad'               => $item->cantidad,
                    'fecha_inicio'           => $fechaInicio,
                    'estado'                 => $estadoInicial,
                    'ciclo_facturacion'      => $servicio->ciclo_facturacion,
                    'descuento_tipo'           => $item->descuento_tipo,
                    'descuento_valor'          => $item->descuento_valor,
                    'descuento_duracion_meses' => $item->descuento_duracion_meses,
                    'descuento_descripcion'    => $item->observaciones_descuento,
                    'descuento_valido_hasta'   => $item->descuento_valido_hasta,
                    'observaciones'            => $item->observaciones_item,
                ]);
                $item->cliente_suscripcion_id = $suscripcion->id;
                $item->save();
            }
        }
    }
    public function esVentaReal(): bool
    {
        return $this->estado === VentaEstadoEnum::COMPLETADA;
    }
    public function tienePagoInicialCompletado(): bool
    {
        // De momento: considerar "pago inicial completado" si existe alguna factura pagada
        return $this->facturas()
            ->where('estado', FacturaEstadoEnum::PAGADA)
            ->exists();
    }
    public function scopeCompletadas($query)
    {
        return $query->where('estado', VentaEstadoEnum::COMPLETADA);
    }

        /**
     * Devuelve true si la venta tiene algún servicio ÚNICO.
     * Es decir, requiere un pago inicial para considerarla cerrada.
     */
    public function requierePagoInicial(): bool
    {
        return $this->items()
            ->whereHas('servicio', function ($q) {
                $q->where('tipo', ServicioTipoEnum::UNICO->value);
            })
            ->exists();
    }

    /**
     * Marca la venta como COMPLETADA y fija la fecha de cierre.
     */
    public function marcarComoCompletada(?Carbon $fecha = null): void
    {
        $fecha = $fecha ?? now();

        // Si ya está completada, solo nos aseguramos de que tenga confirmada_at
        if ($this->estado === VentaEstadoEnum::COMPLETADA) {
            if (! $this->confirmada_at) {
                $this->forceFill([
                    'confirmada_at' => $fecha,
                ])->save();
            }

            return;
        }

        $this->forceFill([
            'estado'        => VentaEstadoEnum::COMPLETADA,
            'confirmada_at' => $fecha,
        ])->save();
    }

    /**
     * Lógica común tras la firma del contrato:
     * - Solo recurrentes  → venta COMPLETADA.
     * - Con servicios únicos → venta PENDIENTE hasta que se pague la factura.
     */
    public function prepararEstadoTrasFirma(Carbon $fechaFirma): void
    {
        if ($this->requierePagoInicial()) {
            // Venta firmada pero aún pendiente porque falta el pago inicial
            $this->forceFill([
                'estado'        => VentaEstadoEnum::PENDIENTE,
                'confirmada_at' => null,
            ])->save();
        } else {
            // Solo recurrentes → ya consideramos la venta cerrada
            $this->marcarComoCompletada($fechaFirma);
        }
    }




}