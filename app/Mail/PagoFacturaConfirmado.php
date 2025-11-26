<?php

namespace App\Mail;

use App\Models\Factura;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PagoFacturaConfirmado extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Factura $factura) {}

    public function build()
    {
        return $this->subject('Hemos recibido tu pago - AsesorFy')
            ->markdown('emails.facturas.pago-confirmado');
    }
}
