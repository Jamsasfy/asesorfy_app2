<?php

namespace App\Filament\Resources\ServicioResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\ServicioResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditServicio extends EditRecord
{
    protected static string $resource = ServicioResource::class;

     protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
