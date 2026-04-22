<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionComisiones extends Model
{
    protected $table = 'configuracion_comisiones';

    protected $fillable = [
        'emails_notificacion_despido',
        'meses_consecutivos_despido',
        'meses_alternos_despido',
        'periodo_meses_alternos',
    ];

    protected $casts = [
        'emails_notificacion_despido' => 'array',
    ];

    public static function get(): static
    {
        return static::first() ?? static::create([
            'emails_notificacion_despido' => [],
            'meses_consecutivos_despido'  => 3,
            'meses_alternos_despido'      => 5,
            'periodo_meses_alternos'      => 12,
        ]);
    }
}
