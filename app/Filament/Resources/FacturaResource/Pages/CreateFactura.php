<?php

namespace App\Filament\Resources\FacturaResource\Pages;

use App\Services\FacturacionService;
use App\Filament\Resources\FacturaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFactura extends CreateRecord
{
    protected static string $resource = FacturaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Llamamos al servicio para obtener el número de forma segura.
        $datosFactura = FacturacionService::generarSiguienteNumeroFactura();

        // Lo añadimos a los datos que se van a guardar.
        $data['serie'] = $datosFactura['serie'];
        $data['numero_factura'] = $datosFactura['numero_factura'];

        // Recalculamos los totales.
        $totales = FacturaResource::calcularTotalesDesdeItems($data['items'] ?? []);

        return array_merge($data, $totales);
    }
}