<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

// 👇 IMPORTACIÓN NORMAL DEL TRAIT — SIN BLOQUES {}
use Illuminate\Notifications\Notifiable;

use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;


class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use HasRoles;

    // 👇 APLICAMOS EL TRAIT CON ALIAS DENTRO DE LA CLASE (AQUÍ SÍ)
    use Notifiable {
        Notifiable::notify as protected laravelNotify;
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'acceso_app',
        'portal_activo',
        'cuenta_activada_at',
        'activation_token',
        'activation_token_expires_at',
        'email_bienvenida_enviado',
        'fecha_inicio_comercial',
        'meses_prueba',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'           => 'datetime',
            'password'                    => 'hashed',
            'cuenta_activada_at'          => 'datetime',
            'activation_token_expires_at' => 'datetime',
            'fecha_inicio_comercial'      => 'date',
        ];
    }

    // bootIAFy no puede acceder al panel protegido
    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->id === 9999) {
            return false;
        }

        if ($panel->getId() === 'admin') {
            return $this->hasRole('super_admin') || (bool) $this->trabajador;
        }

        if ($panel->getId() === 'portal') {
            return $this->clientes()->exists();
        }

        return false;
    }

    // ⛔ Override notify() para que el bot no reciba notificaciones
    public function notify($notification): void
    {
        if ($this->id === 9999) {
            return;
        }

        // Llamamos al método original del trait
        $this->laravelNotify($notification);
    }

    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->map(fn (string $name) => Str::of($name)->substr(0, 1))
            ->implode('');
    }

    public function oficina(): BelongsTo
    {
        return $this->belongsTo(Oficina::class);
    }

    public function trabajador(): HasOne
    {
        return $this->hasOne(Trabajador::class);
    }

    public function getFullNameAttribute(): string
    {
        $apellidos = $this->trabajador?->apellidos ?? '';
        return trim("{$this->name} {$apellidos}");
    }

    public function clientes(): BelongsToMany
    {
        return $this->belongsToMany(Cliente::class, 'cliente_user');
    }

    public function tipoDeUsuario(): string
    {
        if ($this->hasRole('super_admin')) {
            return 'Super Admin';
        }

        if ($this->trabajador) {
            return 'Trabajador';
        }

        if ($this->clientes()->exists()) {
            return 'Cliente';
        }

        return 'Desconocido';
    }

    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class);
    }

    public function reglasComision(): BelongsToMany
    {
        return $this->belongsToMany(ComisionRegla::class, 'comercial_reglas', 'comercial_id', 'regla_id')
            ->withPivot('es_obligatoria', 'activa')
            ->withTimestamps();
    }

    public function asignacionesReglas(): HasMany
    {
        return $this->hasMany(ComercialRegla::class, 'comercial_id');
    }

    public function comisionesMensuales(): HasMany
    {
        return $this->hasMany(ComisionMensual::class, 'comercial_id');
    }

    public function historialObjetivos(): HasMany
    {
        return $this->hasMany(ComercialHistorialObjetivo::class, 'comercial_id');
    }

    public function alertasComercial(): HasMany
    {
        return $this->hasMany(ComercialAlerta::class, 'comercial_id');
    }

    /**
     * Indica si el comercial está actualmente en período de prueba.
     */
    public function estaEnPeriodoPrueba(): bool
    {
        if (! $this->fecha_inicio_comercial) {
            return false;
        }

        $meses = $this->meses_prueba ?? 3;
        $finPrueba = $this->fecha_inicio_comercial->copy()->addMonths($meses)->subDay();

        return now()->lte($finPrueba);
    }

    /**
     * Devuelve en qué mes de prueba se encuentra el comercial (1-based).
     * Si no está en prueba, devuelve null.
     */
    public function getMesActualPrueba(): ?int
    {
        if (! $this->estaEnPeriodoPrueba()) {
            return null;
        }

        $inicio = $this->fecha_inicio_comercial->copy()->startOfMonth();
        $ahora  = now()->startOfMonth();

        return (int) $inicio->diffInMonths($ahora) + 1;
    }

    /**
     * Indica si un mes/año concreto cayó dentro del período de prueba.
     */
    public function mesEstaEnPeriodoPrueba(int $anio, int $mes): bool
    {
        if (! $this->fecha_inicio_comercial) {
            return false;
        }

        $meses      = $this->meses_prueba ?? 3;
        $inicio     = $this->fecha_inicio_comercial->copy()->startOfMonth();
        $finPrueba  = $this->fecha_inicio_comercial->copy()->addMonths($meses)->subDay();
        $fechaMes   = \Carbon\Carbon::create($anio, $mes, 1)->startOfMonth();

        return $fechaMes->gte($inicio) && $fechaMes->lte($finPrueba);
    }

    public function contratosIncentivos(): HasMany
    {
        return $this->hasMany(ComercialContratoIncentivo::class, 'comercial_id');
    }

    public function tieneContratoFirmado(): bool
    {
        return $this->contratosIncentivos()
            ->whereNotNull('fecha_firma')
            ->exists();
    }

    public function contratoBaseFirmado(): ?ComercialContratoIncentivo
    {
        return $this->contratosIncentivos()
            ->where('tipo', 'base')
            ->whereNotNull('fecha_firma')
            ->first();
    }

    public function tieneReglasNoFirmadas(): bool
    {
        if (!$this->contratoBaseFirmado()) {
            return true;
        }

        $reglasActuales = $this->asignacionesReglas()
            ->where('activa', true)
            ->with('regla')
            ->get();

        if ($reglasActuales->isEmpty()) {
            return false;
        }

        $snapshotActual = $reglasActuales->map(function ($asignacion) {
            return [
                'id'             => $asignacion->regla_id,
                'nombre'         => $asignacion->regla->nombre,
                'minimo'         => (string) $asignacion->regla->minimo_mensual,
                'porcentaje'     => (string) $asignacion->regla->porcentaje_comision,
                'penalizacion'   => $asignacion->regla->penalizacion_baja_antes_meses,
                'es_obligatoria' => (bool) $asignacion->es_obligatoria,
                'activa'         => (bool) $asignacion->regla->activa,
            ];
        })->sortBy('id')->values()->toArray();

        $ultimoContrato = $this->contratosIncentivos()
            ->whereNotNull('fecha_firma')
            ->latest('fecha_firma')
            ->first();

        if (!$ultimoContrato) {
            return true;
        }

        $snapshotContrato = collect($ultimoContrato->reglas_snapshot)
            ->map(fn ($r) => [
                'id'             => $r['id'],
                'nombre'         => $r['nombre'],
                'minimo'         => (string) $r['minimo'],
                'porcentaje'     => (string) $r['porcentaje'],
                'penalizacion'   => $r['penalizacion'],
                'es_obligatoria' => (bool) $r['es_obligatoria'],
                'activa'         => (bool) $r['activa'],
            ])
            ->sortBy('id')
            ->values()
            ->toArray();

        return $snapshotActual !== $snapshotContrato;
    }

    public function puedeAprobarComisiones(): bool
    {
        return $this->tieneContratoFirmado() && !$this->tieneReglasNoFirmadas();
    }

}
