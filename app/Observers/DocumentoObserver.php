<?php

namespace App\Observers;

use App\Enums\DocumentoEstadoEnum;
use App\Models\Documento;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class DocumentoObserver
{
    /**
     * Se ejecuta cuando se CREA un documento nuevo.
     */
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

    /**
     * ✅ Se ejecuta cuando se ACTUALIZA un documento.
     */
    public function updated(Documento $documento): void
    {
        // Solo si el estado cambió
        if (! $documento->wasChanged('estado')) {
            return;
        }

        $nuevoEstado = $documento->estado;

        // Convertir a enum si es necesario
        if (! ($nuevoEstado instanceof DocumentoEstadoEnum)) {
            $nuevoEstado = DocumentoEstadoEnum::tryFrom((string) $nuevoEstado);
        }

        // Solo si cambió a NECESITA_ACLARACION
        if ($nuevoEstado !== DocumentoEstadoEnum::NECESITA_ACLARACION) {
            return;
        }

        // Verificar que tiene cliente
        if (! $documento->cliente_id) {
            return;
        }

        // ✅ Disparar el Job agregado (máx 1 aviso/día por cliente)
        \App\Jobs\NotificarPendientesRespuestaClienteJob::dispatch((int) $documento->cliente_id)
            ->delay(now()->addMinutes(3));
    }
}