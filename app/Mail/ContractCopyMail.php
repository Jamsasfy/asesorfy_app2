<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;
use App\Models\VariableConfiguracion;

class ContractCopyMail extends Mailable
{
    use Queueable, SerializesModels;

    public $lead;
    public $pdfPath;
    
    // Variables para la vista (pago)
    public $venta;
    public $debePagar = false;
    public $totalPagar = 0;
    public $metodo = 'tarjeta';
    public $iban = null;
    public $concepto = null;

    public function __construct($lead, $pdfPath)
    {
        $this->lead = $lead;
        $this->pdfPath = $pdfPath;

        // LÓGICA DE PAGO (Recuperamos la última venta para ver si debe algo)
        $this->venta = $lead->ventas()->latest()->first();

        if ($this->venta && $this->venta->requierePagoInicial() && !$this->venta->tienePagoInicialCompletado()) {
            $this->debePagar = true;
            $this->metodo = $this->venta->pago_inicial_metodo ?? 'tarjeta';
            
            // Calculamos Total con IVA
            $this->totalPagar = $this->venta->importe_total_con_iva ?? ($this->venta->importe_total * 1.21);

            // Si es transferencia, preparamos datos
            if ($this->metodo === 'transferencia') {
                $this->iban = VariableConfiguracion::where('nombre_variable', 'iban_transferencias')->value('valor_variable') 
                     ?? VariableConfiguracion::where('nombre_variable', 'empresa_iban')->value('valor_variable');
                
                $dni = $lead->dni ?? $lead->cif ?? $this->venta->cliente->dni_cif ?? '---';
                $this->concepto = trim($dni . " - Venta #" . $this->venta->id);
            }
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Copia del contrato solicitado - ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.contract_copy',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->pdfPath)
                ->as('Contrato_Servicios_' . $this->lead->id . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}