<?php

namespace App\Observers;

use Filament\Actions\Action;
use App\Enums\ClienteEstadoEnum;
use App\Models\Cliente;
use App\Models\User;
use Filament\Notifications\Notification; // <-- Importante añadir este 'use'
use App\Filament\Resources\ClienteResource; // <-- Y este también

class ClienteObserver
{
    /**
     * Se ejecuta ANTES de guardar los cambios en la base de datos.
     */
    public function saving(Cliente $cliente): void
    {
        // Si se está asignando un asesor y el cliente estaba esperando esa asignación...
        if ($cliente->isDirty('asesor_id') && !is_null($cliente->asesor_id) && $cliente->getOriginal('estado') === ClienteEstadoEnum::PENDIENTE_ASIGNACION) {
            
            // ...modificamos el estado en el objeto. Se guardará junto con el asesor_id.
            $cliente->estado = ClienteEstadoEnum::ACTIVO;
        }
    }

    /**
     * Se ejecuta DESPUÉS de que los cambios se han guardado.
     */
    public function updated(Cliente $cliente): void
    {
        // Si el 'asesor_id' acaba de cambiar y el estado original era el correcto...
        if ($cliente->wasChanged('asesor_id') && !is_null($cliente->asesor_id) && $cliente->getOriginal('estado') === ClienteEstadoEnum::PENDIENTE_ASIGNACION) {
            
            // Notificamos al nuevo asesor asignado.
            $asesorAsignado = User::find($cliente->asesor_id);
            if ($asesorAsignado) {
                Notification::make()
                    ->title('¡Nuevo cliente asignado!')
                    ->body("Se te ha asignado el cliente '{$cliente->razon_social}'.")
                    ->icon('heroicon-o-user-group')
                    // ▼▼▼ BLOQUE AÑADIDO ▼▼▼
                    ->actions([
                        Action::make('view')
                            ->label('Ver Cliente')
                            ->url(ClienteResource::getUrl('view', ['record' => $cliente]))
                            ->markAsRead()
                            ->close(),
                    ])
                    ->sendToDatabase($asesorAsignado);
            }
        }
    }
}