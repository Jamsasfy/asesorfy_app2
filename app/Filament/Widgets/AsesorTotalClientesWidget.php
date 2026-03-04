<?php

namespace App\Filament\Widgets;

use App\Enums\DocumentoEstadoEnum;
use App\Models\Cliente;
use App\Models\Documento;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AsesorTotalClientesWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 0;
    protected static bool $isLazy = true;


    // ✅ 5 en una fila (desktop)
protected array|int|null $columns = 5;

    protected function getStats(): array
    {
        $asesorId = auth()->id();

        if (! $asesorId) {
            return [
                Stat::make('Error', 'Usuario no autenticado')
                    ->description('No se pudieron cargar las estadísticas.')
                    ->color('danger'),
            ];
        }

        // ✅ Clientes (1 query)
        $row = Cliente::query()
            ->where('asesor_id', $asesorId)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) as activos")
            ->selectRaw("SUM(CASE WHEN estado IN ('requiere_atencion','impagado') THEN 1 ELSE 0 END) as atencion")
            ->first();

        $total   = (int) ($row->total ?? 0);
        $activos = (int) ($row->activos ?? 0);
        $aten    = (int) ($row->atencion ?? 0);

        // ✅ Documentos (2 queries)
        $docsPendientes = Documento::query()
            ->whereHas('cliente', fn ($q) => $q->where('asesor_id', $asesorId))
            ->where('estado', DocumentoEstadoEnum::PENDIENTE->value)
            ->count();

        $docsAclaracionRespondida = Documento::query()
            ->whereHas('cliente', fn ($q) => $q->where('asesor_id', $asesorId))
            ->where('estado', DocumentoEstadoEnum::NECESITA_ACLARACION->value)
            ->whereNotNull('aclaracion_respondida_at')
            ->count();

        return [
            Stat::make('Mis clientes', $total)
                ->description('Asignados a mí')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('primary'),

            Stat::make('Activos', $activos)
                ->description('Estado activo')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Atención / Impago', $aten)
                ->description('Requieren atención')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($aten > 0 ? 'danger' : 'success'),

            Stat::make('Docs pendientes', $docsPendientes)
                ->description('Pendiente de verificar')
                ->descriptionIcon('heroicon-m-inbox')
                ->color($docsPendientes > 0 ? 'warning' : 'success'),

            Stat::make('Aclaración respondida', $docsAclaracionRespondida)
                ->description('Cliente ya contestó')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color($docsAclaracionRespondida > 0 ? 'info' : 'success'),
        ];
    }
}
