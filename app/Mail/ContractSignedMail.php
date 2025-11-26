<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractSignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $lead;
    public $pdfPath;
    public $factura; // <--- Propiedad para la factura

    /**
     * Create a new message instance.
     *
     * @param mixed $lead
     * @param string $pdfPath Ruta absoluta al archivo PDF
     * @param mixed|null $factura Objeto factura (opcional)
     */
    public function __construct($lead, string $pdfPath, $factura = null)
    {
        $this->lead = $lead;
        $this->pdfPath = $pdfPath;
        $this->factura = $factura;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Aquí tienes tu contrato firmado con AsesorFy',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.contract-signed',
        );
    }

    /**
     * Get the attachments for the message.
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