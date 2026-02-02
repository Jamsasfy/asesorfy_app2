<?php

namespace App\Filament\Portal\Resources\Facturas\Pages;

use App\Filament\Portal\Resources\Facturas\FacturaResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFactura extends ViewRecord
{
    protected static string $resource = FacturaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
