<?php

namespace App\Filament\Portal\Resources\Facturas\Pages;

use App\Filament\Portal\Resources\Facturas\FacturaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFactura extends CreateRecord
{
    protected static string $resource = FacturaResource::class;
}
