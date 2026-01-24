<?php

namespace App\Enums;

enum DocumentoEstadoEnum: string
{
    case PENDIENTE           = 'pendiente';
    case VERIFICADO          = 'verificado';
    case NECESITA_ACLARACION = 'necesita_aclaracion';
    case RECHAZADO           = 'rechazado';
    case ARCHIVADO           = 'archivado';

    public function label(): string
    {
        return match ($this) {
            self::PENDIENTE           => 'Pendiente',
            self::VERIFICADO          => 'Verificado',
            self::NECESITA_ACLARACION => 'Necesita aclaración',
            self::RECHAZADO           => 'Rechazado',
            self::ARCHIVADO           => 'Archivado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDIENTE           => 'warning',
            self::VERIFICADO          => 'success',
            self::NECESITA_ACLARACION => 'info',
            self::RECHAZADO           => 'danger',
            self::ARCHIVADO           => 'gray',
        };
    }

        public function requiresClientAction(): bool
    {
        return $this === self::NECESITA_ACLARACION;
    }


    public function isClosed(): bool
    {
        return $this === self::ARCHIVADO;
    }
}
