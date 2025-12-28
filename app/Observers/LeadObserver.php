<?php

namespace App\Observers;

use App\Models\Lead;
use App\Enums\LeadEstadoEnum;
use App\Jobs\SendLeadEstadoChangedEmailJob;
use App\Filament\Resources\LeadResource;
use Filament\Notifications\Notification;

use App\Models\User;
use Filament\Actions\Action;


class LeadObserver
{
    public function updated(Lead $lead): void
{
    // 1) NOTIFICAR ASIGNACIÓN DE LEAD AL COMERCIAL
    if ($lead->wasChanged('asignado_id') && $lead->asignado_id) {
        if ($comercial = User::find($lead->asignado_id)) {
            Notification::make()
                ->title('Te han asignado un nuevo lead')
                ->body("Lead: '{$lead->nombre}'")
                ->icon('heroicon-o-user-plus')
                ->actions([
                    Action::make('view')
                        ->label('Ver Lead')
                        ->url(LeadResource::getUrl('view', ['record' => $lead]))
                        ->openUrlInNewTab()
                        ->markAsRead()
                        ->close(),
                ])
                ->sendToDatabase($comercial);
        }
    }

    // 2) CAMBIO DE ESTADO (lo que ya tienes)
    if ($lead->wasChanged('estado')) {
        $nuevo = $lead->getAttribute('estado');
        $estadoValue = $nuevo instanceof LeadEstadoEnum ? $nuevo->value : (string) $nuevo;

        SendLeadEstadoChangedEmailJob::dispatch($lead->id, $estadoValue);
    }
}
}
