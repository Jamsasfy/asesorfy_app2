<?php

namespace App\Filament\Resources\NotificacionPortalResource\Pages;

use App\Filament\Resources\NotificacionPortalResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ViewNotificacionPortal extends ViewRecord
{
    protected static string $resource = NotificacionPortalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('enviar')
                ->label('📤 Enviar ahora')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Enviar notificación')
                ->modalDescription('¿Estás seguro de que quieres enviar esta notificación a los destinatarios seleccionados?')
                ->action(function () {
                    dispatch(new \App\Jobs\EnviarNotificacionPortalJob($this->record));
                    
                    Notification::make()
                        ->title('✅ Notificación enviándose')
                        ->body('La notificación se está enviando a los destinatarios.')
                        ->success()
                        ->send();
                })
                ->visible(fn () => $this->record->activa && !$this->record->enviada),
            
            EditAction::make(),
        ];
    }
}
