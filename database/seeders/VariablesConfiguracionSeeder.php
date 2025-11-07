<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VariablesConfiguracionSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $rows = [
            ['nombre_variable' => 'IVA_general',                   'valor_variable' => '21',                              'tipo_dato' => 'numero_entero', 'descripcion' => 'Porcentaje de IVA general aplicable a la mayoría de servicios y productos', 'es_secreto' => 0],
            ['nombre_variable' => 'formato_factura',               'valor_variable' => 'FR{YY}-00000',                   'tipo_dato' => 'cadena',         'descripcion' => 'serie de las facturas',                                               'es_secreto' => 0],
            ['nombre_variable' => 'empresa_razon_social',          'valor_variable' => 'Asesorfy S.L.',                  'tipo_dato' => 'cadena',         'descripcion' => null,                                                                 'es_secreto' => 0],
            ['nombre_variable' => 'empresa_cif',                   'valor_variable' => 'B12345678',                      'tipo_dato' => 'cadena',         'descripcion' => null,                                                                 'es_secreto' => 0],
            ['nombre_variable' => 'empresa_direccion_calle',       'valor_variable' => 'C/ DEL ROMERIJO, 123, 4ºA',      'tipo_dato' => 'cadena',         'descripcion' => null,                                                                 'es_secreto' => 0],
            ['nombre_variable' => 'empresa_direccion_cp',          'valor_variable' => '11130',                          'tipo_dato' => 'cadena',         'descripcion' => null,                                                                 'es_secreto' => 0],
            ['nombre_variable' => 'empresa_direccion_ciudad',      'valor_variable' => 'Chiclana de la Frontera',        'tipo_dato' => 'cadena',         'descripcion' => null,                                                                 'es_secreto' => 0],
            ['nombre_variable' => 'empresa_direccion_provincia',   'valor_variable' => 'Cádiz',                          'tipo_dato' => 'cadena',         'descripcion' => null,                                                                 'es_secreto' => 0],
            ['nombre_variable' => 'empresa_logo_url',              'valor_variable' => 'images/logo.png',                'tipo_dato' => 'cadena',         'descripcion' => null,                                                                 'es_secreto' => 0],
            ['nombre_variable' => 'empresa_telefono',              'valor_variable' => '956 123 456',                    'tipo_dato' => 'cadena',         'descripcion' => null,                                                                 'es_secreto' => 0],
            ['nombre_variable' => 'empresa_email',                 'valor_variable' => 'info@asesorfy.net',              'tipo_dato' => 'cadena',         'descripcion' => null,                                                                 'es_secreto' => 0],
            ['nombre_variable' => 'empresa_web',                   'valor_variable' => 'https://www.asesorfy.net',       'tipo_dato' => 'cadena',         'descripcion' => null,                                                                 'es_secreto' => 0],
            ['nombre_variable' => 'empresa_banco_nombre',          'valor_variable' => 'Banco Ficticio S.A.',            'tipo_dato' => 'cadena',         'descripcion' => null,                                                                 'es_secreto' => 0],
            ['nombre_variable' => 'empresa_banco_iban',            'valor_variable' => 'ESXX XXXX XXXX XXXX XXXX XXXX',  'tipo_dato' => 'cadena',         'descripcion' => null,                                                                 'es_secreto' => 0],
            ['nombre_variable' => 'empresa_banco_swift',           'valor_variable' => 'FICTESFFXXX',                    'tipo_dato' => 'cadena',         'descripcion' => null,                                                                 'es_secreto' => 0],
            ['nombre_variable' => 'formato_factura_rectificativa', 'valor_variable' => 'REC{YY}-00000',                  'tipo_dato' => 'cadena',         'descripcion' => 'FORMATO FACTURA RECTIFICATIVA REC{YY}-00000',                       'es_secreto' => 0],
        ];

        // Requiere índice único en nombre_variable (lo tienes)
        DB::table('variables_configuracion')->upsert(
            array_map(function ($r) use ($now) {
                return $r + ['created_at' => $now, 'updated_at' => $now];
            }, $rows),
            ['nombre_variable'],                         // clave única
            ['valor_variable','tipo_dato','descripcion','es_secreto','updated_at'] // columnas a actualizar
        );
    }
}
