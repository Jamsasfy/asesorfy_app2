<?php

namespace App\Mail;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AsesorAsignadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Cliente $cliente,
        public User $asesor,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "👨‍💼 Tu asesor en AsesorFy - {$this->asesor->name}",
        );
    }

    public function content(): Content
    {
        $portalUrl = route('filament.portal.auth.login');

        return new Content(
            view: 'emails.asesor_asignado',
            with: [
                'nombreCliente' => $this->cliente->tipo_cliente_id == 1
                    ? ($this->cliente->nombre . ' ' . $this->cliente->apellidos)
                    : $this->cliente->nombre . ' ' . $this->cliente->apellidos,
                'razonSocial'   => $this->cliente->razon_social,
                'esSociedad'    => $this->cliente->tipo_cliente_id != 1,
                'asesorNombre'  => $this->asesor->name,
                'asesorEmail'   => $this->asesor->email,
                'portalUrl'     => $portalUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
