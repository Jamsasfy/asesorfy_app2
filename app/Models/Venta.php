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
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use App\Filament\Resources\ProyectoResource;
use App\Enums\VentaEstadoEnum;
use App\Enums\FacturaEstadoEnum;
use Illuminate\Support\Carbon;
use App\Services\FacturacionService;
use App\Services\StripeSubscriptionService; // 🔥 EL ÚNICO SERVICIO NECESARIO
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeClientMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class Venta extends Model
{
    use HasFactory;

    protected $fillable = [
        'cliente_id',
        'lead_id',
        'user_id',
        'fecha_venta',
        'importe_total',
        'observaciones',
        'correccion_estado',
        'correccion_solicitada_at',
        'correccion_solicitada_por_id',
        'correccion_motivo',
        'signed_at',
        'estado',
        'confirmada_at',
        'pago_inicial_metodo',
        'pago_inicial_notas',
        'pago_inicial_referencia',
        'pago_inicial_fecha',
    ];

    protected $casts = [
        'fecha_venta'              => 'datetime',
        'importe_total'            => 'decimal:2',
        'correccion_estado'        => VentaCorreccionEstadoEnum::class,
        'correccion_solicitada_at' => 'datetime',
        'signed_at'                => 'datetime',
        'estado'                   => VentaEstadoEnum::class,
        'confirmada_at'            => 'datetime',
        'pago_inicial_fecha'       => 'datetime',
        'requiere_pago_inicial'    => 'boolean',
    ];

    protected $appends = ['tipo_venta'];

    // --- RELACIONES ---
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function solicitanteCorreccion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'correccion_solicitada_por_id');
    }

    public function comercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VentaItem::class);
    }

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

    // --- ATRIBUTOS ---
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
                return null;
            },
        );
    }

    public function updateTotal(): void
    {
        $this->loadMissing('items');
        $newTotal = $this->items->sum('subtotal_aplicado');
        $this->importe_total = $newTotal;
        $this->saveQuietly();
    }

    // --- LÓGICA ANTIGUA (MANTENIDA POR COMPATIBILIDAD) ---
    public function checkAndActivateSubscriptions(): void
    {
        $ventaItems = $this->items()
            ->whereHas('servicio', function (Builder $query) {
                $query->where('tipo', ServicioTipoEnum::RECURRENTE)
                      ->where('es_tarifa_principal', true);
            })
            ->get();

        foreach ($ventaItems as $item) {
            $suscripcion = ClienteSuscripcion::where('cliente_id', $this->cliente_id)
                ->where('servicio_id', $item->servicio_id)
                ->where('venta_origen_id', $this->id)
                ->where('estado', ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION)
                ->first();

            if (! $suscripcion) continue;

            $proyectosIncompletos = $this->proyectos()
                ->whereNot('estado', ProyectoEstadoEnum::Finalizado)
                ->exists();

            if (! $proyectosIncompletos) {
                // Activación si se reactiva por este método legacy
                $suscripcion->estado = ClienteSuscripcionEstadoEnum::ACTIVA;
                $suscripcion->fecha_inicio = now();
                $suscripcion->save();
                
                try {
                    StripeSubscriptionService::activarSuscripcion($suscripcion);
                } catch (\Throwable $e) {}
            }
        }
    }

    protected function importeBaseSinDescuento(): Attribute
    {
        return Attribute::make(
            get: fn (): float => $this->items->sum('subtotal')
        );
    }

    protected function importeTotalConIva(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                $iva = 1.21; // Solo visual/debug
                return round($this->importe_total * $iva, 2);
            }
        );
    }

    // --- PROCESO POST-FIRMA (CREACIÓN ESTRUCTURAS) ---
    public function processSaleAfterCreation(array $extraData = []): void
    {
        $this->loadMissing('items.servicio', 'cliente');

        $normalize = function ($text) {
            $text = strtolower($text);
            $text = str_replace(['á','é','í','ó','ú','ñ'], ['a','e','i','o','u','n'], $text);
            return $text;
        };

        // Detectar si ALGÚN servicio requiere proyecto
        $ventaRequiereProyecto = $this->items->contains(function ($item) {
            if (!$item->servicio) return false;
            return $item->servicio->es_editable
                ? $item->requiere_proyecto
                : $item->servicio->requiere_proyecto_activacion;
        });

        // ---------------------------------------------------------
        // 1. PREPARAR DATOS DEL FORMULARIO (Mapeo)
        // ---------------------------------------------------------
        // ... (Tu código de mapeo de formularios se mantiene igual) ...
        // (Resumido para no ocupar espacio innecesario, pero DEBES mantenerlo si copias el archivo)
        // Mantenemos la lógica de mapeo tal cual la tenías en tu código original.
        
        // A) Alta Autónomo
        $altaAutonomo = array_filter([
            'Fecha Inicio'       => $extraData['extra_auto_fecha_inicio'] ?? null,
            'Fecha Nacimiento'   => $extraData['fecha_nacimiento'] ?? null,
            'Nº Seg. Social'     => $extraData['seguridad_social'] ?? null,
            'Certificado Digital'=> isset($extraData['extra_auto_certificado_digital']) ? ($extraData['extra_auto_certificado_digital'] ? 'Sí' : 'No') : null,
            'Actividad (IAE)'    => $extraData['extra_auto_actividad'] ?? null,
            'Lugar Trabajo'      => $extraData['extra_auto_lugar'] ?? null,
            'Dirección Local'    => $extraData['extra_auto_direccion_local'] ?? null,
            'Tarifa Plana'       => isset($extraData['extra_auto_tarifa_plana']) ? ($extraData['extra_auto_tarifa_plana'] ? 'Sí' : 'No') : null,
        ]);

        // B) Capitalización Paro
        $capitalizacion = array_filter([
            'Forma Jurídica'     => $extraData['extra_cap_forma_juridica'] ?? null,
            'Inversión Prevista' => $extraData['extra_cap_inversion'] ?? null,
            'Importe Solicitado' => $extraData['extra_cap_solicitado'] ?? null,
            'Modalidad'          => $extraData['extra_cap_modalidad'] ?? null,
            'Memoria Explicativa'=> $extraData['extra_cap_memoria'] ?? null,
            'Fecha Paro'         => $extraData['extra_cap_fecha_paro'] ?? null,
            'Prestación Mensual' => $extraData['extra_cap_prestacion_mensual'] ?? null,
            'Duración Paro'      => $extraData['extra_cap_duracion_paro'] ?? null,
            'Oficina SEPE'       => $extraData['extra_cap_oficina_sepe'] ?? null,
        ]);

        // C) Constitución SL
        $crearSL = [
            'Nombres Propuestos' => array_filter([
                $extraData['extra_sl_nombre1'] ?? null,
                $extraData['extra_sl_nombre2'] ?? null,
                $extraData['extra_sl_nombre3'] ?? null,
                $extraData['extra_sl_nombre4'] ?? null,
                $extraData['extra_sl_nombre5'] ?? null,
            ]),
            'Tipo Aportación'    => $extraData['extra_sl_aportacion_tipo'] ?? null,
            'Capital Social'     => $extraData['extra_sl_capital'] ?? null,
            'Descripción Bienes' => $extraData['extra_sl_bienes_descripcion'] ?? null,
            'Actividad'          => $extraData['extra_sl_actividad'] ?? null,
            'Socios'             => $extraData['extra_sl_socios_nombre'] ?? [], 
            'Tipo Admin'         => $extraData['extra_sl_tipo_admin'] ?? null,
            'Admin Nombre'       => $extraData['extra_sl_admin_nombre'] ?? null,
            'Ciudad Firma'       => $extraData['extra_sl_ciudad_firma'] ?? null,
        ];

        // ---------------------------------------------------------
        // 2. CREAR PROYECTOS
        // ---------------------------------------------------------
        foreach ($this->items as $item) {
            $servicio = $item->servicio;
            if (!$servicio) continue;

            $debeCrearProyecto = $servicio->es_editable
                ? $item->requiere_proyecto
                : $servicio->requiere_proyecto_activacion;

            if (!$debeCrearProyecto) continue;
            if (\App\Models\Proyecto::where('venta_item_id', $item->id)->exists()) continue;

            $nombreProyecto = $item->nombre_personalizado ?: $servicio->nombre;
            $nombreNorm = $normalize($nombreProyecto);
            
            $descripcion = "Proyecto generado por la venta #{$this->id}.";
            $detalles = [];

            if (str_contains($nombreNorm, 'autonomo') || str_contains($nombreNorm, 'alta')) {
                foreach ($altaAutonomo as $k => $v) $detalles[] = "- $k: $v";
            } 
            elseif (str_contains($nombreNorm, 'capitaliz') || str_contains($nombreNorm, 'paro')) {
                foreach ($capitalizacion as $k => $v) $detalles[] = "- $k: $v";
            }
            elseif (str_contains($nombreNorm, 'sociedad') || str_contains($nombreNorm, 'sl') || str_contains($nombreNorm, 'constitu')) {
                if (!empty($crearSL['Nombres Propuestos'])) {
                    $detalles[] = "- Nombres: " . implode(', ', $crearSL['Nombres Propuestos']);
                }
                if ($crearSL['Capital Social']) $detalles[] = "- Capital: {$crearSL['Capital Social']} € ({$crearSL['Tipo Aportación']})";
                if ($crearSL['Actividad']) $detalles[] = "- Actividad: {$crearSL['Actividad']}";
                if ($crearSL['Tipo Admin']) $detalles[] = "- Administración: {$crearSL['Tipo Admin']} (" . ($crearSL['Admin Nombre'] ?? '-') . ")";
                
                if (!empty($crearSL['Socios'])) {
                    $detalles[] = "- Socios:";
                    foreach ($crearSL['Socios'] as $idx => $socioNombre) {
                        $pct = $extraData['extra_sl_socios_porcentaje'][$idx] ?? '?';
                        $detalles[] = "  * $socioNombre ($pct%)";
                    }
                }
            }

            if (!empty($detalles)) {
                $descripcion .= "\n\nDatos del Formulario:\n" . implode("\n", $detalles);
            }

            \App\Models\Proyecto::create([
                'nombre'        => "{$nombreProyecto} ({$this->cliente->razon_social})",
                'cliente_id'    => $this->cliente_id,
                'venta_id'      => $this->id,
                'lead_id'       => $this->lead_id,
                'venta_item_id' => $item->id,
                'servicio_id'   => $servicio->id,
                'estado'        => \App\Enums\ProyectoEstadoEnum::Pendiente,
                'descripcion'   => $descripcion,
                'user_id'       => null,
            ]);
        }

        // ---------------------------------------------------------
        // 3. CREAR SUSCRIPCIONES (Lógica Inicial: PENDIENTE_ACTIVACION)
        // ---------------------------------------------------------
        foreach ($this->items as $item) {
            $servicio = $item->servicio;
            if (!$servicio) continue;

            $tipoServicio = $servicio->tipo instanceof \BackedEnum ? $servicio->tipo->value : $servicio->tipo;
            if ($tipoServicio !== \App\Enums\ServicioTipoEnum::RECURRENTE->value) continue;

            if (\App\Models\ClienteSuscripcion::where('venta_origen_id', $this->id)
                ->where('servicio_id', $item->servicio_id)
                ->exists()) {
                continue;
            }

            // Estado inicial: Si hay proyecto -> Pendiente. Si no -> Activa (se confirmará en el cobro)
            $estadoInicial = $ventaRequiereProyecto
                ? \App\Enums\ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION
                : \App\Enums\ClienteSuscripcionEstadoEnum::ACTIVA;

            $fechaInicio = $ventaRequiereProyecto ? null : ($item->fecha_inicio_servicio ?? now());

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
                'descuento_tipo'         => $item->descuento_tipo,
                'descuento_valor'        => $item->descuento_valor,
                'descuento_duracion_meses' => $item->descuento_duracion_meses,
                'descuento_descripcion'  => $item->observaciones_descuento,
                'descuento_valido_hasta' => $item->descuento_valido_hasta,
                'observaciones'          => $item->observaciones_item,
            ]);

            $item->cliente_suscripcion_id = $suscripcion->id;
            $item->save();
        }
    }

    // --- MÉTODOS DE ESTADO ---
    public function esVentaReal(): bool
    {
        return $this->estado === VentaEstadoEnum::COMPLETADA;
    }

    public function tienePagoInicialCompletado(): bool
    {
        return $this->facturas()
            ->where('estado', FacturaEstadoEnum::PAGADA)
            ->exists();
    }

    public function scopeCompletadas($query)
    {
        return $query->where('estado', VentaEstadoEnum::COMPLETADA);
    }

    public function requierePagoInicial(): bool
    {
        return $this->items()
            ->whereHas('servicio', function ($q) {
                $q->where('tipo', ServicioTipoEnum::UNICO->value);
            })
            ->exists();
    }

    public function marcarComoCompletada(?Carbon $fecha = null): void
    {
        $fecha = $fecha ?? now();

        if ($this->estado === VentaEstadoEnum::COMPLETADA) {
            if (! $this->confirmada_at) {
                $this->forceFill(['confirmada_at' => $fecha])->save();
            }
            return;
        }

        $this->forceFill([
            'estado'        => VentaEstadoEnum::COMPLETADA,
            'confirmada_at' => $fecha,
        ])->save();
    }

    public function prepararEstadoTrasFirma(Carbon $fechaFirma): void
    {
        if ($this->requierePagoInicial()) {
            $this->forceFill([
                'estado'        => VentaEstadoEnum::PENDIENTE,
                'confirmada_at' => null,
            ])->save();
        } else {
            $this->marcarComoCompletada($fechaFirma);
        }
    }

    /**
     * ✅ MÉTODO CORREGIDO Y UNIFICADO
     * Procesa el cobro inicial y activa suscripciones usando el servicio unificado.
     */
    public function procesarCobroInicial(
        \Carbon\Carbon $fechaPago,
        string $metodoPago = 'suscripcion_directa',
        ?string $paymentIntentId = null,
        array $extraData = []
    ): void {

        Log::info('🔵 procesarCobroInicial INICIO', ['venta_id' => $this->id]);

        $this->loadMissing('cliente', 'items.servicio', 'suscripciones');
        $cliente = $this->cliente;

        if (!$cliente) {
            throw new \Exception('Venta sin cliente asociado');
        }

        // 1. Crear suscripciones locales pendientes si no existen (Lógica de seguridad)
        foreach ($this->items as $item) {
            if (!$item->servicio) continue;

            $tipo = $item->servicio->tipo instanceof \BackedEnum
                ? $item->servicio->tipo->value
                : $item->servicio->tipo;

            if ($tipo !== 'recurrente') continue;

            // Si no tiene suscripción vinculada, la creamos
            if (!$item->cliente_suscripcion_id) {
                
                // Doble check para no duplicar por error
                $existe = $this->suscripciones()
                    ->where('servicio_id', $item->servicio_id)
                    ->exists();

                if ($existe) continue;

                $suscripcion = $this->suscripciones()->create([
                    'cliente_id'           => $cliente->id,
                    'servicio_id'          => $item->servicio_id,
                    'nombre_personalizado' => $item->nombre_personalizado,
                    'precio_acordado'      => $item->subtotal_aplicado,
                    'cantidad'             => $item->cantidad,
                    'estado'               => \App\Enums\ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION,
                    'fecha_inicio'         => now(),
                    // Copiamos datos del item/servicio
                    'ciclo_facturacion'        => $item->servicio->ciclo_facturacion,
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
        
        $this->refresh();

        // 2. ACTIVACIÓN REAL (Unificada)
        // Detectar si requiere proyecto. Si lo requiere, NO activamos Stripe aún (esperamos a que acabe el proyecto).
        $requiereProyecto = $this->items->contains(fn($i) => 
            $i->servicio && ($i->servicio->es_editable ? $i->requiere_proyecto : $i->servicio->requiere_proyecto_activacion)
        );

        if (!$requiereProyecto) {
            foreach ($this->suscripciones as $suscripcion) {
                // Solo si no tiene ID de Stripe (no está activada)
                if (!$suscripcion->stripe_subscription_id) {
                    try {
                        // Llamada al servicio ÚNICO que gestiona Tarjeta/SEPA y Prorratas
                        StripeSubscriptionService::activarSuscripcion($suscripcion);
                    } catch (\Exception $e) {
                         Log::error("Error activando suscripción {$suscripcion->id}: " . $e->getMessage());
                    }
                }
            }
        } else {
            Log::info("⏳ Venta #{$this->id} requiere proyecto. Suscripciones quedan pendientes de activación.");
        }

        // 3. Finalizar Venta
        $this->update([
            'estado'        => VentaEstadoEnum::COMPLETADA,
            'fecha_pago'    => $fechaPago,
            'metodo_pago'   => $metodoPago,
            'confirmada_at' => now(),
        ]);

        Log::info('🟢 procesarCobroInicial FIN');
    }
}