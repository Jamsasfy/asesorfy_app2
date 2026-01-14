<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramLink extends Model
{
    protected $fillable = [
        'cliente_id',
        'token',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];
}
