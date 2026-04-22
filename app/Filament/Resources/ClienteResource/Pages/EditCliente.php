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
        $tipoCliente = $data['tipo_cliente_id'] ?? $this->record->tipo_cliente_id;

        // AUTÓNOMO: requiere nombre + apellidos
        if ($tipoCliente == 1) {
            if (empty($data['nombre']) || empty($data['apellidos'])) {
                Notification::make()
                    ->title('❌ Falta información')
                    ->body('Los autónomos deben tener nombre y apellidos.')
                    ->danger()
                    ->persistent()
                    ->send();
                $this->halt();
            }
            // Auto-rellenar razon_social
            if (empty($data['razon_social'])) {
                $data['razon_social'] = trim($data['nombre'] . ' ' . $data['apellidos']);
            }
        } else {
            // SOCIEDAD: requiere razon_social
            if (empty($data['razon_social'])) {
                Notification::make()
                    ->title('❌ Falta información')
                    ->body('Las sociedades deben tener razón social.')
                    ->danger()
                    ->persistent()
                    ->send();
                $this->halt();
            }
        }

        return $data;
    }


}
