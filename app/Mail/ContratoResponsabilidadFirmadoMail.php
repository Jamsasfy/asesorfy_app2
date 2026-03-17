<?php

namespace App\Mail;

use App\Models\ContratoResponsabilidad;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContratoResponsabilidadFirmadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ContratoResponsabilidad $contrato,
        public string $pdfPath,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Documento firmado — ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contrato-responsabilidad-firmado',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->pdfPath)
                ->as('contrato_responsabilidad.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
