<?php

namespace App\Filament\Resources\NotificacionPortalResource\Pages;

use App\Filament\Resources\NotificacionPortalResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Filament\Actions\Action;

class CreateNotificacionPortal extends CreateRecord
{
    protected static string $resource = NotificacionPortalResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        
        return $data;
    }

    protected function getFormActions(): array
    {
        return [
            // Botón por defecto "Crear" (sin enviar)
            $this->getCreateFormAction(),
            
            // Botón nuevo "Guardar y Enviar"
            Action::make('create_and_send')
                ->label('💾 Guardar y Enviar Inmediatamente')
                ->action('createAndSend')
                ->color('success')
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn () => auth()->user()?->can('Enviar:NotificacionPortal') ?? false),
            
            $this->getCancelFormAction(),
        ];
    }

    public function createAndSend(): void
    {
        // Validar formulario
        $data = $this->form->getState();
        $data['created_by'] = auth()->id();

        // Crear notificación
        $this->record = static::getModel()::create($data);

        // Enviar inmediatamente
        dispatch(new \App\Jobs\EnviarNotificacionPortalJob($this->record));

        // Marcar como enviada (se hace en el Job, pero aseguramos aquí para el redirect)
        $this->record->update([
            'enviada' => true,
            'enviada_at' => now(),
        ]);

        Notification::make()
            ->title('✅ Notificación creada y enviada')
            ->body('La notificación se está enviando a los destinatarios.')
            ->success()
            ->send();

        $this->redirect($this->getResource()::getUrl('index'));
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
