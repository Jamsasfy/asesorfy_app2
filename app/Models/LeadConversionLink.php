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
        return $this->expires_at !== null && now()->greaterThan($this->expires_at);
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function scopeActive($q)
    {
        return $q->whereNull('used_at')
                 ->where(function ($qq) {
                     $qq->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                 });
    }

     /**
     * Crea un link de conversión estándar para este lead (formulario 1: alta autónomo recurrente).
     */
    public static function createForLead(Lead $lead, string $formType = 'alta_autonomo_fiscal_recurrente'): self
{
    return self::create([
        'lead_id'    => $lead->id,
        'token'      => (string) \Illuminate\Support\Str::uuid(),
        'expires_at' => now()->addDays(7),
        'mode'       => 'automatic', // 👈 o el valor que uses en tu enum/campo
        'meta'       => [
            'form_type' => $formType,
        ],
    ]);
}



}
