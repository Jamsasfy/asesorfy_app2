<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CorreoDePrueba extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Propiedad pública para pasar datos a la vista.
     */
    public $nombreUsuario;

    /**
     * Create a new message instance.
     *
     * @param string $nombre El dato que queremos pasar
     */
    public function __construct(string $nombre)
    {
        $this->nombreUsuario = $nombre;
    }

    /**
     * Define el Asunto y el remitente.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Correo de Prueba (AsesorFy)',
        );
    }

    /**
     * Define la vista Blade que usará el email.
     */
    public function content(): Content
    {
        return new Content(
            // La vista estará en: resources/views/emails/prueba.blade.php
            view: 'emails.prueba',
        );
    }
}