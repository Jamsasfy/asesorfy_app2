<?php

namespace App\Filament\Portal\Pages\Auth;

use App\Notifications\PortalResetPasswordNotification;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Password;

class RequestPasswordReset extends BaseRequestPasswordReset
{
    public function request(): void
    {
        $data = $this->form->getState();

        $status = Password::broker('portal')
            ->sendResetLink(
                ['email' => $data['email']],
                function ($user, string $token): void {
                    $user->notify(new PortalResetPasswordNotification($token));
                }
            );

        if ($status !== Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));
            return;
        }

        $this->form->fill();

        Notification::make()
            ->success()
            ->title(__($status))
            ->send();
    }
}
