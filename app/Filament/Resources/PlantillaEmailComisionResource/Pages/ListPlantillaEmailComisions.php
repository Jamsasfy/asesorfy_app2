<?php

namespace App\Filament\Resources\PlantillaEmailComisionResource\Pages;

use App\Filament\Resources\PlantillaEmailComisionResource;
use Filament\Resources\Pages\ListRecords;

class ListPlantillaEmailComisions extends ListRecords
{
    protected static string $resource = PlantillaEmailComisionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
