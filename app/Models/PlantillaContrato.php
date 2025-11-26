<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlantillaContrato extends Model
{
    protected $guarded = [];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
