<?php

namespace App\Observers;

use App\Models\Lead;
use App\Enums\LeadEstadoEnum;
use App\Jobs\SendLeadEstadoChangedEmailJob;

class LeadObserver
{
    public function updated(Lead $lead): void
    {
        if ($lead->wasChanged('estado')) {
            // Obtén el nuevo estado como string SIEMPRE
            $nuevo = $lead->getAttribute('estado');
            $estadoValue = $nuevo instanceof LeadEstadoEnum ? $nuevo->value : (string) $nuevo;

            // (Opcional) puedes también sacar el anterior si lo necesitas:
            // $anteriorRaw = $lead->getRawOriginal('estado');

            SendLeadEstadoChangedEmailJob::dispatch($lead->id, $estadoValue);
        }
    }
}
