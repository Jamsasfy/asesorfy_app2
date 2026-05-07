<?php

namespace App\Filament\Resources\VentaResource\Pages;

use App\Filament\Resources\VentaResource;
use App\Filament\Resources\VentaResource\Widgets\AnnualSalesChart;
use Filament\Resources\Pages\ListRecords;

class ListVentas extends ListRecords
{
    protected static string $resource = VentaResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            AnnualSalesChart::class,
        ];
    }
}
