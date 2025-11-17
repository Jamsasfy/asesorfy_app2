<?php

// app/Mail/LeadEstadoChangedMail.php
namespace App\Mail;

use App\Models\Lead;
use App\Enums\LeadEstadoEnum;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeadEstadoChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Lead $lead;
    public LeadEstadoEnum $estado;

    public function __construct(Lead $lead, LeadEstadoEnum $estado)
    {
        $this->lead = $lead;
        $this->estado = $estado;
    }

    public function build()
    {
        return $this->subject('Tu solicitud está en: ' . $this->estado->getLabel())
            ->markdown('emails.leads.estado_cambiado', [ // <- RUTA EXACTA
                'lead'         => $this->lead,
                'estadoLabel'  => $this->estado->getLabel(),
                'estadoValue'  => $this->estado->value,
            ]);
    }
}
