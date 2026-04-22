<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailPlantillaComercial extends Model
{
    protected $table = 'email_plantillas_comercial';

    protected $fillable = [
        'codigo',
        'nombre',
        'asunto',
        'asunto_rrhh',
        'contenido_html',
        'contenido_html_rrhh',
        'variables_disponibles',
        'activa',
    ];

    protected $casts = [
        'variables_disponibles' => 'array',
        'activa'                => 'boolean',
    ];
}
