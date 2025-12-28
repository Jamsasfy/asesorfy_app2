<?php

namespace App\Filament\Resources\VentaResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\Resources\VentaResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewVenta extends ViewRecord
{
    protected static string $resource = VentaResource::class;

    // Aquí añadiremos los botones de la cabecera más adelante
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}