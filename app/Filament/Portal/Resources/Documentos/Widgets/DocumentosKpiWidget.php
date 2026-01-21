<?php

namespace App\Filament\Portal\Resources\Documentos\Widgets;

use App\Enums\DocumentoEstadoEnum;
use App\Models\Documento;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Filament\Portal\Resources\Documentos\DocumentoResource;


class DocumentosKpiWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $clienteIds = auth()->user()->clientes()->pluck('clientes.id')->all();

        // 1 query agregada (rápido y limpio)
        $rows = Documento::query()
            ->selectRaw('estado, COUNT(*) as c')
            ->whereIn('cliente_id', $clienteIds)
            ->groupBy('estado')
            ->pluck('c', 'estado')
            ->toArray();

        $pendientes  = (int) ($rows[DocumentoEstadoEnum::PENDIENTE->value]  ?? 0);
        $rechazados  = (int) ($rows[DocumentoEstadoEnum::RECHAZADO->value]  ?? 0);
        $verificados = (int) ($rows[DocumentoEstadoEnum::VERIFICADO->value] ?? 0);
        $total       = $pendientes + $rechazados + $verificados;

        return [
            Stat::make('Todos', $total)
                ->description('Ver todo')
                ->url($this->tabUrl('todos')),

            Stat::make('Pendientes', $pendientes)
                ->description('En revisión por tu Asesor')
                ->color('warning')
                ->url($this->tabUrl('pendientes')),

            Stat::make('Rechazados', $rechazados)
                ->description('Necesitan corrección')
                ->color('danger')
                ->url($this->tabUrl('rechazados')),

            Stat::make('Verificados', $verificados)
                ->description('Verificados correctos')
                ->color('success')
                ->url($this->tabUrl('verificados')),
        ];
    }

    private function tabUrl(string $tab): string
    {
        $base = \App\Filament\Portal\Resources\Documentos\DocumentoResource::getUrl('index');

        return $tab === 'todos'
            ? $base
            : $base . '?tab=' . $tab; // ✅ tu instalación usa "tab"
    }


}
