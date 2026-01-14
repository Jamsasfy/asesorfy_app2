<?php
namespace App\Mail;

use App\Models\Lead;
use App\Models\Venta;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PagoInicialTransferenciaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Lead $lead,
        public Venta $venta,
        public float $importeTotal,
        public int $porcentajeIva,
        public ?string $iban,
        public string $concepto,
        public string $resumeUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Datos para realizar la transferencia');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pago-inicial-transferencia',
            with: [
                'lead'         => $this->lead,
                'venta'        => $this->venta,
                'importeTotal' => $this->importeTotal,
                'porcentajeIva'=> $this->porcentajeIva,
                'iban'         => $this->iban,
                'concepto'     => $this->concepto,
                'resumeUrl'    => $this->resumeUrl,
            ],
        );
    }
}
