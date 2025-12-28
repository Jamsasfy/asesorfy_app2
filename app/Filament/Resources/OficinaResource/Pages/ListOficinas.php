<?php

namespace App\Filament\Resources\OficinaResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\OficinaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOficinas extends ListRecords
{
    protected static string $resource = OficinaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
