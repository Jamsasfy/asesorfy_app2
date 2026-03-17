<?php

namespace App\Filament\Resources\TrabajadorResource\Pages;

use Illuminate\Database\Eloquent\Model;
use App\Filament\Resources\TrabajadorResource;
use App\Models\User;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Mail\BienvenidaTrabajadorMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class CreateTrabajador extends CreateRecord
{
    protected static string $resource = TrabajadorResource::class;

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function handleRecordCreation(array $data): Model
    {
        // Extraer roles antes de crear
        $roles = $data['roles'] ?? [];
        unset($data['roles']);
        
        $trabajador = parent::handleRecordCreation($data);

        // Los roles ya se sincronizan automáticamente con saveRelationshipsUsing
        // Solo mostramos notificación de éxito
        Notification::make()
            ->title('✅ Trabajador creado correctamente')
            ->body('El trabajador ha sido creado y sus roles asignados.')
            ->icon('icon-f-city-worker')
            ->color('success')
            ->send();

        return $trabajador;
    }
}
