<?php

namespace App\Notifications;

use Filament\Facades\Filament;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class PortalResetPasswordNotification extends Notification
{
    public function __construct(private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = Filament::getPanel('portal')->getResetPasswordUrl($this->token, $notifiable);

        return (new MailMessage)
    ->subject('Restablecer contraseña — AsesorFy')
    ->view('emails.portal.reset-password', [
        'user' => $notifiable,
        'url' => $url,
        'expireMinutes' => config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60),
    ]);

    }
}
