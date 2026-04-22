<?php

namespace App\Mail;

use App\Models\ComercialContratoIncentivo;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContratoIncentivosMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ComercialContratoIncentivo $contrato
    ) {}

    public function build()
    {
        $comercial = $this->contrato->comercial;
        $tipo      = $this->contrato->tipo === 'base' ? 'Contrato Base' : 'Anexo';

        return $this->subject("Firma de {$tipo} de Incentivos - AsesorFy")
            ->view('emails.contrato-incentivos')
            ->with([
                'comercial' => $comercial,
                'contrato'  => $this->contrato,
                'tipo'      => $tipo,
                'urlFirma'  => $this->contrato->getUrlFirma(),
            ]);
    }
}
