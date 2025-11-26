<?php

namespace App\Mail;

use App\Models\Factura;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EnlacePagoFacturaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Factura $factura,
        public string $urlPago,
    ) {}

    public function build()
    {
        return $this->subject('Enlace para pagar tu factura en AsesorFy')
            ->markdown('emails.facturas.enlace-pago');
    }
}
