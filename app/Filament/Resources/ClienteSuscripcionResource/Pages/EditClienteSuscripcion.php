<?php

namespace App\Filament\Resources\ClienteSuscripcionResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\ClienteSuscripcionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClienteSuscripcion extends EditRecord
{
    protected static string $resource = ClienteSuscripcionResource::class;

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
