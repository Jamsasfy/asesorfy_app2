<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlantillaEmailComision extends Model
{
    protected $table = 'plantillas_email_comisiones';

    protected $fillable = [
        'codigo',
        'nombre',
        'asunto',
        'contenido_html',
        'variables_disponibles',
        'activa',
    ];

    protected $casts = [
        'variables_disponibles' => 'array',
        'activa'                => 'boolean',
    ];

    public function procesarContenido(array $variables): string
    {
        $contenido = $this->contenido_html;

        foreach ($variables as $clave => $valor) {
            $contenido = str_replace('{' . $clave . '}', $valor, $contenido);
        }

        return $contenido;
    }

    public function procesarAsunto(array $variables): string
    {
        $asunto = $this->asunto;

        foreach ($variables as $clave => $valor) {
            $asunto = str_replace('{' . $clave . '}', $valor, $asunto);
        }

        return $asunto;
    }
}
