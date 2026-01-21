<?php

namespace App\Filament\Portal\Resources\Documentos\Pages;

use App\Enums\DocumentoEstadoEnum;
use App\Filament\Portal\Resources\Documentos\DocumentoResource;
use App\Models\Documento;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Portal\Resources\Documentos\Widgets\DocumentosKpiWidget;


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

    /**
     * Tabs encima de la tabla (Filament v4).
     */
    public function getTabs(): array
    {
        return [
            'todos' => Tab::make('Todos')
                ->badge(fn () => $this->baseDocumentoQuery()->count()),

            'pendientes' => Tab::make('Pendientes')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('estado', DocumentoEstadoEnum::PENDIENTE->value))
                ->badge(fn () => $this->baseDocumentoQuery()->where('estado', DocumentoEstadoEnum::PENDIENTE->value)->count())
                ->badgeColor('warning'),

            'rechazados' => Tab::make('Rechazados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('estado', DocumentoEstadoEnum::RECHAZADO->value))
                ->badge(fn () => $this->baseDocumentoQuery()->where('estado', DocumentoEstadoEnum::RECHAZADO->value)->count())
                ->badgeColor('danger'),

            'verificados' => Tab::make('Verificados')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('estado', DocumentoEstadoEnum::VERIFICADO->value))
                ->badge(fn () => $this->baseDocumentoQuery()->where('estado', DocumentoEstadoEnum::VERIFICADO->value)->count())
                ->badgeColor('success'),
        ];
    }

    /**
     * Query base scoping igual que el Resource (portal -> clientes del usuario).
     * Así los badges cuentan solo lo que el usuario puede ver.
     */
    private function baseDocumentoQuery(): Builder
    {
        $clienteIds = auth()->user()->clientes()->pluck('clientes.id')->all();

        return Documento::query()->whereIn('cliente_id', $clienteIds);
    }
}
