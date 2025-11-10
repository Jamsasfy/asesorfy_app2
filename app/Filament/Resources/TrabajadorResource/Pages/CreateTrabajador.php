<?php

namespace App\Filament\Resources\TrabajadorResource\Pages;

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

    protected function getCreatedNotification(): ?\Filament\Notifications\Notification
{
    return null; // ❌ Anula la notificación por defecto
}




    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
   
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
       // 1. Dejamos que Filament haga la magia (esto SÍ funciona)
        $trabajador = parent::handleRecordCreation($data);
    
       
        
        // Mostrar toast personalizado
        \Filament\Notifications\Notification::make()
            ->title('⚠️ Trabajador creado sin rol')
            ->body('Recuerda asignarle un rol desde la sección de trabajadores o usuarios web para que pueda acceder a la plataforma.')
            ->icon('icon-f-city-worker')
            ->color('warning')
            ->persistent()
            ->send(); // <- ¡Faltaba esto!
    
        return $trabajador; // <- ¡Y esto también!
    }

   
    
}
