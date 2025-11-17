<?php

namespace App\Models;

// Importaciones necesarias
use App\Enums\LeadEstadoEnum; // Importar el Enum de Estados
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Importar BelongsTo para relaciones
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

// Asumiendo que estos modelos existen en App\Models, ajusta si es necesario
// use App\Models\User;
// use App\Models\Procedencia;
// use App\Models\MotivoDescarte;
// use App\Models\Cliente;
// use App\Models\Servicio;

class Lead extends Model
{
    use HasFactory;

    /**
     * The attributes that aren't mass assignable.
     * Comenta esto y usa $fillable si prefieres esa estrategia.
     * @var array<string>|bool
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     * Define cómo Laravel debe tratar ciertos campos.
     * @var array<string, string>
     */
    protected $casts = [
        'estado' => LeadEstadoEnum::class, // Convierte entre string y objeto Enum
        'fecha_gestion' => 'datetime',     // Convierte a objeto Carbon/DateTime
        'agenda' => 'datetime',            // Convierte a objeto Carbon/DateTime
        'fecha_cierre' => 'datetime',      // Convierte a objeto Carbon/DateTime
        'llamadas' => 'integer',           // Asegura que es un número entero
        'emails' => 'integer',            // Asegura que es un número entero
        'chats' => 'integer',             // Asegura que es un número entero
        'otros_acciones' => 'integer',     // Asegura que es un número entero
          'estado_email_intentos' => 'integer',
    'estado_email_ultima_fecha' => 'datetime',
    'ultima_interaccion_manual_at' => 'datetime',
     'autospam_activo' => 'bool',
    ];

      // --- Listener de Evento para fecha_gestion ---
    /**
     * The "booted" method of the model.
     * Se ejecuta cuando el modelo se inicializa.
     */
  protected static function booted(): void
{
    static::updating(function (Lead $lead) {

        // 1) Si cambia el estado...
        if ($lead->isDirty('estado')) {

            $estadoNuevo = $lead->estado instanceof LeadEstadoEnum
                ? $lead->estado->value
                : $lead->estado;

            // Si antes no había fecha_gestion y AHORA deja de estar SIN_GESTIONAR,
            // estampamos la fecha de primera gestión
            if (
                is_null($lead->getOriginal('fecha_gestion')) &&
                $estadoNuevo !== LeadEstadoEnum::SIN_GESTIONAR->value
            ) {
                $lead->fecha_gestion = now();
            }

            // Resetear contadores de emails al cambiar de estado
            $lead->resetEstadoEmails();
        }
    });
}
    // --- Fin Listener ---

    

    // --- RELACIONES BelongsTo ---
    // Definen a qué otros modelos pertenece este Lead

    /**
     * Obtiene la procedencia del lead.
     * Relación con la tabla 'procedencias' a través de 'procedencia_id'.
     */
    public function procedencia(): BelongsTo
    {
        // Asegúrate que el modelo 'Procedencia' existe en App\Models
        return $this->belongsTo(Procedencia::class);
    }

    /**
     * Obtiene el usuario que creó el lead.
     * Relación con 'users' usando la clave foránea 'creado_id'.
     */
    public function creador(): BelongsTo
    {
        // Asegúrate que el modelo 'User' existe en App\Models
        return $this->belongsTo(User::class, 'creado_id');
    }

    /**
     * Obtiene el usuario asignado a gestionar el lead.
     * Relación con 'users' usando la clave foránea 'asignado_id'.
     */
    public function asignado(): BelongsTo
    {
        // Asegúrate que el modelo 'User' existe en App\Models
        return $this->belongsTo(User::class, 'asignado_id');
    }

    /**
     * Obtiene el motivo de descarte (si aplica).
     * Relación con 'motivos_descarte' a través de 'motivo_descarte_id'.
     */
    public function motivoDescarte(): BelongsTo
    {
        return $this->belongsTo(MotivoDescarte::class);
    }

    /**
     * Obtiene el cliente asociado (si se ha convertido).
     * Relación con 'clientes' a través de 'cliente_id'.
     */
   // Un Lead PERTENECE A UN Cliente
public function cliente(): BelongsTo
{
    return $this->belongsTo(Cliente::class);
}

   public function autoEmailLogs()
{
    return $this->hasMany(\App\Models\LeadAutoEmailLog::class)
    ->orderByDesc('sent_at')
        ->orderByDesc('id');
}

    // --- FIN RELACIONES ---

    // Si tienes comentarios polimórficos, añade aquí la relación morphMany:
    // use Illuminate\Database\Eloquent\Relations\MorphMany;
    // public function comentarios(): MorphMany
    // {
    //     return $this->morphMany(Comentario::class, 'comentable'); // Ajusta 'Comentario' a tu modelo
    // }

    public function comentarios(): MorphMany
{
    return $this->morphMany(Comentario::class, 'comentable')->latest();
}

  // Relación uno-a-muchos con Ventas (las ventas originadas por este lead)
  public function ventas(): HasMany
  {
      return $this->hasMany(Venta::class);
  }
// Marca que alguien ha interactuado manualmente con el lead
public function marcarInteraccionManual(): void
{
    $ahora = now();

    $this->ultima_interaccion_manual_at = $ahora;

    // Si aún no se había gestionado nunca el lead, fijamos la fecha de primera gestión
    if (is_null($this->fecha_gestion)) {
        $this->fecha_gestion = $ahora;
    }

    $this->save();
}

// Resetea el contador de emails al cambiar de estado
public function resetEstadoEmails(): void
    {
        $this->estado_email_intentos = 0;
        $this->estado_email_ultima_fecha = null;
       // $this->save();
    }

// Registra que se ha enviado un email automático para el estado actual
   public function registrarEnvioEmailEstado(): void
    {
        $this->estado_email_intentos = ($this->estado_email_intentos ?? 0) + 1;
        $this->estado_email_ultima_fecha = now();
        $this->save();
    }

public function activarAutospam(): void
{
    $this->forceFill(['autospam_activo' => true])->save();
}

public function desactivarAutospam(): void
{
    $this->forceFill(['autospam_activo' => false])->save();
}

// App\Models\Lead.php

public function puedeSugerirPrimerEmailIa(): bool
{
    // Sin email o autospam desactivado → no sugerimos nada
    if (empty($this->email) || ! $this->autospam_activo) {
        return false;
    }

    // Estado actual
    $estadoValue = $this->estado instanceof \App\Enums\LeadEstadoEnum
        ? $this->estado->value
        : (string) $this->estado;

    // El estado tiene autospam configurado?
    $configEstado = config("lead_emails.states.{$estadoValue}");

    if (! $configEstado || empty($configEstado['auto'])) {
        return false;
    }

    // ✅ Solo queremos sugerencia si alguna vez se intentó autospam
    // y se marcó como "skipped" (por ejemplo, porque no había email)
    $tieneSkips = $this->autoEmailLogs()
        ->where('estado', $estadoValue)
        ->where('status', 'skipped')
        ->exists();

    if (! $tieneSkips) {
        // Si nunca hubo un intento skipped, NO mostramos el botón
        return false;
    }

    // Si ya hay envíos reales (sent/pending/rate_limited) para este estado, tampoco sugerimos
    $tieneEnviados = $this->autoEmailLogs()
        ->where('estado', $estadoValue)
        ->whereIn('status', ['sent', 'pending', 'rate_limited'])
        ->exists();

    if ($tieneEnviados) {
        return false;
    }

    // En este punto:
    // - Autospam activo
    // - Estado con auto=true
    // - Hubo intento skipped (ej. no tenía email antes)
    // - No hay envíos reales todavía
    return true;
}


}