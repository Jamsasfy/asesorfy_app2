<?php

namespace App\Mail;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UsuarioVinculadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Cliente $cliente,
        public User $user,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Nuevo acceso concedido - {$this->cliente->razon_social}",
        );
    }

    public function content(): Content
    {
        $portalUrl = route('filament.portal.auth.login');

        return new Content(
            view: 'emails.usuario_vinculado',
            with: [
                'userName'    => $this->user->name,
                'razonSocial' => $this->cliente->razon_social,
                'portalUrl'   => $portalUrl,
            ],
        );
    }
}
