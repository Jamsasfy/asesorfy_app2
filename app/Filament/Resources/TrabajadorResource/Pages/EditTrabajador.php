<?php

namespace App\Filament\Resources\TrabajadorResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use App\Filament\Resources\TrabajadorResource;
use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTrabajador extends EditRecord
{
    protected static string $resource = TrabajadorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('gestionar_reglas_comision')
                ->label('Gestionar accesos y usuario web')
                ->icon('heroicon-o-user-circle')
                ->color('warning')
                ->url(fn () => $this->record->user_id
                    ? UserResource::getUrl('edit', ['record' => $this->record->user_id])
                    : null
                )
                ->openUrlInNewTab(),

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
