<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractSignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Lead $lead;
    public string $pdfPath;

    /**
     * @param Lead   $lead     Lead asociado
     * @param string $pdfPath  Ruta absoluta al PDF firmado
     */
    public function __construct(Lead $lead, string $pdfPath)
    {
        $this->lead    = $lead;
        $this->pdfPath = $pdfPath;
    }

    /**
     * Encabezado del email
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Aquí tienes tu contrato firmado con AsesorFy',
        );
    }

    /**
     * Vista original del email (la tuya)
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.contract-signed',
            with: [
                'lead' => $this->lead,
            ],
        );
    }

    /**
     * Adjuntar PDF del contrato firmado
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->pdfPath)
                ->as('Contrato_AsesorFy.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
