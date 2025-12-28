<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Documento extends Model
{
    protected $guarded = [];

    protected static function booted()
{
    static::deleting(function ($documento) {
        // Asegúrate de que existe el archivo antes de intentar borrarlo
        if ($documento->ruta && Storage::disk('public')->exists($documento->ruta)) {
            Storage::disk('public')->delete($documento->ruta);
        }
    });
}


    public function tipo(): BelongsTo
    {
        return $this->belongsTo(DocumentoCategoria::class, 'tipo_documento_id');
    }

    public function subtipo(): BelongsTo
    {
        return $this->belongsTo(DocumentoSubtipo::class, 'subtipo_documento_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
      // 🔗 Cliente al que pertenece el documento
      public function cliente()
      {
          return $this->belongsTo(Cliente::class);
      }
      public function documentable()
        {
            return $this->morphTo();
        }
}
