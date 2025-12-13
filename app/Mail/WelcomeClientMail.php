<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeClientMail extends Mailable
{
    use Queueable, SerializesModels;

    public $nombreCliente;

    public function __construct($cliente)
    {
        // Aceptamos objeto cliente o lead, sacamos el nombre
        $this->nombreCliente = $cliente->razon_social ?? $cliente->nombre ?? 'Cliente';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bienvenido a ' . config('app.name') . ' - Próximos pasos',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome_client',
        );
    }
}