<?php

namespace App\Filament\Resources\TrabajadorResource\Pages;

use Filament\Actions\DeleteAction;
use App\Filament\Resources\TrabajadorResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTrabajador extends EditRecord
{
    protected static string $resource = TrabajadorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
{
    return Notification::make()
        ->title('💾 Cambios guardados')
        ->body('La información del trabajador ha sido actualizada con éxito.')
        ->success()
        ->icon('icon-f-city-worker')
        ->persistent();
}




}
