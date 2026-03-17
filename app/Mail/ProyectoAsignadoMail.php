<?php

namespace App\Mail;

use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProyectoAsignadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $asesor,
        public Proyecto $proyecto,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Te han asignado un nuevo proyecto - ' . $this->proyecto->nombre,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.proyecto-asignado',
            with: [
                'asesor'   => $this->asesor,
                'proyecto' => $this->proyecto,
            ],
        );
    }
}
