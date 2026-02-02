<?php

namespace App\Notifications;

use App\Models\Cliente;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClienteTienePendientesDeRespuesta extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Cliente $cliente,
        public int $count,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    protected function portalUrl(): string
    {
        return route('filament.portal.resources.documentos.index', [
            'tab' => 'requiere_atencion',
        ]);
    }

    public function toMail($notifiable): MailMessage
    {
        $url = $this->portalUrl();

        return (new MailMessage)
            ->subject('Tienes documentos pendientes')
            ->view('emails.portal.cliente-pendientes-respuesta', [
                'url' => $url,
                'userName' => $notifiable?->name,
            ]);
    }

    /**
     * ✅ IMPORTANTE: Filament (campanita) necesita el formato de database message de Filament.
     */
    public function toDatabase($notifiable): array
    {
        return FilamentNotification::make()
            ->title('Tienes documentos pendientes')
            ->body('Tienes documentos pendientes de respuesta en el portal.')
            ->icon('heroicon-o-bell-alert')
            ->iconColor('info')
            ->actions([
                Action::make('ver_documentos')
                    ->label('Ver documentos')
                    ->url($this->portalUrl())
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    /**
     * (Opcional) Laravel genérico. Puedes dejarlo, pero Filament usa toDatabase().
     */
    public function toArray($notifiable): array
    {
        return [
            'title' => 'Tienes documentos pendientes',
            'body'  => 'Tu Asesor te ha enviado documentos pendientes de respuesta.',
            'url'   => $this->portalUrl(),
            'cliente_id' => $this->cliente->id,
            'count' => $this->count,
        ];
    }
}
