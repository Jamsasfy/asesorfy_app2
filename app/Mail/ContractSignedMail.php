<?php

namespace App\Mail;

use App\Models\Lead;
use App\Models\Venta;
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
    public ?Venta $venta;
    public string $resumeUrl;

    public function __construct(Lead $lead, string $pdfPath, string $resumeUrl, ?Venta $venta = null)
    {
        $this->lead      = $lead;
        $this->pdfPath   = $pdfPath;
        $this->resumeUrl = $resumeUrl;
        $this->venta     = $venta;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Aquí tienes tu contrato firmado con AsesorFy',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contract-signed',
            with: [
                'lead'      => $this->lead,
                'venta'     => $this->venta,
                'resumeUrl' => $this->resumeUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->pdfPath)
                ->as('Contrato_AsesorFy.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
