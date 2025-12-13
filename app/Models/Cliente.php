<?php

namespace App\Models;

use App\Enums\ServicioTipoEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Enums\ClienteEstadoEnum;
use App\Services\ConfiguracionService; 



class Cliente extends Model
{

    protected $fillable = [
        'user_id',
        'tipo_cliente_id',
        'nombre',
        'apellidos',
        'razon_social',
        'nombre_comercial',  // NUEVO: La marca o rótulo
        'dni_cif',
        'email_contacto',
        'telefono_contacto',
        'direccion',
        'codigo_postal',
        'localidad',
        'provincia',
        'comunidad_autonoma',
        'iban_asesorfy',
        'iban_impuestos',
        'ccc',
        'asesor_id',        
        'observaciones',
        'estado',
        'fecha_alta',
        'fecha_baja',
        'lead_id',
        'comercial_id',
        'stripe_customer_id',
        'preferencia_pago_recurrente',  
       
    ];

// Dentro de la clase Cliente
protected $casts = [
    'estado' => ClienteEstadoEnum::class,
    // ... otros casts que ya tengas
];
    public function user()
{
    return $this->belongsTo(User::class);
}

public function tipoCliente()
{
    return $this->belongsTo(TipoCliente::class, 'tipo_cliente_id');
}

public function asesor()
{
    return $this->belongsTo(User::class, 'asesor_id');
}



public function usuarios(): BelongsToMany
{
    return $this->belongsToMany(User::class, 'cliente_user');
}

public function recordTitle(): string
{
    return $this->razon_social ?? 'Cliente sin nombre';
}

public function documentos(): HasMany
{
    return $this->hasMany(\App\Models\Documento::class);
}

public function comentarios(): MorphMany
{
    return $this->morphMany(Comentario::class, 'comentable');
}

public function leads(): HasMany
{
    return $this->hasMany(Lead::class);
}

 // Relación uno-a-muchos con Ventas (todas las ventas de este cliente)
 public function ventas(): HasMany
 {
     return $this->hasMany(Venta::class);
 }
 /**
 * Un Cliente puede tener muchas suscripciones.
 */
public function suscripciones(): HasMany
{
    return $this->hasMany(ClienteSuscripcion::class);
}
public function documentosPolimorficos()
{
    return $this->morphMany(\App\Models\Documento::class, 'documentable');
}


  /**
     * ACCESOR MEJORADO: Obtiene la suscripción de la tarifa principal activa.
     * Busca directamente en la tabla `cliente_suscripciones` que es la fuente de la verdad.
     */
    protected function tarifaPrincipalActiva(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->suscripciones()
                ->where('es_tarifa_principal', true)
                ->where('estado', \App\Enums\ClienteSuscripcionEstadoEnum::ACTIVA)
                ->first()
        );
    }

    /**
     * ACCESOR MEJORADO: Devuelve el nombre del servicio y el precio formateado.
     * Utiliza el accesor anterior que es mucho más rápido.
     */
    protected function tarifaPrincipalActivaConPrecio(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Llama al nuevo y eficiente accesor 'tarifaPrincipalActiva'
                $suscripcionActiva = $this->tarifa_principal_activa; 
    
                if ($suscripcionActiva && $suscripcionActiva->servicio) {
                    $acronimo = $suscripcionActiva->servicio->acronimo ?? $suscripcionActiva->servicio->nombre;
                    $precioFormateado = number_format($suscripcionActiva->precio_acordado, 2, ',', '.') . ' €';
    
                    return "{$acronimo} - {$precioFormateado}";
                }
                
                return 'Sin tarifa principal';
            }
        );
    }
   
protected static function booted(): void
{
    static::deleting(function (Cliente $cliente) {
        // 🗑️ Comentarios
        $cliente->comentarios()->delete();

        // 🗑️ Documentos normales
        $cliente->documentos()->each(fn ($doc) => $doc->delete());

        // 🗑️ Documentos polimórficos
        $cliente->documentosPolimorficos()->each(fn ($doc) => $doc->delete());

        // 🗑️ Ventas
        $cliente->ventas()->each(fn ($venta) => $venta->delete());

        // 🗑️ Suscripciones
        $cliente->suscripciones()->each(fn ($suscripcion) => $suscripcion->delete());

        // 👥 Usuarios con acceso a este cliente
        foreach ($cliente->usuarios as $usuario) {
            $otrosClientes = $usuario->clientes()->where('clientes.id', '!=', $cliente->id)->exists();

            if (! $otrosClientes) {
                // Solo se elimina si no tiene más accesos
                $usuario->delete();
            }
        }

        // 🔓 Limpieza de la tabla pivote
        $cliente->usuarios()->detach();
    });
}
public function tieneMetodoPagoStripe(): bool
{
    if (!$this->stripe_customer_id) {
        return false;
    }

    try {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $customer = \Stripe\Customer::retrieve($this->stripe_customer_id);

        return !empty($customer->invoice_settings->default_payment_method);

    } catch (\Exception $e) {
        \Log::error("Stripe ERROR comprobando método de pago cliente {$this->id}: " . $e->getMessage());
        return false;
    }
}

// Al principio del archivo Cliente.php, con los otros use:


    /**
     * Devuelve el porcentaje de impuestos aplicable.
     * - Si es Canarias (Provincia o CP): Devuelve 0.00.
     * - Si es resto: Devuelve el valor de la variable 'IVA_general' (por defecto 21.00).
     */
  public static function getPorcentajeImpuesto(?string $codPostal = null, ?string $provincia = null): float
    {
        // 1. PRIORIDAD: CÓDIGO POSTAL (Es lo más fiable porque es numérico)
        // 35 = Las Palmas, 38 = Santa Cruz de Tenerife, 51 = Ceuta, 52 = Melilla
        $cpLimpio = preg_replace('/[^0-9]/', '', $codPostal ?? '');
        
        if (strlen($cpLimpio) >= 2) {
            $prefijo = (int) substr($cpLimpio, 0, 2);
            if (in_array($prefijo, [35, 38, 51, 52])) {
                return 0.00; // Exento
            }
        }

        // 2. RESPALDO: PROVINCIA (Lista Cerrada)
        // Comprobamos exactamente contra los nombres de tu array de configuración
        if (!empty($provincia)) {
            // Array de provincias/ciudades exentas de IVA (IGIC/IPSI)
            // Nota: Ceuta y Melilla no están en tu lista, pero las dejo por seguridad
            $zonasExentas = [
                'Las Palmas',
                'Santa Cruz de Tenerife',
                'Ceuta',
                'Melilla'
            ];

            if (in_array(trim($provincia), $zonasExentas)) {
                return 0.00;
            }
        }

        // 3. RESTO DE ESPAÑA: 21%
        if (class_exists(\App\Services\ConfiguracionService::class)) {
             return (float) \App\Services\ConfiguracionService::get('IVA_general', 21.00);
        }

        return 21.00;
    }
}
