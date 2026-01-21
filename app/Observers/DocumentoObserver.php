<?php

namespace App\Observers;

use App\Models\Documento;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class DocumentoObserver
{
    public function created(Documento $documento): void
    {
        // Solo si está asociado a un cliente
        if (! $documento->cliente_id) {
            return;
        }

        // Solo si lo sube un usuario interno (ajusta roles si quieres)
        $uploader = $documento->user;
        if (! $uploader || ! ($uploader->hasRole('asesor') || $uploader->hasRole('super_admin'))) {
            return;
        }

        // Usuarios portal vinculados al cliente (pivot cliente_user)
        $recipients = User::query()
            ->whereHas('clientes', fn ($q) => $q->where('clientes.id', $documento->cliente_id))
            ->when($documento->user_id, fn ($q) => $q->whereKeyNot($documento->user_id)) // evita auto-notificación
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $url = \App\Filament\Portal\Resources\Documentos\DocumentoResource::getUrl('index');

        foreach ($recipients as $recipient) {
            Notification::make()
                ->title('Tienes nuevos documentos')
                ->body("Se ha subido: {$documento->nombre}")
                ->icon('heroicon-m-document-text')
                ->actions([
                    Action::make('ver')
                        ->label('Ver documentos')
                        ->button()
                        ->url($url, shouldOpenInNewTab: true),
                ])
                ->sendToDatabase($recipient);
        }
    }
}
