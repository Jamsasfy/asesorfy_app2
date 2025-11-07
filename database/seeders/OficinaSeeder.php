<?php

namespace Database\Seeders;

use App\Models\Oficina;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OficinaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
       public function run(): void
    {
        Oficina::create(['nombre' => 'Chiclana de la Frontera']);
    }
}
