<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Departamento;

class DepartamentoSeeder extends Seeder
{
    public function run(): void
    {
        Departamento::insert([
            ['nombre' => 'Comercial'],
            ['nombre' => 'Asesoría'],
            ['nombre' => 'Administración'],
        ]);
    }
}