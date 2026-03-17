<?php

namespace App\Mail;

use App\Models\Venta;
use App\Models\VariableConfiguracion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CambioMetodoPagoMail extends Mailable
{
    use Queueable, SerializesModels;

    public Venta $venta;
    public ?string $iban = null;
    public ?string $concepto = null;

    public function __construct(Venta $venta)
    {
        $this->venta = $venta;

        // Si el nuevo método es transferencia, preparamos los datos
        if ($venta->pago_inicial_metodo === 'transferencia') {
            $this->iban = VariableConfiguracion::where('nombre_variable', 'iban_transferencias')
                ->value('valor_variable') 
                ?? VariableConfiguracion::where('nombre_variable', 'empresa_banco_iban')->value('valor_variable');

            // Recalculamos concepto por seguridad
            $dni = $venta->cliente->dni_cif ?? '---';
            $this->concepto = "Venta #{$venta->id} - {$dni}";
        }
    }

    public function envelope(): Envelope
    {
        $asunto = $this->venta->pago_inicial_metodo === 'transferencia'
            ? 'Instrucciones para realizar transferencia - AsesorFy'
            : 'Enlace para pago seguro con tarjeta - AsesorFy';

        return new Envelope(subject: $asunto);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pagos.cambio_metodo', // Crearemos esta vista ahora
        );
    }
}