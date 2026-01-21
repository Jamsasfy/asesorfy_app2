<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentoCategoria;
use App\Models\DocumentoSubtipo;

class DocumentoClasificacionBasicaSeeder extends Seeder
{
    public function run(): void
    {
        $categoria = DocumentoCategoria::updateOrCreate(
            ['nombre' => 'Sin clasificar'],
            [
                'descripcion' => 'Categoría temporal para documentos subidos por el cliente sin etiquetar.',
                'activo' => true,
                'color' => 'gray',
            ]
        );

        DocumentoSubtipo::updateOrCreate(
            [
                'documento_categoria_id' => $categoria->id,
                'nombre' => 'Pendiente de clasificar',
            ],
            [
                'descripcion' => 'Pendiente de que el asesor lo clasifique.',
                'activo' => true,
            ]
        );
    }
}
