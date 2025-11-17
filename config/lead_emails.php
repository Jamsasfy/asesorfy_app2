<?php

use App\Enums\LeadEstadoEnum;

return [

    'states' => [

        LeadEstadoEnum::INTENTO_CONTACTO->value => [
          'slug' => 'intento_contacto',
        'auto' => true,

        // Recordatorios automáticos
        'reminders' => [
            // 1 inicial (al entrar en el estado) + 4 recordatorios
            'max_attempts' => 5,

            // índice = nº de emails ya enviados (estado_email_intentos)
            // valor   = días desde el último email hasta el siguiente
            'delays' => [
                1 => 1, // tras el primer email, mandar el 2º a partir de 1 día
                2 => 1, // tras el segundo email, mandar el 3º a partir de 1 día
                3 => 4, // tras el tercer email, mandar el 4º a partir de 4 días
                4 => 4, // tras el cuarto email, mandar el 5º (último) a partir de 4 días
            ],
        ],
    ],


        LeadEstadoEnum::ANALISIS_NECESIDADES->value => [
            'slug' => 'analisis_necesidades',
            'auto' => true,
            // Este estado NO tiene recordatorios (por ahora)
        ],

            LeadEstadoEnum::ESPERANDO_INFORMACION->value => [
            'slug' => 'esperando_informacion',
            'auto' => true,

            'reminders' => [
                'max_attempts' => 5,
                'delays' => [
                    1 => 2,  // 2º email a los 2 días
                    2 => 4,  // 3º email a los 4 días
                    3 => 7,  // 4º email a los 7 días
                    4 => 11, // 5º email a los 11 días
                ],
            ],
        ],


        LeadEstadoEnum::PROPUESTA_ENVIADA->value => [
            'slug'  => 'propuesta_enviada',
            'auto'  => true,
            // No hace falta recordatorios aquí
        ],

        LeadEstadoEnum::CONVERTIDO->value => [
            'slug' => 'convertido',
            'auto' => true,
            // Tampoco recordatorios
        ],

        // Estados sin email automático
        LeadEstadoEnum::CONTACTADO->value => [
            'slug' => 'contactado',
            'auto' => false,
        ],

        LeadEstadoEnum::EN_NEGOCIACION->value => [
            'slug' => 'en_negociacion',
            'auto' => false,
        ],

        LeadEstadoEnum::SIN_GESTIONAR->value => [
            'slug' => 'sin_gestionar',
            'auto' => false,
        ],

        LeadEstadoEnum::DESCARTADO->value => [
            'slug' => 'descartado',
            'auto' => false,
        ],
    ],

    // Si el comercial ha interactuado en las últimas X horas, no se envían recordatorios
    'manual_interaction_cooldown_hours' => 24,
];
