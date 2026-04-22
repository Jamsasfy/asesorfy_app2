<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EmailPlantillaComercial;

class EmailPlantillasComercialSeeder extends Seeder
{
    public function run(): void
    {
        $plantillas = [
            [
                'codigo'   => 'comision_no_minimo',
                'nombre'   => 'No alcanza mínimo mensual',
                'asunto'   => '{{mes}} {{año}} - No has alcanzado el mínimo',
                'asunto_rrhh' => null,
                'contenido_html' => '<p>Hola {{comercial_nombre}},</p>
<p>Te informamos que en el mes de <strong>{{mes}} {{año}}</strong> no has alcanzado el mínimo requerido para comisionar.</p>
<p><strong>Resumen:</strong></p>
<ul>
<li>Facturación neta: {{facturacion_neta}}</li>
<li>Mínimo requerido: {{minimo_requerido}}</li>
<li>Diferencia: {{diferencia}}</li>
</ul>
<p>Meses consecutivos sin alcanzar mínimo: {{meses_consecutivos_sin_minimo}}</p>
<p>Este email constituye un <strong>aviso formal</strong> según política de la empresa.</p>
<p>Cualquier duda, contacta con tu coordinador.</p>
<p>Saludos,<br>Equipo AsesorFy</p>',
                'contenido_html_rrhh' => null,
                'variables_disponibles' => [
                    'comercial_nombre',
                    'mes',
                    'año',
                    'facturacion_neta',
                    'minimo_requerido',
                    'diferencia',
                    'meses_consecutivos_sin_minimo',
                ],
                'activa' => true,
            ],
            [
                'codigo'   => 'comision_positivo',
                'nombre'   => 'Resultado positivo - Comisión calculada',
                'asunto'   => '¡Enhorabuena! Incentivo de {{mes}} {{año}}: {{total_comision}}',
                'asunto_rrhh' => null,
                'contenido_html' => '<p>Hola {{comercial_nombre}},</p>
<p>¡Enhorabuena! Has alcanzado los objetivos en <strong>{{mes}} {{año}}</strong>.</p>
<p><strong>Resumen de tu incentivo:</strong></p>
<ul>
<li>Facturación neta: {{facturacion_neta}}</li>
<li>Mínimo requerido: {{minimo_requerido}}</li>
<li>Base comisionable: {{base_comisionable}}</li>
<li>Porcentaje: {{porcentaje}}%</li>
<li><strong>Comisión calculada: {{total_comision}}</strong></li>
</ul>
<p>Este importe se pagará en la nómina del mes siguiente tras la aprobación del coordinador.</p>
<p>¡Sigue así!</p>
<p>Saludos,<br>Equipo AsesorFy</p>',
                'contenido_html_rrhh' => null,
                'variables_disponibles' => [
                    'comercial_nombre',
                    'mes',
                    'año',
                    'facturacion_neta',
                    'minimo_requerido',
                    'base_comisionable',
                    'porcentaje',
                    'total_comision',
                ],
                'activa' => true,
            ],
            [
                'codigo'   => 'despido_aviso',
                'nombre'   => 'Aviso automático - Condiciones de despido',
                'asunto'   => 'IMPORTANTE - Aviso formal por objetivos',
                'asunto_rrhh' => 'ALERTA - Comercial {{comercial_nombre}} cumple condiciones de despido',
                'contenido_html' => '<p>Hola {{comercial_nombre}},</p>
<p>Este es un <strong>aviso formal</strong>.</p>
<p>Has cumplido las condiciones que según política de empresa constituyen motivo de despido:</p>
<ul>
<li>{{condicion_cumplida}}</li>
</ul>
<p>Por favor, ponte en contacto con RRHH a la mayor brevedad.</p>
<p>Saludos,<br>Equipo AsesorFy</p>',
                'contenido_html_rrhh' => '<p>Hola,</p>
<p>El comercial <strong>{{comercial_nombre}}</strong> ha cumplido las condiciones de despido automático:</p>
<ul>
<li>{{condicion_cumplida}}</li>
</ul>
<p>Meses sin alcanzar mínimo:</p>
<ul>
<li>Consecutivos: {{meses_consecutivos}}</li>
<li>Alternos en {{periodo_meses}} meses: {{meses_alternos}}</li>
</ul>
<p>Se requiere acción de RRHH.</p>
<p>Sistema AsesorFy</p>',
                'variables_disponibles' => [
                    'comercial_nombre',
                    'condicion_cumplida',
                    'meses_consecutivos',
                    'meses_alternos',
                    'periodo_meses',
                ],
                'activa' => true,
            ],
        ];

        foreach ($plantillas as $plantilla) {
            EmailPlantillaComercial::updateOrCreate(
                ['codigo' => $plantilla['codigo']],
                $plantilla
            );
        }
    }
}
