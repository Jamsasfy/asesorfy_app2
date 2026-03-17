<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Colors\Color;

enum ClienteEstadoEnum: string implements HasLabel, HasColor
{
    case PENDIENTE            = 'pendiente';
    case PENDIENTE_ASIGNACION = 'pendiente_asignacion';
    case EN_PROYECTO          = 'en_proyecto';
    case ACTIVO               = 'activo';
    case IMPAGADO             = 'impagado';
    case BLOQUEADO            = 'bloqueado';
    case RESCINDIDO           = 'rescindido';
    case BAJA                 = 'baja';
    case REQUIERE_ATENCION    = 'requiere_atencion';
    case PROYECTO_FINALIZADO  = 'proyecto_finalizado';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PENDIENTE            => 'Pendiente',
            self::PENDIENTE_ASIGNACION => 'Pendiente de Asignación',
            self::EN_PROYECTO          => 'En Proyecto',
            self::ACTIVO               => 'Activo',
            self::IMPAGADO             => 'Impagado',
            self::BLOQUEADO            => 'Bloqueado',
            self::RESCINDIDO           => 'Rescindido',
            self::BAJA                 => 'Baja',
            self::REQUIERE_ATENCION    => 'Requiere Atención',
            self::PROYECTO_FINALIZADO  => 'Proyecto Finalizado',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::PENDIENTE            => Color::Amber,
            self::PENDIENTE_ASIGNACION => Color::Orange,
            self::EN_PROYECTO          => Color::Blue,
            self::ACTIVO               => Color::Green,
            self::IMPAGADO             => Color::Red,
            self::BLOQUEADO            => Color::Gray,
            self::RESCINDIDO           => Color::Purple,
            self::BAJA                 => Color::Rose,
            self::REQUIERE_ATENCION    => Color::Yellow,
            self::PROYECTO_FINALIZADO  => Color::Slate,
        };
    }
}