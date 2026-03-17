<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Venta;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PagoCompletadoComercialMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $comercial,
        public Venta $venta,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '💰 Pago completado — ' . ($this->venta->cliente->razon_social ?? 'Cliente'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pago-completado-comercial',
        );
    }
}
