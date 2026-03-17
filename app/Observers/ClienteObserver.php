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

        // Verificar si tiene proyectos bloqueantes pendientes
        $tieneProyectosBloqueantes = $cliente->ventas()
            ->whereHas('items', fn ($q) => $q->where('bloquea_recurrente', true))
            ->whereHas('proyectos', fn ($q) => $q->whereNotIn('estado', [
                \App\Enums\ProyectoEstadoEnum::Finalizado->value,
                \App\Enums\ProyectoEstadoEnum::Cancelado->value,
            ]))
            ->exists();

        // Solo pasa a activo si NO hay proyectos bloqueantes pendientes
        if (!$tieneProyectosBloqueantes) {
            $cliente->estado = ClienteEstadoEnum::ACTIVO;
        }
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

                // Comentario automático
                $cliente->comentarios()->create([
                    'user_id'   => 9999,
                    'contenido' => '👤 Asesor definitivo asignado: ' . $asesorAsignado->name . ' — cliente pasado a Activo.',
                ]);

                // Pasar cliente a ACTIVO
                $cliente->updateQuietly(['estado' => ClienteEstadoEnum::ACTIVO]);
            }
        }
    }
}