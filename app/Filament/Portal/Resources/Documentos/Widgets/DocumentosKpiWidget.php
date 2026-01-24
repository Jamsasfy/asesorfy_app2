<?php

namespace App\Filament\Portal\Resources\Documentos\Widgets;

use App\Enums\DocumentoEstadoEnum;
use App\Models\Documento;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DocumentosKpiWidget extends StatsOverviewWidget
{
    // ✅ Ahora son 6 tarjetas (incluye "Rechazados")
    protected int|array|null $columns = 6;

    protected function getStats(): array
    {
        $clienteIds = auth()->user()->clientes()->pluck('clientes.id')->all();

        $estadoPendiente  = DocumentoEstadoEnum::PENDIENTE->value;
        $estadoAclaracion = DocumentoEstadoEnum::NECESITA_ACLARACION->value;
        $estadoRechazado  = DocumentoEstadoEnum::RECHAZADO->value;
        $estadoVerificado = DocumentoEstadoEnum::VERIFICADO->value;
        $estadoArchivado  = DocumentoEstadoEnum::ARCHIVADO->value;

        $row = Documento::query()
            ->whereIn('cliente_id', $clienteIds)
            ->selectRaw("
                -- Activos visibles (no ocultos, no purgados, no archivados)
                SUM(CASE
                    WHEN hidden_in_portal = 0
                     AND purged_at IS NULL
                     AND estado <> '{$estadoArchivado}'
                    THEN 1 ELSE 0 END
                ) AS total_visible,

                SUM(CASE
                    WHEN hidden_in_portal = 0
                     AND purged_at IS NULL
                     AND estado = '{$estadoPendiente}'
                    THEN 1 ELSE 0 END
                ) AS pendientes,

                -- ✅ SOLO “NECESITA_ACLARACION” SIN RESPUESTA
                SUM(CASE
                    WHEN hidden_in_portal = 0
                     AND purged_at IS NULL
                     AND estado = '{$estadoAclaracion}'
                     AND aclaracion_respondida_at IS NULL
                    THEN 1 ELSE 0 END
                ) AS requiere_atencion,

                -- ✅ Rechazados visibles para el cliente
                SUM(CASE
                    WHEN hidden_in_portal = 0
                     AND purged_at IS NULL
                     AND estado = '{$estadoRechazado}'
                    THEN 1 ELSE 0 END
                ) AS rechazados,

                SUM(CASE
                    WHEN hidden_in_portal = 0
                     AND purged_at IS NULL
                     AND estado = '{$estadoVerificado}'
                    THEN 1 ELSE 0 END
                ) AS verificados,

                -- Archivados: ocultos OR purgados OR estado archivado
                SUM(CASE
                    WHEN hidden_in_portal = 1
                      OR purged_at IS NOT NULL
                      OR estado = '{$estadoArchivado}'
                    THEN 1 ELSE 0 END
                ) AS archivados
            ")
            ->first();

        $totalVisible     = (int) ($row->total_visible ?? 0);
        $pendientes       = (int) ($row->pendientes ?? 0);
        $requiereAtencion = (int) ($row->requiere_atencion ?? 0);
        $rechazados       = (int) ($row->rechazados ?? 0);
        $verificados      = (int) ($row->verificados ?? 0);
        $archivados       = (int) ($row->archivados ?? 0);

        return [
            Stat::make('Todos', $totalVisible)
                ->description('Ver todo')
                ->icon('heroicon-o-document-text')
                ->color('info')
                ->url($this->tabUrl('todos'))
                ->extraAttributes(['class' => 'cursor-pointer hover:shadow-md transition']),

            Stat::make('En revisión', $pendientes)
                ->description('Tu asesor los está revisando')
                ->icon('heroicon-o-clock')
                ->color('gray')
                ->url($this->tabUrl('pendientes'))
                ->extraAttributes(['class' => 'cursor-pointer hover:shadow-md transition']),

            Stat::make('Requiere tu respuesta', $requiereAtencion)
                ->description('Aclaración pendiente')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->url($this->tabUrl('requiere_atencion'))
                ->extraAttributes(['class' => 'cursor-pointer hover:shadow-md transition']),

            Stat::make('Rechazados', $rechazados)
                ->description('Debes subir uno nuevo')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->url($this->tabUrl('rechazados'))
                ->extraAttributes(['class' => 'cursor-pointer hover:shadow-md transition']),

            Stat::make('Verificados', $verificados)
                ->description('Todo correcto')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->url($this->tabUrl('verificados'))
                ->extraAttributes(['class' => 'cursor-pointer hover:shadow-md transition']),

            Stat::make('Archivados', $archivados)
                ->description('Guardados / eliminados')
                ->icon('heroicon-o-archive-box')
                ->color('gray')
                ->url($this->tabUrl('archivados'))
                ->extraAttributes(['class' => 'cursor-pointer hover:shadow-md transition']),
        ];
    }

    private function tabUrl(string $tab): string
    {
        $base = \App\Filament\Portal\Resources\Documentos\DocumentoResource::getUrl('index');

        return $tab === 'todos'
            ? $base
            : $base . '?tab=' . $tab;
    }
}
