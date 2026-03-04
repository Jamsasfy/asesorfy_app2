<?php

namespace App\Filament\Resources\ClienteSuscripcionResource\Pages;

use App\Filament\Resources\ClienteSuscripcionResource;
use App\Filament\Resources\ClienteSuscripcionResource\Widgets\SuscripcionesTemperatura;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListClienteSuscripcions extends ListRecords
{
    protected static string $resource = ClienteSuscripcionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //CreateAction::make(),
        ];
    }

    /**
     * ✅ Cards / KPIs arriba del listado
     */
    public function getHeaderWidgets(): array
    {
        return [
            SuscripcionesTemperatura::class,
        ];
    }

    /**
     * ✅ Columnas del grid de widgets
     */
    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
