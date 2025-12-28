<?php

namespace App\Filament\Resources\ProyectoResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\ProyectoResource;
use App\Filament\Resources\ProyectoResource\Widgets\ProyectoStatsOverview;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Filters\TrashedFilter;

class ListProyectos extends ListRecords
{
    protected static string $resource = ProyectoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
    public function getFilters(): array
{
    return [
        TrashedFilter::make(), // Filtro para ver activos / papelera / todos
    ];
}

protected function getHeaderWidgets(): array
{
    return [
        ProyectoStatsOverview::class,
    ];
}


}
