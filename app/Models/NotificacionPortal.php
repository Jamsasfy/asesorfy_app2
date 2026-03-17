<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class NotificacionPortal extends Model
{
    protected $table = 'notificaciones_portal';

    protected $fillable = [
        'titulo',
        'mensaje',
        'tipo',
        'canales',
        'bloquea_portal',
        'destinatarios',
        'filtro_servicios',
        'filtro_clientes',
        'activa',
        'fecha_publicacion',
        'fecha_caducidad',
        'created_by',
        'enviada',
        'enviada_at',
    ];

    protected $casts = [
        'canales' => 'array',
        'filtro_servicios' => 'array',
        'filtro_clientes' => 'array',
        'bloquea_portal' => 'boolean',
        'activa' => 'boolean',
        'fecha_publicacion' => 'datetime',
        'fecha_caducidad' => 'datetime',
        'enviada' => 'boolean',
        'enviada_at' => 'datetime',
    ];

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function vistas(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'notificacion_portal_vistas', 'notificacion_portal_id', 'user_id')
            ->withPivot([
                'visto_en_plataforma', 
                'leido_at', 
                'canal_recibido',
                'enviado_email',
                'enviado_telegram',
                'enviado_email_at',
                'enviado_telegram_at'
            ])
            ->withTimestamps();
    }

    /**
     * Scope para obtener solo notificaciones activas y publicadas
     */
    public function scopeActivas($query)
    {
        return $query->where('activa', true)
            ->where(function ($q) {
                $q->whereNull('fecha_publicacion')
                    ->orWhere('fecha_publicacion', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('fecha_caducidad')
                    ->orWhere('fecha_caducidad', '>=', now());
            });
    }

    /**
     * Verifica si un usuario ha leído la notificación
     */
    public function fueLeida(User $user): bool
    {
        return $this->vistas()
            ->where('user_id', $user->id)
            ->wherePivot('visto_en_plataforma', true)
            ->exists();
    }

    /**
     * Marca como leída por un usuario
     */
    public function marcarComoLeida(User $user, string $canal = 'plataforma'): void
    {
        $this->vistas()->syncWithoutDetaching([
            $user->id => [
                'visto_en_plataforma' => true,
                'leido_at' => now(),
                'canal_recibido' => $canal,
            ]
        ]);
    }

    /**
     * Cuenta envíos por canal
     */
    public function contarEnviosEmail(): int
    {
        return $this->vistas()
            ->wherePivot('enviado_email', true)
            ->count();
    }

    public function contarEnviosTelegram(): int
    {
        return $this->vistas()
            ->wherePivot('enviado_telegram', true)
            ->count();
    }

    public function contarLeidasPortal(): int
    {
        return $this->vistas()
            ->wherePivot('visto_en_plataforma', true)
            ->count();
    }

    public function contarTotalDestinatarios(): int
    {
        return $this->vistas()->count();
    }
}
