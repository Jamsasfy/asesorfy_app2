<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use App\Mail\BienvenidaTrabajadorMail; // Asumo que este es el Mailable que creamos
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;


class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

   protected function handleRecordUpdate(Model $record, array $data): Model
{
    // --- LÓGICA ORIGINAL (Contraseña y Guardado) ---
    $passwordUpdated = filled($data['password'] ?? null);
    $record->update($data);
    
    // --- LÓGICA DE EMAIL (BUENA PRÁCTICA CON "FLAG") ---

    // 1. Recargamos el usuario por si acaso
    $record->refresh(); 

    // 2. Definimos las condiciones (Tu lógica)
    $isTrabajador = $record->trabajador()->exists();
    $hasRolesNow = $record->roles()->exists();
    $emailYaEnviado = $record->email_bienvenida_enviado; // El nuevo flag

    // 3. Comprobamos (Lógica más simple y segura)
    // "Si es trabajador Y tiene roles Y NUNCA hemos enviado el email"
    if ($isTrabajador && $hasRolesNow && !$emailYaEnviado) {
        
        Log::info('CONDICIÓN (FLAG) CUMPLIDA. Intentando enviar email...');
        
        try {
            // 4. Enviamos el email
            Mail::to($record->email)->send(new BienvenidaTrabajadorMail($record));

            // 5. ⚡️ ¡MUY IMPORTANTE! Marcamos el flag (Seguridad)
            // Para no volver a enviarlo NUNCA MÁS.
            $record->email_bienvenida_enviado = true;
            $record->saveQuietly(); // (Guardamos sin disparar más eventos)

            Log::info('ÉXITO (FLAG): Email de bienvenida enviado y flag marcado.');

        } catch (\Exception $e) {
            Log::error('ERROR (FLAG): Fallo al enviar email: ' . $e->getMessage());
        }
    } else {
         Log::info('CONDICIÓN (FLAG) NO CUMPLIDA. No se envía email.');
    }
    
    // ... (Tu notificación de contraseña actualizada) ...
    if ($passwordUpdated) {
        Notification::make()
            ->title('Contraseña actualizada')
            ->body('...')
            ->success()
            ->duration(3000)
            ->send();
    }

    return $record;
}
}
