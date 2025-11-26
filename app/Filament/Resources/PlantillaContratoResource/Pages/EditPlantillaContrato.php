<?php

namespace App\Filament\Resources\PlantillaContratoResource\Pages;

use App\Filament\Resources\PlantillaContratoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPlantillaContrato extends EditRecord
{
    protected static string $resource = PlantillaContratoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
