<?php

namespace App\Mail;

use App\Models\NotificacionPortal;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificacionPortalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public NotificacionPortal $notificacion,
        public User $usuario,
    ) {}

    public function envelope(): Envelope
    {
        $tipoLabel = match($this->notificacion->tipo) {
            'info' => 'Información',
            'aviso' => 'Aviso Importante',
            'urgente' => 'URGENTE',
            'critico' => '🔴 CRÍTICO',
            default => 'Notificación',
        };

        return new Envelope(
            subject: "[AsesorFy] {$tipoLabel}: {$this->notificacion->titulo}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notificacion_portal',
        );
    }
}
