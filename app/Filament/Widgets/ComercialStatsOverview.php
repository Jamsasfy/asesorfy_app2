<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use App\Models\Venta;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class ComercialStatsOverview extends BaseWidget
{
    protected ?string $pollingInterval = null;

    // Permiso de Shield
    protected static bool $isDiscovered = false;

    public static function canView(): bool
    {
        return Auth::user()?->can('View:ComercialStatsOverview') ?? false;
    }

    protected function getStats(): array
    {
        $user = Auth::user();

        // Obtener filtros del dashboard
        $startDate = $this->filters['startDate'] ?? now()->startOfMonth()->toDateString();
        $endDate   = $this->filters['endDate'] ?? now()->toDateString();

        $leadsAsignados = Lead::where('asignado_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $leadsFirmados = Lead::where('asignado_id', $user->id)
            ->whereIn('estado', ['convertido_firmado', 'convertido_activado'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $tasaConversion = $leadsAsignados > 0
            ? round(($leadsFirmados / $leadsAsignados) * 100, 1)
            : 0;

        $ventasTotales = Venta::whereHas('lead', function ($query) use ($user) {
                $query->where('asignado_id', $user->id);
            })
            ->where('estado', 'completada')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('importe_total');

        return [
            Stat::make('Leads Asignados', $leadsAsignados)
                ->description('Total de leads en tu cartera')
                ->descriptionIcon('heroicon-o-user-group')
                ->color('primary'),

            Stat::make('Leads Convertidos', $leadsFirmados)
                ->description('Han firmado contrato')
                ->descriptionIcon('heroicon-o-document-check')
                ->color('success'),

            Stat::make('Tasa de Conversión', $tasaConversion . '%')
                ->description('Porcentaje de éxito')
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color($tasaConversion >= 50 ? 'success' : 'warning'),

            Stat::make('Ventas Totales', '€' . number_format($ventasTotales, 2, ',', '.'))
                ->description('Importe total cerrado')
                ->descriptionIcon('heroicon-o-currency-euro')
                ->color('success'),
        ];
    }
}
