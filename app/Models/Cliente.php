<?php

namespace App\Models;

use App\Enums\ClienteSuscripcionEstadoEnum;
use Stripe\Stripe;
use Stripe\Customer;
use Exception;
use App\Models\Factura;

use Log;
use App\Enums\ServicioTipoEnum;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Enums\ClienteEstadoEnum;
use App\Services\ConfiguracionService;
use App\Services\StripePaymentMethodResolver;

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
        'pendientes_respuesta_notificado_at' => 'datetime', // ✅ Esto

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

    public function facturas(): HasMany
    {
        return $this->hasMany(Factura::class);
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

    /* public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    } */

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
        return $this->morphMany(Documento::class, 'documentable');
    }

    public function documentosVinculados(): HasMany
    {
        return $this->hasMany(Documento::class, 'cliente_id');
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
                ->where('estado', ClienteSuscripcionEstadoEnum::ACTIVA)
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

                return 'SIN TARIFA';
            }
        );
    }

   protected static function booted(): void
    {
        static::deleting(function (Cliente $cliente) {
            // 🗑️ Comentarios
            $cliente->comentarios()->delete();

            // 🗑️ Documentos (ownership por cliente_id) -> dispara deleting() y borra el fichero
            $cliente->documentosVinculados()->each(fn ($doc) => $doc->delete());

            // 🗑️ Ventas
            //$cliente->ventas()->each(fn ($venta) => $venta->delete());

            // 🗑️ Suscripciones
            $cliente->suscripciones()->each(fn ($suscripcion) => $suscripcion->delete());

            // 👥 Usuarios con acceso a este cliente
            foreach ($cliente->usuarios as $usuario) {
                $otrosClientes = $usuario->clientes()->where('clientes.id', '!=', $cliente->id)->exists();

                if (! $otrosClientes) {
                    $usuario->delete();
                }
            }

            // 🔓 Limpieza de la tabla pivote
            $cliente->usuarios()->detach();
        });

        // ✅ NUEVO: Lógica de cambio de Asesor
        static::updated(function (Cliente $cliente) {
            if ($cliente->wasChanged('asesor_id')) {
                // 1. Movemos todos los chats de este cliente al nuevo asesor
                \App\Models\ChatConversacion::where('cliente_id', $cliente->id)
                    ->update(['asesor_id' => $cliente->asesor_id]);

                // 2. Buscamos el chat activo en Telegram para avisar al cliente
                $chat = \App\Models\ChatConversacion::where('cliente_id', $cliente->id)
                    ->whereNotNull('telegram_chat_id')
                    ->first();

                if ($chat) {
                    $nuevoAsesor = $cliente->asesor ? $cliente->asesor->name : 'un nuevo compañero';
                    
                    // Registro silencioso en el panel de Mis Chats
                    \App\Models\ChatMensaje::create([
                        'chat_id'   => $chat->id,
                        'origen'    => 'sistema',
                        'tipo'      => 'text',
                        'contenido' => "🔄 Cartera reasignada: El cliente ha pasado a manos de {$nuevoAsesor}.",
                        'leido'     => true,
                    ]);

                    // Aviso por Telegram al cliente (try/catch para proteger cambios masivos)
                    try {
$msg = "🔄 <b>Actualización de tu cuenta</b>\n\nTe informamos que, para darte un mejor servicio, <b>{$nuevoAsesor}</b> ha sido asignado como tu nuevo asesor principal.\n\nPuedes seguir escribiendo por este mismo chat y te atenderá directamente.";                        
                        app(\App\Services\TelegramService::class)->sendMessage(
                            $chat->telegram_chat_id, 
                            $msg, 
                            $chat->telegram_thread_id
                        );
                    } catch (\Throwable $e) {
                        // Falla en silencio si Telegram bloquea o da error
                    }
                }
            }
        });
    }

    public function tieneMetodoPagoStripe(): bool
    {
        if (! $this->stripe_customer_id) {
            return false;
        }

        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            $customer = Customer::retrieve($this->stripe_customer_id);

            return ! empty($customer->invoice_settings->default_payment_method);
        } catch (Exception $e) {
            Log::error("Stripe ERROR comprobando método de pago cliente {$this->id}: " . $e->getMessage());
            return false;
        }
    }

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
        if (! empty($provincia)) {
            $zonasExentas = [
                'Las Palmas',
                'Santa Cruz de Tenerife',
                'Ceuta',
                'Melilla',
            ];

            if (in_array(trim($provincia), $zonasExentas)) {
                return 0.00;
            }
        }

        // 3. RESTO DE ESPAÑA: 21%
        if (class_exists(ConfiguracionService::class)) {
            return (float) ConfiguracionService::get('IVA_general', 21.00);
        }

        return 21.00;
    }

    public function leadConContrato()
    {
        return $this->ventas()
            ->whereHas('lead.conversionLinks', function ($q) {
                $q->whereNotNull('used_at')
                    ->whereNotNull('meta->pdf');
            })
            ->with(['lead.conversionLinks' => function ($q) {
                $q->whereNotNull('used_at')
                    ->whereNotNull('meta->pdf')
                    ->latest('used_at');
            }])
            ->first()
            ?->lead;
    }

    protected function stripeMetodoPago(): Attribute
    {
        return Attribute::make(
            get: fn () => StripePaymentMethodResolver::resolve($this)
        );
    }
}
