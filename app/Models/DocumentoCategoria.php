<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentoCategoria extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'descripcion', 'activo', 'color'];

    public function subtipos(): HasMany
{
    return $this->hasMany(DocumentoSubtipo::class, 'documento_categoria_id');
}

}
