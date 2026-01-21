<?php

namespace App\Enums;

enum DocumentoEstadoEnum: string
{
    case PENDIENTE  = 'pendiente';
    case VERIFICADO = 'verificado';
    case RECHAZADO  = 'rechazado';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE  => 'Pendiente',
            self::VERIFICADO => 'Verificado',
            self::RECHAZADO  => 'Rechazado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDIENTE  => 'warning',
            self::VERIFICADO => 'success',
            self::RECHAZADO  => 'danger',
        };
    }
}
