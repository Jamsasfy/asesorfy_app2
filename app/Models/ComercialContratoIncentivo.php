<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ComercialContratoIncentivo extends Model
{
    protected $table = 'comercial_contratos_incentivos';

    protected $fillable = [
        'comercial_id',
        'tipo',
        'contrato_base_id',
        'reglas_snapshot',
        'config_snapshot',
        'fecha_envio',
        'fecha_firma',
        'token_firma',
        'pdf_path',
        'hash_documento',
        'ip_firma',
        'user_agent_firma',
    ];

    protected $casts = [
        'reglas_snapshot' => 'array',
        'config_snapshot' => 'array',
        'fecha_envio'     => 'datetime',
        'fecha_firma'     => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($contrato) {
            if (!$contrato->token_firma) {
                $contrato->token_firma = Str::random(64);
            }
        });
    }

    // Relaciones

    public function comercial(): BelongsTo
    {
        return $this->belongsTo(User::class, 'comercial_id');
    }

    public function contratoBase(): BelongsTo
    {
        return $this->belongsTo(ComercialContratoIncentivo::class, 'contrato_base_id');
    }

    public function anexos(): HasMany
    {
        return $this->hasMany(ComercialContratoIncentivo::class, 'contrato_base_id');
    }

    // Métodos helper

    public function estaFirmado(): bool
    {
        return !is_null($this->fecha_firma);
    }

    public function estaPendiente(): bool
    {
        return is_null($this->fecha_firma);
    }

    public function esBase(): bool
    {
        return $this->tipo === 'base';
    }

    public function esAnexo(): bool
    {
        return $this->tipo === 'anexo';
    }

    public function getUrlFirma(): string
    {
        return route('firmar-contrato-incentivos', ['token' => $this->token_firma]);
    }

    public function getPdfUrl(): ?string
    {
        if (!$this->pdf_path) {
            return null;
        }

        return \Storage::url($this->pdf_path);
    }
}
