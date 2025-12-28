<?php

namespace App\Filament\Resources\VariableConfiguracionResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\VariableConfiguracionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVariableConfiguracions extends ListRecords
{
    protected static string $resource = VariableConfiguracionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
