<?php

namespace App\Filament\Portal\Resources\Facturas\Pages;

use App\Filament\Portal\Resources\Facturas\FacturaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFacturas extends ListRecords
{
    protected static string $resource = FacturaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            
        ];
    }
}
