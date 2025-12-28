<?php

namespace App\Filament\Resources\PlantillaContratoResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\PlantillaContratoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPlantillaContratos extends ListRecords
{
    protected static string $resource = PlantillaContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
