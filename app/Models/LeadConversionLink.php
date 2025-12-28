<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LeadConversionLink extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
        'meta'       => 'array',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function isExpired(): bool
{
    // Si está usado, para el sistema ya no es válido aunque queden días.
    if ($this->isUsed()) {
        return true;
    }

    // Si está revocado, también lo consideramos inválido
    if ($this->isRevoked()) {
        return true;
    }

    return $this->expires_at !== null && now()->greaterThan($this->expires_at);
}


    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function scopeActive($query)
{
    return $query
        ->whereNull('used_at') // ✅ NO usados
        ->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        })
        ->where(function ($q) {
            // ✅ NO revocados (meta JSON)
            $q->whereNull('meta->revoked_at')
              ->orWhere('meta->revoked_at', '=', '');
        });
}



    public function scopeLatestForLead($q, int $leadId)
    {
        return $q->where('lead_id', $leadId)->latest('id');
    }

    /**
     * Crea un link de conversión para un lead.
     */
    public static function createForLead(Lead $lead, string $formType = 'automatic_multi', array $metaExtra = []): self
    {
        return self::create([
            'lead_id'    => $lead->id,
            'token'      => (string) Str::uuid(),
            'expires_at' => now()->addDays(7),
            'mode'       => 'automatic',
            'meta'       => array_merge([
                'form_type' => $formType,
            ], $metaExtra),
        ]);
    }

    /**
     * Regenera token: invalida el anterior y crea uno nuevo copiando meta.
     * (Recomendado para caducidad / reenvíos “limpios”)
     */
    public static function regenerateForLead(Lead $lead, ?self $previous = null, int $days = 7): self
    {
        $meta = $previous?->meta ?? [];

        if ($previous) {
            // No lo marcamos usado; simplemente lo invalidamos por caducidad
            $previous->expires_at = now()->subSecond();
            $previous->save();
        }

        return self::create([
            'lead_id'    => $lead->id,
            'token'      => (string) Str::uuid(),
            'expires_at' => now()->addDays($days),
            'mode'       => $previous?->mode ?? 'automatic',
            'meta'       => $meta,
        ]);
    }

    // App\Models\LeadConversionLink.php

public function isRevoked(): bool
{
    return !empty(data_get($this->meta, 'revoked_at'));
}

public function revoke(?int $userId = null, ?string $reason = null): void
{
    $meta = $this->meta ?? [];

    $meta['revoked_at'] = now()->toDateTimeString();
    if ($userId) {
        $meta['revoked_by_user_id'] = $userId;
    }
    if ($reason) {
        $meta['revoked_reason'] = $reason;
    }

    $this->meta = $meta;

    // lo invalidamos SIEMPRE
    $this->expires_at = now()->subSecond();
    $this->save();
}



}
