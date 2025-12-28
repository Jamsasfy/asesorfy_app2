<?php

namespace App\Mail;

use App\Models\Venta;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeClientMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $nombreCliente;
    public ?Venta $venta;
    public string $modo; // 'transfer_pendiente' | 'welcome'

    public function __construct($cliente, ?Venta $venta = null)
    {
        $this->nombreCliente = $cliente->razon_social ?? $cliente->nombre ?? 'Cliente';
        $this->venta = $venta;

        // Modo por defecto
        $this->modo = 'welcome';

        if ($venta && $venta->requierePagoInicial()) {
            $metodo = $venta->pago_inicial_metodo;
            $pagado = $venta->tienePagoInicialCompletado();

            if ($metodo === 'transferencia' && ! $pagado) {
                $this->modo = 'transfer_pendiente';
            }
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Bienvenido a ' . config('app.name') . ' - Próximos pasos',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome_client',
            with: [
                'venta' => $this->venta,
                'modo'  => $this->modo,
            ],
        );
    }
}
