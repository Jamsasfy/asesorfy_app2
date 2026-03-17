<?php

namespace App\Mail;

use App\Models\Cliente;
use App\Models\Venta;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SuscripcionCanceladaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Cliente $cliente,
        public Venta $venta,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Información sobre tu servicio — ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.suscripcion-cancelada',
        );
    }
}
