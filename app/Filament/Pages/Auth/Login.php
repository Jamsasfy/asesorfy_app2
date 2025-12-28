<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Http\Responses\LoginResponse;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Alignment;



class Login extends \Filament\Auth\Pages\Login
{
    public bool $remember = false;

    public function authenticate(): ?LoginResponse
    {
        $state = $this->form->getState();

        $credentials = [
            'email' => $state['email'],
            'password' => $state['password'],
        ];
    
        if (! Auth::attempt($credentials, $this->remember)) {
            $this->throwFailureValidationException();
        }
    
        $user = Auth::user();
    
        if (! $user->acceso_app) {
            Auth::logout();
    
           /*  $this->throwFailureValidationException([
                'email' => 'Tu cuenta está desactivada. Contacta con un administrador.',
            ]); */

            Notification::make()
            ->title('⛔ Acceso desactivado')
            ->body('Tu cuenta está desactivada. Contacta con un administrador o directament al email info@asesorfy.net.')
            ->icon('heroicon-o-exclamation-triangle') // También puedes probar: heroicon-o-shield-exclamation
            ->color('danger')
            ->duration(6000)
            ->persistent() // El usuario debe cerrarla manualmente
            ->send();

            return null;
        }
    
        session()->regenerate();
    
        return app(LoginResponse::class);
    }
}
