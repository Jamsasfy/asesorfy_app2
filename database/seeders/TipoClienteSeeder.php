<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TipoCliente;

class TipoClienteSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            [
                'nombre' => 'Autónomo',
                'descripcion' => 'Trabajadores por cuenta propia.',
                'activo' => true,
            ],
            [
                'nombre' => 'Sociedad Limitada',
                'descripcion' => 'Empresas con CIF.',
                'activo' => true,
            ],
            [
                'nombre' => 'Empleados del Hogar',
                'descripcion' => 'Gestión de empleada del hogar.',
                'activo' => true,
            ],
            [
                'nombre' => 'Comunidad de Bienes',
                'descripcion' => 'CB o sociedades sin personalidad jurídica.',
                'activo' => true,
            ],
        ];

        foreach ($tipos as $tipo) {
            TipoCliente::firstOrCreate(
                ['nombre' => $tipo['nombre']],
                $tipo
            );
        }
    }
}
