<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LeadEstadoEnum: string implements HasLabel
{
    case SIN_GESTIONAR          = 'sin_gestionar';
    case INTENTO_CONTACTO       = 'intento_contacto';
    case CONTACTADO             = 'contactado';
    case ANALISIS_NECESIDADES   = 'analisis_necesidades';
    case ESPERANDO_INFORMACION  = 'esperando_informacion';
    case PROPUESTA_ENVIADA      = 'propuesta_enviada';
    case EN_NEGOCIACION         = 'en_negociacion';

    // 🎯 Nuevos estados de conversión (flujo acordado)
    case CONVERTIDO                 = 'convertido';                 // disparador (elige manual/automático)
    case CONVERTIDO_ESPERA_DATOS    = 'convertido_espera_datos';    // automático: esperando formulario
    case CONVERTIDO_ESPERA_FIRMA    = 'convertido_espera_firma';    // manual/auto: contrato enviado, esperando firma
    case CONVERTIDO_FIRMADO         = 'convertido_firmado';         // ✅ final (venta OK)

    case DESCARTADO             = 'descartado';

    public function getLabel(): string
    {
        return match ($this) {
            self::SIN_GESTIONAR         => 'Sin Gestionar',
            self::INTENTO_CONTACTO      => 'Intento Contacto',
            self::CONTACTADO            => 'Contactado',
            self::ANALISIS_NECESIDADES  => 'Análisis de Necesidades',
            self::ESPERANDO_INFORMACION => 'Esperando Información',
            self::PROPUESTA_ENVIADA     => 'Propuesta Enviada',
            self::EN_NEGOCIACION        => 'En Negociación',

            self::CONVERTIDO               => 'Convertido (inicia proceso)',
            self::CONVERTIDO_ESPERA_DATOS  => 'Convertido · Espera datos',
            self::CONVERTIDO_ESPERA_FIRMA  => 'Convertido · Espera firma',
            self::CONVERTIDO_FIRMADO       => 'Convertido · Firmado',

            self::DESCARTADO            => 'Descartado',
        };
    }

    /**
     * Estados finales reales del lead.
     * Ahora SOLO: CONVERTIDO_FIRMADO y DESCARTADO.
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::CONVERTIDO_FIRMADO, self::DESCARTADO => true,
            default => false,
        };
    }

    /**
     * “Convertido” en sentido amplio para no romper
     * estadísticas/controles existentes que usan isConvertido().
     */
    public function isConvertido(): bool
    {
        return match ($this) {
            self::CONVERTIDO,
            self::CONVERTIDO_ESPERA_DATOS,
            self::CONVERTIDO_ESPERA_FIRMA,
            self::CONVERTIDO_FIRMADO => true,
            default => false,
        };
    }

    public function isEnProgreso(): bool
    {
        return match ($this) {
            self::INTENTO_CONTACTO,
            self::CONTACTADO,
            self::ANALISIS_NECESIDADES,
            self::ESPERANDO_INFORMACION,
            self::PROPUESTA_ENVIADA,
            self::EN_NEGOCIACION => true,
            default => false,
        };
    }

    public function isInicial(): bool
    {
        return match ($this) {
            self::SIN_GESTIONAR => true,
            default => false,
        };
    }

    // 🔸 Helpers útiles (por si los necesitas en condiciones/visibilidad)
    public function isConvertidoInicio(): bool
    {
        return $this === self::CONVERTIDO;
    }

    public function isConvertidoTransicion(): bool
    {
        return in_array($this, [
            self::CONVERTIDO_ESPERA_DATOS,
            self::CONVERTIDO_ESPERA_FIRMA,
        ], true);
    }

    public function isConvertidoFirmado(): bool
    {
        return $this === self::CONVERTIDO_FIRMADO;
    }
}
