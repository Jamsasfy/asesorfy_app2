<?php

namespace App\Models;

use Throwable;
use BackedEnum;
use Exception;
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
use App\Models\LeadConversionLink;


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
                } catch (Throwable $e) {}
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
// --- PROCESO POST-FIRMA (CREACIÓN ESTRUCTURAS) ---
public function processSaleAfterCreation(array $extraData = []): void
{
    $this->loadMissing('items.servicio', 'cliente');

    $normalize = function ($text) {
        $text = strtolower((string) $text);
        $text = str_replace(['á','é','í','ó','ú','ñ'], ['a','e','i','o','u','n'], $text);
        return $text;
    };

    // ---------------------------------------------------------
    // 🔥 0) REHIDRATAR FLAGS/DTO DESDE SALE_BLUEPRINT (LeadConversionLink)
    // - Esto es clave para que ClienteSuscripcion guarde:
    //   no_cobrar_primer_periodo + descuento_% + meses
    // ---------------------------------------------------------
    $parseMoneyOrNumber = function ($raw): float {
        if ($raw === null) return 0.0;
        if (is_string($raw)) {
            $raw = str_replace(['€', ' ', ','], ['', '', '.'], $raw);
        }
        return is_numeric($raw) ? (float) $raw : 0.0;
    };

    $blueprintServicios = [];

    try {
        $link = null;

        // 1) Preferente: link que referencia esta venta (si lo estás guardando así)
        $link = LeadConversionLink::query()
            ->where('meta->existing_venta_id', $this->id)
            ->latest('id')
            ->first();

        // 2) Fallback: último link del lead (aunque esté usado/expirado)
        if (! $link && $this->lead_id) {
            $link = LeadConversionLink::query()
                ->where('lead_id', $this->lead_id)
                ->latest('id')
                ->first();
        }

        $meta = $link?->meta ?? [];
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : [];
        }

        $blueprintServicios = data_get($meta, 'sale_blueprint.servicios', []);
        if (! is_array($blueprintServicios)) {
            $blueprintServicios = [];
        }

    } catch (\Throwable $e) {
        Log::warning('⚠️ No se pudo leer sale_blueprint para rehidratar flags/DTO', [
            'venta_id' => $this->id,
            'error' => $e->getMessage(),
        ]);
        $blueprintServicios = [];
    }

    // Indexar por servicio_id (tu UI evita duplicados, pero igual lo hacemos robusto)
    $blueprintPorServicioId = [];
    foreach ($blueprintServicios as $bp) {
        if (! is_array($bp)) continue;
        $sid = (int) data_get($bp, 'servicio_id', 0);
        if ($sid > 0) {
            $blueprintPorServicioId[$sid] = $bp;
        }
    }

    // Detectar si ALGÚN servicio marca "requiere proyecto" (esto SOLO bloquea activación de recurrentes)
    $ventaRequiereProyecto = $this->items->contains(function ($item) {
        if (! $item->servicio) return false;

        return (bool) (
            $item->servicio->es_editable
                ? ($item->requiere_proyecto ?? false)
                : ($item->servicio->requiere_proyecto_activacion ?? false)
        );
    });

    // ---------------------------------------------------------
    // 1. PREPARAR DATOS DEL FORMULARIO (Mapeo)
    // ---------------------------------------------------------
    // A) Alta Autónomo
    $altaAutonomo = array_filter([
        'Fecha Inicio'        => $extraData['extra_auto_fecha_inicio'] ?? null,
        'Fecha Nacimiento'    => $extraData['fecha_nacimiento'] ?? null,
        'Nº Seg. Social'      => $extraData['seguridad_social'] ?? null,
        'Certificado Digital' => isset($extraData['extra_auto_certificado_digital'])
            ? (($extraData['extra_auto_certificado_digital'] ? 'Sí' : 'No'))
            : null,
        'Actividad (IAE)'     => $extraData['extra_auto_actividad'] ?? null,
        'Lugar Trabajo'       => $extraData['extra_auto_lugar'] ?? null,
        'Dirección Local'     => $extraData['extra_auto_direccion_local'] ?? null,
        'Tarifa Plana'        => isset($extraData['extra_auto_tarifa_plana'])
            ? (($extraData['extra_auto_tarifa_plana'] ? 'Sí' : 'No'))
            : null,
    ]);

    // B) Capitalización Paro
    $capitalizacion = array_filter([
        'Forma Jurídica'      => $extraData['extra_cap_forma_juridica'] ?? null,
        'Inversión Prevista'  => $extraData['extra_cap_inversion'] ?? null,
        'Importe Solicitado'  => $extraData['extra_cap_solicitado'] ?? null,
        'Modalidad'           => $extraData['extra_cap_modalidad'] ?? null,
        'Memoria Explicativa' => $extraData['extra_cap_memoria'] ?? null,
        'Fecha Paro'          => $extraData['extra_cap_fecha_paro'] ?? null,
        'Prestación Mensual'  => $extraData['extra_cap_prestacion_mensual'] ?? null,
        'Duración Paro'       => $extraData['extra_cap_duracion_paro'] ?? null,
        'Oficina SEPE'        => $extraData['extra_cap_oficina_sepe'] ?? null,
    ]);

    // C) Constitución SL (arrays paralelos)
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

        'Socios'         => $extraData['extra_sl_socios_nombre'] ?? [],
        'Socios DNI'     => $extraData['extra_sl_socios_dni'] ?? [],
        'Socios %'       => $extraData['extra_sl_socios_porcentaje'] ?? [],
        'Socios Régimen' => $extraData['extra_sl_socios_regimen'] ?? [],

        'Tipo Admin'     => $extraData['extra_sl_tipo_admin'] ?? null,
        'Admin Nombre'   => $extraData['extra_sl_admin_nombre'] ?? null,
        'Ciudad Firma'   => $extraData['extra_sl_ciudad_firma'] ?? null,
    ];

    // ---------------------------------------------------------
    // 2. CREAR PROYECTOS
    // ✅ Proyecto SIEMPRE para servicios ÚNICOS
    // ---------------------------------------------------------
    foreach ($this->items as $item) {
        $servicio = $item->servicio;
        if (! $servicio) continue;

        $tipoServicio = $servicio->tipo instanceof \BackedEnum
            ? $servicio->tipo->value
            : (string) $servicio->tipo;

        $debeCrearProyecto = ($tipoServicio === \App\Enums\ServicioTipoEnum::UNICO->value);
        if (! $debeCrearProyecto) {
            continue;
        }

        // idempotencia
        if (\App\Models\Proyecto::where('venta_item_id', $item->id)->exists()) {
            continue;
        }

        $nombreProyecto = $item->nombre_personalizado ?: $servicio->nombre;
        $nombreNorm = $normalize($nombreProyecto);

        $descripcion = "Proyecto generado por la venta #{$this->id}.";
        $detalles = [];

        if (str_contains($nombreNorm, 'autonomo') || str_contains($nombreNorm, 'alta')) {
            foreach ($altaAutonomo as $k => $v) $detalles[] = "- {$k}: {$v}";
        }
        elseif (str_contains($nombreNorm, 'capitaliz') || str_contains($nombreNorm, 'paro')) {
            foreach ($capitalizacion as $k => $v) $detalles[] = "- {$k}: {$v}";
        }
        elseif (str_contains($nombreNorm, 'sociedad') || str_contains($nombreNorm, 'sl') || str_contains($nombreNorm, 'constitu')) {

            if (! empty($crearSL['Nombres Propuestos'])) {
                $detalles[] = "- Nombres: " . implode(', ', $crearSL['Nombres Propuestos']);
            }

            if (! empty($crearSL['Capital Social'])) {
                $detalles[] = "- Capital: {$crearSL['Capital Social']} € ({$crearSL['Tipo Aportación']})";
            }

            if (! empty($crearSL['Actividad'])) {
                $detalles[] = "- Actividad: {$crearSL['Actividad']}";
            }

            if (! empty($crearSL['Tipo Admin'])) {
                $detalles[] = "- Administración: {$crearSL['Tipo Admin']} (" . ($crearSL['Admin Nombre'] ?? '-') . ")";
            }

            $socios = is_array($crearSL['Socios'] ?? null) ? $crearSL['Socios'] : [];
            if (! empty($socios)) {
                $detalles[] = "- Socios:";
                foreach ($socios as $idx => $socioNombre) {
                    $socioNombre = trim((string) $socioNombre);
                    if ($socioNombre === '') continue;

                    $pct = $crearSL['Socios %'][$idx] ?? null;
                    $pct = ($pct === null || $pct === '') ? '?' : $pct;

                    $dni = $crearSL['Socios DNI'][$idx] ?? null;
                    $dni = $dni ? trim((string) $dni) : null;

                    $reg = $crearSL['Socios Régimen'][$idx] ?? null;
                    $reg = $reg ? trim((string) $reg) : null;

                    $line = "  * {$socioNombre} ({$pct}%)";
                    if ($dni) $line .= " — DNI: {$dni}";
                    if ($reg) $line .= " — Régimen/Casamiento: {$reg}";

                    $detalles[] = $line;
                }
            }
        }

        if (! empty($detalles)) {
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
    // 3. CREAR SUSCRIPCIONES (SIEMPRE PENDIENTES)
    // 🔥 CLAVE: NO poner ACTIVA aquí nunca
    // ---------------------------------------------------------
    foreach ($this->items as $item) {
        $servicio = $item->servicio;
        if (! $servicio) continue;

        $tipoServicio = $servicio->tipo instanceof \BackedEnum
            ? $servicio->tipo->value
            : (string) $servicio->tipo;

        if ($tipoServicio !== \App\Enums\ServicioTipoEnum::RECURRENTE->value) {
            continue;
        }

        // idempotencia
        if (\App\Models\ClienteSuscripcion::where('venta_origen_id', $this->id)
            ->where('servicio_id', $item->servicio_id)
            ->exists()) {
            continue;
        }

        $cantidad = (int) ($item->cantidad ?? 1);
        $cantidad = $cantidad <= 0 ? 1 : $cantidad;

        // ✅ precio acordado UNITARIO (ya con descuento aplicado en subtotal_aplicado)
        $subtotalAplicado = (float) ($item->subtotal_aplicado ?? $item->subtotal ?? 0);
        $precioUnitario = $cantidad > 0 ? round($subtotalAplicado / $cantidad, 2) : round($subtotalAplicado, 2);

        $estadoInicial = \App\Enums\ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION;
        $fechaInicio = null;

        // ---------------------------------------------------------
        // ✅ FLAGS/DTO: preferimos lo que ya esté en el item,
        // pero si viene vacío, lo rehidratamos desde sale_blueprint.
        // ---------------------------------------------------------
        $bp = $blueprintPorServicioId[(int) $item->servicio_id] ?? null;

        // no_cobrar_primer_periodo
        $noCobrarPrimerPeriodo = false;
        // (si tu VentaItem tuviera campo, lo respetamos; si no, quedará null y usamos blueprint)
        $rawItemNoCobrar = data_get($item, 'no_cobrar_primer_periodo');
        if ($rawItemNoCobrar !== null) {
            $noCobrarPrimerPeriodo = (bool) $rawItemNoCobrar;
        } elseif (is_array($bp)) {
            $noCobrarPrimerPeriodo = (bool) data_get($bp, 'no_cobrar_primer_periodo', false);
        }

        // descuento (solo porcentaje + meses)
        $descuentoTipo = $item->descuento_tipo ?? null;
        $descuentoValor = $item->descuento_valor ?? null;
        $descuentoMeses = $item->descuento_duracion_meses ?? null;

        $tieneDtoItem = filled($descuentoTipo) && (float) ($descuentoValor ?? 0) > 0 && (int) ($descuentoMeses ?? 0) >= 1;

        if (! $tieneDtoItem && is_array($bp)) {
            $aplicar = (bool) data_get($bp, 'descuento.aplicar', false);

            if ($aplicar) {
                $tipoRaw = mb_strtolower(trim((string) data_get($bp, 'descuento.tipo', '')));
                $valorRaw = data_get($bp, 'descuento.valor', 0);
                $mesesRaw = (int) data_get($bp, 'descuento.meses', 0);

                if ($tipoRaw === 'porcentaje') {
                    $valor = $parseMoneyOrNumber($valorRaw);

                    // sanitizar
                    if ($valor > 0 && $valor <= 100 && $mesesRaw >= 1) {
                        $descuentoTipo = 'porcentaje';
                        $descuentoValor = $valor;
                        $descuentoMeses = $mesesRaw;
                    }
                }
            }
        }

        $suscripcion = \App\Models\ClienteSuscripcion::create([
            'cliente_id'               => $this->cliente_id,
            'servicio_id'              => $item->servicio_id,
            'venta_origen_id'          => $this->id,

            'nombre_personalizado'     => $item->nombre_personalizado,
            'es_tarifa_principal'      => (bool) ($servicio->es_tarifa_principal ?? false),

            'precio_acordado'          => $precioUnitario,
            'cantidad'                 => $cantidad,

            'fecha_inicio'             => $fechaInicio,
            'estado'                   => $estadoInicial,
            'ciclo_facturacion'        => $servicio->ciclo_facturacion,

            // ✅ DTO (rehidratado si hacía falta)
            'descuento_tipo'           => $descuentoTipo,
            'descuento_valor'          => $descuentoValor,
            'descuento_duracion_meses' => $descuentoMeses,

            // ✅ FLAG CLAVE
            'no_cobrar_primer_periodo' => (bool) $noCobrarPrimerPeriodo,

            'descuento_descripcion'    => $item->observaciones_descuento,
            'descuento_valido_hasta'   => $item->descuento_valido_hasta,
            'observaciones'            => $item->observaciones_item
                ? $item->observaciones_item . ($ventaRequiereProyecto ? ' | Pendiente de proyecto' : ' | Pendiente de método recurrente')
                : ($ventaRequiereProyecto ? 'Pendiente de proyecto' : 'Pendiente de método recurrente'),

            // ✅ Guardamos el blueprint en datos_adicionales para auditoría
            'datos_adicionales'        => is_array($bp) ? ['sale_blueprint_line' => $bp] : null,
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
    // ✅ si ya marcamos pago inicial en la propia venta, es completado
    if (!empty($this->pago_inicial_fecha) || !empty($this->pago_inicial_referencia)) {
        return true;
    }

    // ✅ fallback legacy: factura pagada
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
    // Si ya están cargados los items, evitamos query extra
    if ($this->relationLoaded('items')) {
        $this->loadMissing('items.servicio');

        return $this->items->contains(function ($item) {
            if (! $item->servicio) return false;

            $tipo = $item->servicio->tipo instanceof \BackedEnum
                ? $item->servicio->tipo->value
                : $item->servicio->tipo;

            if ($tipo !== \App\Enums\ServicioTipoEnum::UNICO->value) {
                return false;
            }

            $importe = (float) ($item->subtotal_aplicado ?? $item->subtotal ?? 0);

            // ✅ Solo requiere pago inicial si hay importe > 0
            return $importe > 0;
        });
    }

    // Query directa: existe algún item ÚNICO con subtotal_aplicado > 0 (o subtotal > 0 si subtotal_aplicado es null)
    return $this->items()
        ->whereHas('servicio', fn ($q) => $q->where('tipo', \App\Enums\ServicioTipoEnum::UNICO->value))
        ->where(function ($q) {
            $q->where('subtotal_aplicado', '>', 0)
              ->orWhere(function ($qq) {
                  $qq->whereNull('subtotal_aplicado')
                     ->where('subtotal', '>', 0);
              });
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


    private function getLatestConversionLink(): ?LeadConversionLink
{
    return LeadConversionLink::query()
        ->where('meta->existing_venta_id', $this->id)
        ->latest('id')
        ->first();
}

public function recurrenteMetodoConfirmado(): bool
{
    $link = $this->getLatestConversionLink();
    return filled(data_get($link?->meta, 'recurrente_metodo'));
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
    Log::info('🔵 procesarCobroInicial INICIO', [
        'venta_id' => $this->id,
        'metodo' => $metodoPago,
        'payment_intent' => $paymentIntentId,
    ]);

    $this->loadMissing('cliente', 'items.servicio', 'suscripciones');
    $cliente = $this->cliente;

    if (! $cliente) {
        throw new \Exception('Venta sin cliente asociado');
    }

    // =========================================================
    // 0️⃣ POST-VENTA: Proyectos + Suscripciones LOCALES (PENDIENTE)
    // =========================================================
    try {
        $this->processSaleAfterCreation($extraData);
    } catch (\Throwable $e) {
        Log::error('❌ Error en processSaleAfterCreation', [
            'venta_id' => $this->id,
            'error' => $e->getMessage(),
        ]);
    }

    $this->refresh();
    $this->loadMissing('items.servicio', 'suscripciones');

    // =========================================================
    // 1️⃣ Determinar escenarios
    // =========================================================
    $esTransfer = in_array($metodoPago, ['transferencia', 'transferencia_confirmada', 'transferencia_recibida'], true);
    $transferConfirmada = in_array($metodoPago, ['transferencia_confirmada', 'transferencia_recibida'], true);

    $metodoPagoVenta = $esTransfer ? 'transferencia' : $metodoPago;

    // =========================================================
    // 2️⃣ Guardar campos base del pago inicial
    // =========================================================
    $this->forceFill([
        'pago_inicial_fecha'      => $fechaPago,
        'pago_inicial_metodo'     => $metodoPagoVenta,
        'pago_inicial_referencia' => $paymentIntentId,
    ])->save();

    // =========================================================
    // 3️⃣ Método/estado de factura
    // =========================================================
    $metodoFactura = $esTransfer ? 'transferencia' : (match ($metodoPago) {
        'stripe', 'stripe_automatico', 'suscripcion_directa', 'tarjeta' => 'stripe',
        default => $metodoPago,
    });

    $estadoFactura = ($esTransfer && ! $transferConfirmada)
        ? \App\Enums\FacturaEstadoEnum::PENDIENTE_PAGO
        : \App\Enums\FacturaEstadoEnum::PAGADA;

    // =========================================================
    // 4️⃣ Generar factura inicial (solo si procede)
    // =========================================================
    try {
        \App\Services\FacturacionService::generarFacturaInicial(
            $this->fresh(),
            $fechaPago,
            $metodoFactura,
            $estadoFactura,
            null,                // stripe_invoice_id (si algún día lo tienes aquí)
            $paymentIntentId     // ✅ stripe_payment_intent_id
        );

        Log::info("🧾 Factura generada para venta {$this->id} ({$metodoFactura}) estado={$estadoFactura->value}");
    } catch (\Throwable $e) {
        Log::error('❌ Error generando factura inicial tras cobro', [
            'venta_id' => $this->id,
            'metodo_factura' => $metodoFactura,
            'estado_factura' => $estadoFactura->value,
            'error' => $e->getMessage(),
        ]);
    }

    // =========================================================
    // 5️⃣ Cambiar estado de venta
    // =========================================================
    if ($esTransfer && ! $transferConfirmada) {
        $this->update(['estado' => \App\Enums\VentaEstadoEnum::PENDIENTE]);
        Log::info("💤 Venta #{$this->id} pendiente (transferencia aún no confirmada)");
    } else {
        $this->update([
            'estado'        => \App\Enums\VentaEstadoEnum::COMPLETADA,
            'confirmada_at' => now(),
        ]);
        Log::info("✅ Venta #{$this->id} marcada como COMPLETADA ({$metodoPago})");

        // =========================================================
        // 6️⃣ Activar suscripciones Stripe si procede
        // - Venta COMPLETADA
        // - NO bloqueo global
        // - PM en Stripe
        // - y RESPETA el método recurrente (tarjeta vs sepa)
        // =========================================================
        try {
            $this->refresh();
            $this->loadMissing('cliente', 'items.servicio', 'suscripciones.servicio');

            // ✅ Método recurrente (fuente de verdad):
            // 1) extraData (flujo público)
            // 2) fallback a preferencia del cliente (admin/transferencias)
            $recurrenteMetodo = data_get($extraData, 'recurrente_metodo')
                ?? ($this->cliente?->preferencia_pago_recurrente ?: null);

            // ✅ BLOQUEO GLOBAL: si algún ÚNICO bloquea_recurrente => NO activar ahora
            $esperaProyecto = $this->items->contains(function ($i) {
                if (! $i->servicio) return false;

                $tipo = $i->servicio->tipo instanceof \BackedEnum
                    ? $i->servicio->tipo->value
                    : $i->servicio->tipo;

                if ($tipo !== 'unico') return false;

                $itemBloquea = (bool) ($i->bloquea_recurrente ?? false);
                $svcBloquea  = (bool) ($i->servicio->bloquea_recurrente ?? false);

                if ($itemBloquea || $svcBloquea) return true;

                // Legacy fallback: si no existen flags nuevos, se usaba "requiere proyecto" como bloqueo
                if (!isset($i->bloquea_recurrente) && !isset($i->servicio->bloquea_recurrente)) {
                    $esEditable = (bool) ($i->servicio->es_editable ?? false);
                    return $esEditable
                        ? (bool) ($i->requiere_proyecto ?? false)
                        : (bool) ($i->servicio->requiere_proyecto_activacion ?? false);
                }

                return false;
            });

            if ($esperaProyecto) {
                Log::info("⏭️ Venta #{$this->id}: recurrente diferido por bloqueo global (no activar ahora).");
                return;
            }

            $cliente = $this->cliente;

            if (! $cliente?->stripe_customer_id) {
                Log::warning("⚠️ Venta #{$this->id}: cliente sin stripe_customer_id. No se puede activar recurrente.");
                return;
            }

            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            if (app()->isLocal()) {
                \Stripe\Stripe::setVerifySslCerts(false);
            }

            $stripeCustomer = \Stripe\Customer::retrieve([
                'id'     => $cliente->stripe_customer_id,
                'expand' => ['invoice_settings.default_payment_method'],
            ]);

            $defaultPm = $stripeCustomer->invoice_settings->default_payment_method ?? null;
            $pmId      = $defaultPm->id ?? null;
            $pmType    = $defaultPm->type ?? null; // 'card' | 'sepa_debit' | null

            if (! $pmId) {
                Log::info("⏭️ Venta #{$this->id}: sin default PM en Stripe. Se activará tras setup-card/setup-sepa.", [
                    'recurrente_metodo' => $recurrenteMetodo,
                ]);
                return;
            }

            // ✅ Respeta método elegido (evita caso mixto tarjeta->sepa)
            if ($recurrenteMetodo === 'domiciliacion' && $pmType !== 'sepa_debit') {
                Log::info("⏭️ Venta #{$this->id}: recurrente=domiciliacion pero default PM NO es sepa_debit. No activar ahora.", [
                    'pmType' => $pmType,
                ]);
                return;
            }

            if ($recurrenteMetodo === 'tarjeta' && $pmType !== 'card') {
                Log::info("⏭️ Venta #{$this->id}: recurrente=tarjeta pero default PM NO es card. No activar ahora.", [
                    'pmType' => $pmType,
                ]);
                return;
            }

            $pendientes = $this->suscripciones->filter(function ($s) {
                return empty($s->stripe_subscription_id)
                    && $s->estado === \App\Enums\ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION;
            });

            if ($pendientes->isEmpty()) {
                Log::info("ℹ️ Venta #{$this->id}: no hay suscripciones pendientes de activación.");
                return;
            }

            foreach ($pendientes as $suscripcion) {
                Log::info("🚀 Activando suscripción inmediata (sin bloqueo)", [
                    'venta_id'       => $this->id,
                    'suscripcion_id' => $suscripcion->id,
                    'recurrente_metodo' => $recurrenteMetodo,
                    'pmType' => $pmType,
                ]);

                \App\Services\StripeSubscriptionService::activarSuscripcion($suscripcion);
            }
        } catch (\Throwable $e) {
            Log::error("❌ Error activando suscripciones en procesarCobroInicial", [
                'venta_id' => $this->id,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    Log::info('🟢 procesarCobroInicial FIN', [
        'venta_id' => $this->id,
        'metodo_factura' => $metodoFactura,
        'estado_factura' => $estadoFactura->value,
    ]);
}


public function activarSuscripcionesSiProcede(?string $recurrenteMetodo = null): void
{
    $this->loadMissing('items.servicio', 'suscripciones', 'cliente');

    $tieneRecurrente = $this->items->contains(fn ($i) =>
        $i->servicio && (
            ($i->servicio->tipo instanceof \BackedEnum ? $i->servicio->tipo->value : (string) $i->servicio->tipo)
            === \App\Enums\ServicioTipoEnum::RECURRENTE->value
        )
    );

    if (! $tieneRecurrente) {
        return;
    }

    // ✅ Sin método elegido -> NO activar
    if (! filled($recurrenteMetodo)) {
        Log::info("⏭️ activarSuscripcionesSiProcede: sin recurrente_metodo. No se activa.", [
            'venta_id' => $this->id,
        ]);
        return;
    }

    // ✅ Bloqueo por “requiere proyecto” (tu concepto)
    $bloqueaPorProyecto = $this->items->contains(function ($item) {
        if (! $item->servicio) return false;

        return (bool) (
            $item->servicio->es_editable
                ? ($item->requiere_proyecto ?? false)
                : ($item->servicio->requiere_proyecto_activacion ?? false)
        );
    });

    if ($bloqueaPorProyecto) {
        Log::info("⏳ activarSuscripcionesSiProcede: bloqueado por proyecto. No se activa.", [
            'venta_id' => $this->id,
        ]);
        return;
    }

    foreach ($this->suscripciones as $suscripcion) {
        // Idempotencia: si ya existe en Stripe, saltar
        if (! empty($suscripcion->stripe_subscription_id)) {
            continue;
        }

        try {
            // Marcamos activa local justo antes/después, como prefieras.
            $suscripcion->estado = \App\Enums\ClienteSuscripcionEstadoEnum::ACTIVA;
            $suscripcion->fecha_inicio = $suscripcion->fecha_inicio ?? now();
            $suscripcion->save();

            \App\Services\StripeSubscriptionService::activarSuscripcion($suscripcion);
                    // ✅ limpiar observaciones "Pendiente de ..."
                    $obs = (string) ($suscripcion->observaciones ?? '');

                    $obs = preg_replace('/\s*\|\s*Pendiente de proyecto\s*/i', '', $obs);
                    $obs = preg_replace('/\s*\|\s*Pendiente de metodo recurrente\s*/i', '', $obs);
                    $obs = preg_replace('/\s*\|\s*Pendiente de método recurrente\s*/iu', '', $obs);

                    // normalizar pipes duplicados/espacios
                    $obs = trim(preg_replace('/\s*\|\s*\|\s*/', ' | ', $obs));
                    $obs = trim($obs);
                    if ($obs === '') {
                        $obs = null;
                    }

                    $suscripcion->observaciones = $obs;
                    $suscripcion->save();



            Log::info("✅ Suscripción activada en Stripe", [
                'venta_id' => $this->id,
                'suscripcion_id' => $suscripcion->id,
            ]);
        } catch (\Throwable $e) {
            Log::error("❌ Error activando suscripción {$suscripcion->id}", [
                'venta_id' => $this->id,
                'error' => $e->getMessage(),
            ]);

            // opcional: revertir estado local
            $suscripcion->estado = \App\Enums\ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION;
            $suscripcion->fecha_inicio = null;
            $suscripcion->save();
        }
    }
}



public function enviarBienvenidaSiProcede(string $triggerSource = 'conversion_finished', array $ctx = []): bool
{
    $this->loadMissing('cliente', 'lead');

    $cliente = $this->cliente;
    $lead    = $this->lead;

    if (! $cliente || ! $cliente->email_contacto) {
        return false;
    }

    if ($lead && \App\Models\LeadAutoEmailLog::where('lead_id', $lead->id)
        ->where('template_identifier', 'welcome_client')
        ->where('status', 'sent')
        ->exists()) {
        return false;
    }

    try {
Mail::to($cliente->email_contacto)->send(new \App\Mail\WelcomeClientMail($cliente, $this));

        if ($lead) {
            \App\Models\LeadAutoEmailLog::create([
                'lead_id'              => $lead->id,
                'estado'               => 'bienvenida',
                'intento'              => 1,
                'template_identifier'  => 'welcome_client',
                'subject'              => 'Bienvenido a ' . config('app.name') . ' - Próximos pasos',
                'body_preview'         => 'Email de bienvenida enviado.',
                'scheduled_at'         => now(),
                'sent_at'              => now(),
                'status'               => 'sent',
                'mail_driver'          => config('mail.default'),
                'triggered_by_user_id' => 9999,
                'trigger_source'       => $triggerSource,
            ]);
        }

        \App\Models\Comentario::create([
            'comentable_type' => \App\Models\Cliente::class,
            'comentable_id'   => $cliente->id,
            'user_id'         => 9999,
            'contenido'       => "✅ Email de bienvenida enviado automáticamente a {$cliente->email_contacto}.",
        ]);

        Log::info('✅ WelcomeClientMail enviado', [
            'venta_id' => $this->id,
            'cliente_id' => $cliente->id,
            'ctx' => $ctx,
        ]);

        return true;

    } catch (\Throwable $e) {
        Log::error('❌ Error enviando WelcomeClientMail', [
            'venta_id' => $this->id,
            'error' => $e->getMessage(),
        ]);

        return false;
    }
}



}