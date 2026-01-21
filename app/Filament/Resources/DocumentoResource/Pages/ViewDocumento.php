<?php

namespace App\Filament\Resources\DocumentoResource\Pages;

use App\Enums\DocumentoEstadoEnum;
use App\Filament\Resources\DocumentoResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;

class ViewDocumento extends ViewRecord
{
    protected static string $resource = DocumentoResource::class;

    public function getTitle(): string
    {
        return '📄 Documento: ' . ($this->record->nombre ?? 'Sin nombre');
    }

    public function getHeading(): string
    {
        return '📄 Documento: ' . ($this->record->nombre ?? 'Sin nombre');
    }

    public function getSubheading(): string
    {
        return 'Cliente: ' . ($this->record->cliente->razon_social ?? 'Desconocido');
    }

    /**
     * En acciones Livewire (DeleteAction, etc.) a veces request()->query() viene vacío.
     * Recuperamos querystring desde request() o, si falta, desde el Referer.
     */
    protected function getQueryFromRequestOrReferer(): array
    {
        $qs = request()->query();
        if (! empty($qs)) {
            return $qs;
        }

        $referer = (string) request()->headers->get('referer', '');
        if ($referer === '') {
            return [];
        }

        $out = [];
        parse_str((string) parse_url($referer, PHP_URL_QUERY), $out);

        return is_array($out) ? $out : [];
    }

    /**
     * Conserva chain/cliente/seen cuando navegas entre view/edit y al volver.
     */
    protected function getChainQueryString(): string
    {
        $qs = $this->getQueryFromRequestOrReferer();

        $keep = [];
        foreach (['chain', 'cliente', 'seen'] as $k) {
            if (isset($qs[$k]) && $qs[$k] !== '' && $qs[$k] !== null) {
                $keep[$k] = $qs[$k];
            }
        }

        return $keep ? ('?' . http_build_query($keep)) : '';
    }

    /**
     * Si vienes desde el RelationManager del cliente, vuelve a /clientes/{id}?relation=1
     * Si no, vuelve al index normal del Resource Documento.
     */
    protected function getReturnUrl(): string
    {
        $qs = $this->getQueryFromRequestOrReferer();
        $clienteId = (int) ($qs['cliente'] ?? 0);

        if ($clienteId > 0) {
            return route('filament.admin.resources.clientes.view', ['record' => $clienteId]) . '?relation=1';
        }

        return route('filament.admin.resources.documentos.index');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Modificar documento')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->tooltip('Editar la información de este documento')
                // ✅ mantener chain/cliente/seen al ir a editar
                ->url(fn () => static::$resource::getUrl('edit', ['record' => $this->record]) . $this->getChainQueryString()),

            // ✅ Purga manual del archivo (NO borra el registro)
          Action::make('eliminar_archivo')
    ->label('🧹 PURGAR ARCHIVO (solo fichero)')
    ->icon('heroicon-o-trash')
    ->color('danger')
    ->requiresConfirmation()
    ->modalHeading('🧹 PURGAR ARCHIVO (solo fichero)')
    ->modalDescription('Borra el archivo del almacenamiento pero mantiene el registro en la base de datos. Úsalo solo para casos sensibles o archivos que no deban permanecer.')
    ->modalSubmitActionLabel('Purgar archivo')
    ->visible(fn () => filled($this->record->ruta))
    ->form([
        \Filament\Forms\Components\Select::make('purge_reason')
            ->label('Motivo de purga')
            ->options([
                'sensible' => 'Sensible (porno/menores/datos extremadamente sensibles)',
                'malware'  => 'Sospecha de malware / archivo peligroso',
                'error'    => 'Archivo erróneo / no corresponde',
                'otro'     => 'Otro',
            ])
            ->required()
            ->native(false),

        \Filament\Forms\Components\Textarea::make('purge_note')
            ->label('Nota interna (auditoría)')
            ->helperText('No se muestra al cliente. Obligatoria si el motivo es “Sensible”.')
            ->rows(4)
            ->required(fn (callable $get) => (string) $get('purge_reason') === 'sensible'),

        \Filament\Forms\Components\TextInput::make('confirm_text')
            ->label('Confirmación')
            ->helperText('Escribe PURGAR para confirmar (solo si el motivo es “Sensible”).')
            ->placeholder('PURGAR')
            ->required(fn (callable $get) => (string) $get('purge_reason') === 'sensible')
            ->dehydrateStateUsing(fn ($state) => (string) $state)
            ->rule(function (callable $get) {
                return (string) $get('purge_reason') === 'sensible'
                    ? 'in:PURGAR'
                    : '';
            }),
    ])
    ->action(function (array $data) {
        $record = $this->record;

        $reason = (string) ($data['purge_reason'] ?? 'otro');
        $note   = (string) ($data['purge_note'] ?? '');

        // borrar físico si existe
        if (filled($record->ruta) && Storage::disk('public')->exists($record->ruta)) {
            Storage::disk('public')->delete($record->ruta);
        }

        // marcar purgado (archivo fuera)
        $record->ruta = null;
        $record->mime_type = null;

        $record->purged_at = now();
        $record->purged_by_id = auth()->id();
        $record->purge_reason = $reason;
        $record->purge_note = $note ?: null;

        // ✅ Si es sensible: ocultar en portal (para que no se acumule “requiere atención”)
        if ($reason === 'sensible') {
            $record->hidden_in_portal = true;

            // mensaje neutro visible para cliente (si lo mantienes visible por lo que sea)
            $record->estado = DocumentoEstadoEnum::RECHAZADO;
            $record->verificado = false;
            $record->motivo_rechazo = 'Archivo eliminado por motivos de seguridad. Si necesitas ayuda, contacta con el equipo.';
        } else {
            // comportamiento actual: lo dejamos como RECHAZADO para que suba corrección
            $record->estado = DocumentoEstadoEnum::RECHAZADO;
            $record->verificado = false;

            if (blank($record->motivo_rechazo)) {
                $record->motivo_rechazo = 'Archivo eliminado por el equipo. Por favor, sube el documento correcto.';
            }
        }

        $record->save();

        Notification::make()
            ->title('Archivo purgado')
            ->body($reason === 'sensible'
                ? 'Archivo eliminado y oculto en el portal (modo sensible).'
                : 'Archivo eliminado correctamente. El registro se mantiene.')
            ->success()
            ->send();

        $record->refresh();
        $this->dispatch('$refresh');
    }),


            DeleteAction::make()
                ->label('☢️ ELIMINAR REGISTRO')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->visible(fn () => auth()->user()?->hasRole('super_admin'))
                ->requiresConfirmation()
                ->modalHeading('☢️ ELIMINAR REGISTRO (irreversible)')
                ->modalDescription('Esto borra el registro en la base de datos. Para quitar solo el fichero usa "Eliminar archivo".')
                ->extraAttributes([
                    'class' => 'font-bold',
                ])
                // ✅ tras borrar, volver al cliente relation (si venías desde ahí)
                ->successRedirectUrl(fn () => $this->getReturnUrl()),

            Action::make('volver')
                ->label('Volver al listado')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => $this->getReturnUrl())
                ->visible(fn () => auth()->user()?->hasRole('super_admin'))
                ->color('gray'),
        ];
    }
}
