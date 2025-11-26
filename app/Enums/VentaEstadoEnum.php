<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Colors\Color;

enum VentaEstadoEnum: string implements HasLabel, HasColor
{
    case PENDIENTE  = 'pendiente';
    case COMPLETADA = 'completada';
    case CANCELADA  = 'cancelada';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDIENTE  => 'Pendiente de Cierre',
            self::COMPLETADA => 'Venta Cerrada',
            self::CANCELADA  => 'Cancelada',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDIENTE  => Color::Amber,
            self::COMPLETADA => Color::Green,
            self::CANCELADA  => Color::Red,
        };
    }
}
