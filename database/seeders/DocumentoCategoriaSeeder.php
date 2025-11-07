<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentoCategoria;

class DocumentoCategoriaSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            ['nombre' => 'Fiscal'],
            ['nombre' => 'Contable'],
            ['nombre' => 'General'],
        ];

        foreach ($categorias as $categoria) {
            DocumentoCategoria::firstOrCreate(['nombre' => $categoria['nombre']], $categoria);
        }
    }
}
