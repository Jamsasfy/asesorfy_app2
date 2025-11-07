<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MotivoDescarte;

class MotivoDescarteSeeder extends Seeder
{
    public function run(): void
    {
        $motivos = [
            ['nombre' => 'No responde', 'descripcion' => 'No fue posible contactar al lead.', 'activo' => true],
            ['nombre' => 'Ya tiene asesor', 'descripcion' => 'Servicio contratado actualmente con otra asesoría.', 'activo' => true],
            ['nombre' => 'Precio', 'descripcion' => 'El precio no encaja en su presupuesto.', 'activo' => true],
            ['nombre' => 'Sin interés', 'descripcion' => 'No está interesado tras la presentación.', 'activo' => true],
        ];

        foreach ($motivos as $motivo) {
            MotivoDescarte::firstOrCreate(
                ['nombre' => $motivo['nombre']],
                $motivo
            );
        }
    }
}
