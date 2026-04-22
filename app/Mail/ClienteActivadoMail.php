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

    public string $context;

    public function __construct(
        public Cliente $cliente,
        public User $user,
        public ?User $asesor = null,
        public ?ClienteSuscripcion $suscripcion = null,
        string $context = 'bienvenida_cliente',
    ) {
        $this->context = $context;
    }

    public function envelope(): Envelope
    {
        $subject = match ($this->context) {
            'creacion_manual' => '🔓 Nuevo acceso a ' . config('app.name') . ' - Activa tu cuenta',
            default           => '🎉 Bienvenido a ' . config('app.name') . ' - Activa tu cuenta',
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $activationUrl = route('portal.activate', ['token' => $this->user->activation_token]);

        return new Content(
            view: 'emails.cliente_activado',
            with: [
                'nombreCliente' => $this->cliente->tipo_cliente_id == 1
                    ? ($this->cliente->nombre . ' ' . $this->cliente->apellidos)
                    : $this->cliente->nombre . ' ' . $this->cliente->apellidos,
                'razonSocial'   => $this->cliente->razon_social,
                'esSociedad'    => $this->cliente->tipo_cliente_id != 1,
                'email'         => $this->user->email,
                'userName'      => $this->user->name,
                'activationUrl' => $activationUrl,
                'asesorNombre'  => $this->asesor?->name ?? 'Tu asesor',
                'asesor'        => $this->asesor,
                'suscripcion'   => $this->suscripcion,
                'servicioNombre' => $this->suscripcion?->servicio?->nombre ?? 'el servicio contratado',
                'expiresAt'     => $this->user->activation_token_expires_at?->format('d/m/Y H:i') ?? '',
                'context'       => $this->context,
            ],
        );
    }
}
