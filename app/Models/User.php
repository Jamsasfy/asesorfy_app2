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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
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
}
