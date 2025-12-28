<?php

namespace App\Observers;

use Filament\Actions\Action;
use App\Enums\ClienteEstadoEnum;
use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Filament\Resources\ClienteResource;
use App\Models\ClienteSuscripcion;
use App\Models\User;
use Filament\Notifications\Notification;


class ClienteSuscripcionObserver
{
    /**
     * Se ejecuta cuando una suscripción es CREADA.
     */
    public function created(ClienteSuscripcion $suscripcion): void
    {
        // Si la suscripción se crea directamente como ACTIVA...
        if ($suscripcion->estado === ClienteSuscripcionEstadoEnum::ACTIVA) {
            $this->handleSubscriptionActivation($suscripcion);
        }
    }

    /**
     * Se ejecuta cuando una suscripción es ACTUALIZADA.
     */
    public function updated(ClienteSuscripcion $suscripcion): void
    {
        // Si el estado de la suscripción CAMBIA a ACTIVA...
        if ($suscripcion->wasChanged('estado') && $suscripcion->estado === ClienteSuscripcionEstadoEnum::ACTIVA) {
            $this->handleSubscriptionActivation($suscripcion);
        }
    }

    /**
     * Lógica central para manejar la activación de una suscripción principal.
     * Pone al cliente pendiente de asignación y notifica a los coordinadores.
     */
    private function handleSubscriptionActivation(ClienteSuscripcion $suscripcion): void
    {
        // Solo actuamos si es la tarifa principal del cliente
        if (!$suscripcion->es_tarifa_principal) {
            return;
        }

        $cliente = $suscripcion->cliente;

        // Si el cliente no existe o ya está activo, no hacemos nada
        if (!$cliente || $cliente->estado === ClienteEstadoEnum::ACTIVO) {
            return;
        }

        // 1. Establecemos la fecha de alta y lo ponemos pendiente de asignación
        $cliente->update([
            'estado' => ClienteEstadoEnum::PENDIENTE_ASIGNACION,
            'fecha_alta' => now(),
        ]);

        // 2. Notificamos a los coordinadores
        $coordinadores = User::whereHas('roles', fn ($q) => $q->where('name', 'coordinador'))->get();
        if ($coordinadores->isNotEmpty()) {
            Notification::make()
                ->title('Cliente listo para asignar')
                ->body("El cliente '{$cliente->razon_social}' necesita un asesor.")
                ->actions([Action::make('view')->label('Ver Cliente')->url(ClienteResource::getUrl('view', ['record' => $cliente]))])
                ->sendToDatabase($coordinadores);
        }
    }
}