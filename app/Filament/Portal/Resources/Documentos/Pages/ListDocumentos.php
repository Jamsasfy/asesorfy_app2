<?php

namespace App\Filament\Portal\Resources\Documentos\Pages;

use App\Enums\DocumentoEstadoEnum;
use App\Filament\Portal\Resources\Documentos\DocumentoResource;
use App\Filament\Portal\Resources\Documentos\Widgets\DocumentosKpiWidget;
use App\Models\Documento;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDocumentos extends ListRecords
{
    protected static string $resource = DocumentoResource::class;

    public function mount(): void
    {
        parent::mount();

        $tab = request()->query('tab');
        session(['portal_documentos_tab' => filled($tab) ? $tab : 'todos']);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            DocumentosKpiWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'todos' => Tab::make('Todos')
                ->modifyQueryUsing(fn (Builder $query) => $this->scopeActivos($query))
                ->badge(fn () => $this->baseActivosQuery()->count())
                ->badgeColor('gray'),

            'pendientes' => Tab::make('En revisión')
                ->modifyQueryUsing(function (Builder $query) {
                    $this->scopeActivos($query);

                    return $query->where('estado', DocumentoEstadoEnum::PENDIENTE->value);
                })
                ->badge(fn () => $this->baseActivosQuery()
                    ->where('estado', DocumentoEstadoEnum::PENDIENTE->value)
                    ->count()
                )
                ->badgeColor('gray'),

            // ✅ SOLO aclaración pendiente de respuesta
            'requiere_atencion' => Tab::make('Requiere tu respuesta')
                ->modifyQueryUsing(function (Builder $query) {
                    $this->scopeActivos($query);

                    return $query
                        ->where('estado', DocumentoEstadoEnum::NECESITA_ACLARACION->value)
                        ->whereNull('aclaracion_respondida_at');
                })
                ->badge(fn () => $this->baseActivosQuery()
                    ->where('estado', DocumentoEstadoEnum::NECESITA_ACLARACION->value)
                    ->whereNull('aclaracion_respondida_at')
                    ->count()
                )
                ->badgeColor('warning'),

            // ✅ NUEVO: Rechazados (cliente debe verlo sí o sí)
            'rechazados' => Tab::make('Rechazados')
                ->modifyQueryUsing(function (Builder $query) {
                    $this->scopeActivos($query);

                    return $query->where('estado', DocumentoEstadoEnum::RECHAZADO->value);
                })
                ->badge(fn () => $this->baseActivosQuery()
                    ->where('estado', DocumentoEstadoEnum::RECHAZADO->value)
                    ->count()
                )
                ->badgeColor('danger'),

            'verificados' => Tab::make('Verificados')
                ->modifyQueryUsing(function (Builder $query) {
                    $this->scopeActivos($query);

                    return $query->where('estado', DocumentoEstadoEnum::VERIFICADO->value);
                })
                ->badge(fn () => $this->baseActivosQuery()
                    ->where('estado', DocumentoEstadoEnum::VERIFICADO->value)
                    ->count()
                )
                ->badgeColor('success'),

            'archivados' => Tab::make('Archivados')
                ->modifyQueryUsing(fn (Builder $query) => $this->scopeArchivados($query))
                ->badge(fn () => $this->baseArchivadosQuery()->count())
                ->badgeColor('gray'),
        ];
    }

    /**
     * Base: docs del/los clientes del usuario (portal).
     */
    private function baseQuery(): Builder
    {
        $clienteIds = auth()->user()->clientes()->pluck('clientes.id')->all();

        return Documento::query()->whereIn('cliente_id', $clienteIds);
    }

    /**
     * Activos = visibles para el cliente en el flujo normal (no archivados, no purgados, no ocultos).
     */
    private function baseActivosQuery(): Builder
    {
        return $this->baseQuery()
            ->where('hidden_in_portal', false)
            ->whereNull('purged_at')
            ->where('estado', '!=', DocumentoEstadoEnum::ARCHIVADO->value);
    }

    /**
     * Archivados = purgados / ocultos / estado archivado.
     */
    private function baseArchivadosQuery(): Builder
    {
        return $this->baseQuery()
            ->where(function (Builder $q) {
                $q->where('hidden_in_portal', true)
                    ->orWhereNotNull('purged_at')
                    ->orWhere('estado', DocumentoEstadoEnum::ARCHIVADO->value);
            });
    }

    private function scopeActivos(Builder $query): Builder
    {
        return $query
            ->where('hidden_in_portal', false)
            ->whereNull('purged_at')
            ->where('estado', '!=', DocumentoEstadoEnum::ARCHIVADO->value);
    }

    private function scopeArchivados(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where('hidden_in_portal', true)
                ->orWhereNotNull('purged_at')
                ->orWhere('estado', DocumentoEstadoEnum::ARCHIVADO->value);
        });
    }
}
