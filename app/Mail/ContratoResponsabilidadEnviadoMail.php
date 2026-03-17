<?php

namespace App\Mail;

use App\Models\ContratoResponsabilidad;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContratoResponsabilidadEnviadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ContratoResponsabilidad $contrato,
        public string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Documento de exoneración pendiente de firma — ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contrato-responsabilidad-enviado',
            with: [
                'contrato' => $this->contrato,
                'url'      => $this->url,
            ],
        );
    }
}
