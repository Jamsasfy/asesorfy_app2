<?php

namespace App\Filament\Resources\VentaResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\VentaResource;
use App\Filament\Resources\VentaResource\Widgets\AnnualSalesChart;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVentas extends ListRecords
{
    protected static string $resource = VentaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
            ->visible(fn (): bool => auth()->user()?->can('boton_crear_venta_venta') ?? false),
        ];
    }

    protected function getHeaderWidgets(): array
{
    return [
        AnnualSalesChart::class,
        // otros widgets…
    ];
}
}
