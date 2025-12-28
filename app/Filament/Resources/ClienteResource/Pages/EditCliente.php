<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use App\Filament\Resources\ClienteResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;


class EditCliente extends EditRecord
{
    protected static string $resource = ClienteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
            ->label('Eliminar cliente')
            ->icon('heroicon-o-trash'),
            ViewAction::make()
            ->label('Ver ficha cliente')
            ->icon('icon-customer')
            ->color('primary'),
        ];
    }

    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (
            empty($data['razon_social']) &&
            (empty($data['nombre']) || empty($data['apellidos']))
        ) {
            Notification::make()
            ->title('❌ Falta información clave')
            ->body('Debes rellenar nombre y apellidos o razón social. Si es autónomo, emplead@ del hogar... nombre y apellidos es obligatorio. Si es empresa y otra figura jurídica, razón social es obligatorio.')
            ->danger() // Estilo rojo
            ->persistent() // No se cierra automáticamente
            ->send();

        // Cancelar el guardado devolviendo los mismos datos sin error
        // Esto evita la excepción pero no guarda nada
        $this->halt(); // <- ⚠️ Esto detiene el guardado
        }

            // ✅ Autocompletamos razón social si falta
    if (empty($data['razon_social']) && !empty($data['nombre']) && !empty($data['apellidos'])) {
        $data['razon_social'] = $data['nombre'] . ' ' . $data['apellidos'];
    }


    

        return $data;
    }


}
