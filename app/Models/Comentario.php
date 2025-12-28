<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comentario extends Model
{

    protected $guarded = [];

    public function comentable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
//mas modelos en la busqueda en comentarios recurso de admin
    public static function getComentableModels(): array
{
    return [
        Cliente::class => 'Cliente',
       
         Lead::class => 'Lead',
       
         Proyecto::class => 'Proyecto',
    ];
}
}
