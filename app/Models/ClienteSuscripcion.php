<?php

namespace App\Models;

use App\Enums\CicloFacturacionEnum;
use App\Enums\ClienteSuscripcionEstadoEnum; // <-- Importamos nuestro Enum
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute; 

class ClienteSuscripcion extends Model
{
    use HasFactory;

    protected $table = 'cliente_suscripciones';


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
  protected $fillable = [
    'cliente_id',
    'servicio_id',
    'venta_origen_id',
    'es_tarifa_principal',
    'precio_acordado',
    'cantidad',
    'fecha_inicio',
    'fecha_fin',
    'estado',
    'descuento_tipo',
    'descuento_valor',
    'descuento_descripcion',
    'descuento_valido_hasta',
    'observaciones',
    'stripe_subscription_id',
    'stripe_status',
    'stripe_current_period_end',
    'stripe_default_payment_method',
    'ciclo_facturacion',
    'proxima_fecha_facturacion',
    'datos_adicionales',
    'nombre_personalizado',
    'descuento_duracion_meses',
    'no_cobrar_primer_periodo',
];


    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'es_tarifa_principal' => 'boolean',
        'precio_acordado' => 'decimal:2',
        'descuento_valor' => 'decimal:2',
        'cantidad' => 'integer',
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'descuento_valido_hasta' => 'date',
        'proxima_fecha_facturacion' => 'date',
        'datos_adicionales' => 'array',
        'estado' => ClienteSuscripcionEstadoEnum::class, // <-- Usamos el Enum para el estado
        'ciclo_facturacion' => CicloFacturacionEnum::class, // Este es el que falta
        'no_cobrar_primer_periodo' => 'boolean',
       

    ];

    // --- RELACIONES ---

    /**
     * La suscripción pertenece a un Cliente.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * La suscripción está asociada a un Servicio.
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    /**
     * La suscripción fue originada por una Venta (opcional).
     */
    public function ventaOrigen(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_origen_id');
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

/**
 * Lógica que se ejecuta al guardar o crear el modelo.
 */
protected static function booted(): void
    {
        static::saving(function (ClienteSuscripcion $suscripcion) {
            // Lógica existente para 'descuento_valido_hasta'
            if ($suscripcion->descuento_duracion_meses > 0) {
                $fechaBaseParaCalculo = null;
                if ($suscripcion->exists && ($suscripcion->isDirty('descuento_tipo') || $suscripcion->isDirty('descuento_duracion_meses'))) {
                    $fechaBaseParaCalculo = Carbon::now();
                } elseif ($suscripcion->isDirty('fecha_inicio') && $suscripcion->fecha_inicio) {
                    $fechaBaseParaCalculo = Carbon::parse($suscripcion->fecha_inicio);
                }

                if ($fechaBaseParaCalculo) {
                    $suscripcion->descuento_valido_hasta = $fechaBaseParaCalculo
                        ->addMonths($suscripcion->descuento_duracion_meses - 1)
                        ->endOfMonth();
                }
            }

            // ** NUEVA LÓGICA: Inicializar proxima_fecha_facturacion **
            // Esto se ejecuta solo si 'proxima_fecha_facturacion' es null
            // y la suscripción está activa, o se está activando.
            if (is_null($suscripcion->proxima_fecha_facturacion) && $suscripcion->estado === ClienteSuscripcionEstadoEnum::ACTIVA) {
                // Usamos la fecha de inicio si existe, de lo contrario, la fecha actual
                $fechaBase = $suscripcion->fecha_inicio ?? Carbon::now();
                
                // Establece la próxima fecha de facturación al inicio del mes siguiente a la fecha base,
                // para que siempre caiga en un día 1 de mes y sea facturable si es el día 1.
                $suscripcion->proxima_fecha_facturacion = $fechaBase->copy()->addMonth()->startOfMonth();
            }
            // Si la suscripción cambia de estado a ACTIVA y antes era nulo o inactivo, también inicializa.
            if ($suscripcion->isDirty('estado') && $suscripcion->estado === ClienteSuscripcionEstadoEnum::ACTIVA && is_null($suscripcion->getOriginal('proxima_fecha_facturacion'))) {
                 $fechaBase = $suscripcion->fecha_inicio ?? Carbon::now();
                 $suscripcion->proxima_fecha_facturacion = $fechaBase->copy()->addMonth()->startOfMonth();
            }
        });
    }
}