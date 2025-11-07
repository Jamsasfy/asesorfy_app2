<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentoCategoria;
use App\Models\DocumentoSubtipo;

class DocumentoSubtipoSeeder extends Seeder
{
    public function run(): void
    {
        $subtipos = [
            'Fiscal' => [
                'Modelo 303 (IVA)',
                'Modelo 130 (IRPF)',
                'Declaración Renta',
            ],
            'Contable' => [
                'Facturas Emitidas',
                'Facturas Recibidas',
                'Extractos Bancarios',
            ],
            'General' => [
                'DNI / NIE',
                'Escrituras',
                'Contratos',
            ],
        ];

        foreach ($subtipos as $categoriaNombre => $listaSubtipos) {

            $categoria = DocumentoCategoria::where('nombre', $categoriaNombre)->first();

            if (!$categoria) continue;

            foreach ($listaSubtipos as $nombre) {
                DocumentoSubtipo::firstOrCreate([
                    'documento_categoria_id' => $categoria->id,
                    'nombre' => $nombre,
                ]);
            }
        }
    }
}
