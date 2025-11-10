<?php

namespace App\Mail;

use App\Models\User; // Asegúrate que esta es la ruta a tu modelo User
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class BienvenidaTrabajadorMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $accessUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
        // BUENA PRÁCTICA: Obtenemos la URL raíz de tu app
        $this->accessUrl = URL::route('filament.admin.auth.login');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '¡Bienvenido a AsesorFy!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            // Usaremos esta vista
            view: 'emails.bienvenida-trabajador',
        );
    }
}