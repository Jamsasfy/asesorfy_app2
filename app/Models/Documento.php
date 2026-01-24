<?php

namespace App\Models;

use App\Enums\DocumentoEstadoEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Documento extends Model
{
    protected $guarded = [];

    protected $casts = [
        'verificado' => 'bool',
        'hidden_in_portal' => 'bool',

        'revisado_at' => 'datetime',
        'purged_at' => 'datetime',

        'aclaracion_at' => 'datetime',
        'aclaracion_respondida_at' => 'datetime',

        'estado' => DocumentoEstadoEnum::class,
    ];

    protected static function booted()
    {
        static::deleting(function ($documento) {
            if ($documento->ruta && Storage::disk('public')->exists($documento->ruta)) {
                Storage::disk('public')->delete($documento->ruta);
            }
        });

        // ✅ Estado por defecto según quién sube
        static::creating(function (self $documento) {
            if (! filled($documento->estado)) {
                $uploader = $documento->user;

                // Si no está cargado aún, intenta resolver por user_id
                if (! $uploader && $documento->user_id) {
                    $uploader = User::find($documento->user_id);
                }

                // Asesor/super_admin -> verificado; cliente -> pendiente
                if ($uploader && (method_exists($uploader, 'hasRole')) && ($uploader->hasRole('asesor') || $uploader->hasRole('super_admin'))) {
                    $documento->estado = DocumentoEstadoEnum::VERIFICADO;
                    $documento->revisado_at = now();
                    $documento->revisado_por_id = $uploader->id;
                } else {
                    $documento->estado = DocumentoEstadoEnum::PENDIENTE;
                }
            }

            // Compat con boolean antiguo
            $documento->verificado = $documento->estado === DocumentoEstadoEnum::VERIFICADO;
        });

        // ✅ Mantener consistencia siempre
       static::saving(function (self $documento) {
            if ($documento->estado === DocumentoEstadoEnum::VERIFICADO) {
                $documento->verificado = true;
            } else {
                $documento->verificado = false;
            }

            // Si pasa a verificado o rechazado y no hay revisado_*, lo setea
            if (in_array($documento->estado, [DocumentoEstadoEnum::VERIFICADO, DocumentoEstadoEnum::RECHAZADO], true)) {
                if (! $documento->revisado_at) {
                    $documento->revisado_at = now();
                }
                if (! $documento->revisado_por_id && auth()->id()) {
                    $documento->revisado_por_id = auth()->id();
                }
            }

            // Si deja de estar rechazado, limpia motivo (opcional, pero lógico)
            if ($documento->estado !== DocumentoEstadoEnum::RECHAZADO) {
                $documento->motivo_rechazo = null;
            }

            /*
            | ✅ Renombrado automático al clasificar
            | - Si viene de portal con nombre "cliente_documento_xxxxxx.ext"
            |   (o legacy "sin-clasificar_..._xxxxxx.ext")
            | - y el asesor ya ha puesto Tipo/Subtipo reales
            | => renombra a: tipo_subtipo_xxxxxx.ext
            */
            $nombre = (string) ($documento->nombre ?? '');

            if (preg_match('/_([a-z0-9]{6})\.(\w+)$/i', $nombre, $m)) {
                $random = strtolower($m[1]);
                $ext = strtolower($m[2]);

                // Ojo: en saving puede no estar cargada la relación aún
                $tipoNombre = $documento->relationLoaded('tipo')
                    ? $documento->tipo?->nombre
                    : \App\Models\DocumentoCategoria::find($documento->tipo_documento_id)?->nombre;

                $subtipoNombre = $documento->relationLoaded('subtipo')
                    ? $documento->subtipo?->nombre
                    : \App\Models\DocumentoSubtipo::find($documento->subtipo_documento_id)?->nombre;

                $esSinClasificar = mb_strtolower((string) $tipoNombre) === 'sin clasificar'
                    || mb_strtolower((string) $subtipoNombre) === 'pendiente de clasificar';

                $esAutoPortal = str_starts_with($nombre, 'cliente_documento_')
                    || str_starts_with($nombre, 'sin-clasificar_');

                if ($esAutoPortal && ! $esSinClasificar && filled($tipoNombre) && filled($subtipoNombre)) {
                    $tipoSlug = \Illuminate\Support\Str::slug($tipoNombre);
                    $subtipoSlug = \Illuminate\Support\Str::slug($subtipoNombre);

                    $documento->nombre = "{$tipoSlug}_{$subtipoSlug}_{$random}.{$ext}";
                }
            }

            // ✅ Calcular hash SHA256 si falta (para detectar duplicados)
                if (blank($documento->file_sha256) && filled($documento->ruta)) {
                    try {
                        $path = Storage::disk('public')->path($documento->ruta);

                        if (is_file($path)) {
                            $documento->file_sha256 = hash_file('sha256', $path);
                        }
                    } catch (\Throwable $e) {
                        // No rompemos el guardado por un hash (log opcional)
                        // logger()->warning('No se pudo calcular file_sha256', ['id' => $documento->id, 'ruta' => $documento->ruta]);
                    }
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

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por_id');
    }

        public function isPurged(): bool
    {
        return filled($this->purged_at) || empty($this->ruta);
    }
    public function purgedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'purged_by_id');
    }

        public function possibleDuplicates()
    {
        return self::query()
            ->where('cliente_id', $this->cliente_id)
            ->whereNotNull('file_sha256')
            ->where('file_sha256', $this->file_sha256)
            ->whereKeyNot($this->getKey());
    }

    public function hasPossibleDuplicate(): bool
    {
        // ✅ si ya fue revisado (ignored/confirmed), no volver a tratarlo como “posible duplicado”
        if (! blank($this->duplicate_status ?? null)) {
            return false;
        }

        return filled($this->file_sha256)
            && $this->possibleDuplicates()->exists();
    }

    /**
     * Si tú usas $record->is_duplicate en la tabla/action:
     * lo definimos aquí para que respete lo de arriba.
     */
    public function getIsDuplicateAttribute(): bool
    {
        return $this->hasPossibleDuplicate();
    }


}
