<?php

namespace App\Filament\Resources\ComentarioResource\Pages;

use Filament\Actions\CreateAction;
use App\Filament\Resources\ComentarioResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListComentarios extends ListRecords
{
    protected static string $resource = ComentarioResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
