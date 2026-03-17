<?php

namespace App\Mail;

use App\Models\Cliente;
use App\Models\User;
use App\Models\ClienteSuscripcion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClienteActivadoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Cliente $cliente,
        public User $user,
        public ?User $asesor = null,
        public ?ClienteSuscripcion $suscripcion = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🎉 Bienvenido a ' . config('app.name') . ' - Activa tu cuenta',
        );
    }

    public function content(): Content
    {
        $activationUrl = route('portal.activate', ['token' => $this->user->activation_token]);

        return new Content(
            view: 'emails.cliente_activado',
            with: [
                'nombreCliente' => $this->cliente->nombre ?? $this->cliente->razon_social,
                'email' => $this->user->email,
                'activationUrl' => $activationUrl,
                'asesorNombre' => $this->asesor?->name ?? 'Tu asesor',
                'servicioNombre' => $this->suscripcion?->servicio?->nombre ?? 'el servicio contratado',
                'expiresAt' => $this->user->activation_token_expires_at?->format('d/m/Y H:i') ?? '',
            ],
        );
    }
}
