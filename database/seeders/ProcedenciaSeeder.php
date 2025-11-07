<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Procedencia;

class ProcedenciaSeeder extends Seeder
{
    public function run(): void
    {
        Procedencia::insert([
            ['procedencia' => 'Recurso propio'],
            ['procedencia' => 'Recomendado'],
        ]);
    }
}