<?php

namespace App\Filament\Resources\ServicioResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\ServicioResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServicios extends ListRecords
{
    protected static string $resource = ServicioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
