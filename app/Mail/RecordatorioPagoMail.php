<?php

namespace App\Mail;

use App\Models\Venta;
use App\Models\VariableConfiguracion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RecordatorioPagoMail extends Mailable
{
    use Queueable, SerializesModels;

    public Venta $venta;
    public ?string $iban = null;
    public ?string $concepto = null;

    public function __construct(Venta $venta)
    {
        $this->venta = $venta;

        // Si es transferencia, preparamos los datos bancarios
        if ($this->venta->pago_inicial_metodo === 'transferencia') {
            $this->iban = VariableConfiguracion::where('nombre_variable', 'iban_transferencias')->value('valor_variable') 
                ?? VariableConfiguracion::where('nombre_variable', 'empresa_iban')->value('valor_variable');
            
            $dni = $venta->cliente->dni_cif ?? $venta->lead->dni ?? '---';
            $this->concepto = trim($dni . " - Venta #" . $venta->id);
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recordatorio: Activación de servicio pendiente - ' . config('app.name')
        );
    }

    public function content(): Content
    {
        // Usaremos una vista específica para el recordatorio
        return new Content(
            view: 'emails.pagos.recordatorio',
        );
    }
}