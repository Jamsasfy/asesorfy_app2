<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Trabajador extends Model
{
    protected $guarded = [];

      // Relación con el modelo User
      public function user(): BelongsTo
      {
          return $this->belongsTo(User::class);
      }
  
      // Relación con el modelo Oficina
      public function oficina() :BelongsTo
      {
          return $this->belongsTo(Oficina::class);
      }
  
     // Un trabajador pertenece a un departamento
public function departamento(): BelongsTo
{
    return $this->belongsTo(Departamento::class);
}

// Accesor para obtener el coordinador fácilmente
public function getCoordinadorAttribute(): ?User
{
    return $this->departamento?->coordinador;
}

      protected static function booted()
{
    static::deleting(function ($trabajador) {
        if ($trabajador->user) {
            $trabajador->user->delete();
        }
    });
}


}
