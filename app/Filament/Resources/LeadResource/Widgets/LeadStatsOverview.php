<?php

namespace App\Filament\Resources\LeadResource\Widgets;

use App\Enums\LeadEstadoEnum;
use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class LeadStatsOverview extends BaseWidget
{
    protected static ?int $sort = -2;
    protected ?string $pollingInterval = '60s';
    protected static ?string $maxWidth = '5xl';

    // true → filtrar por asignado_id (Mis Leads); false → global (Todos los Leads)
    public bool $soloPropios = false;

    public static function canView(): bool
    {
        return true;
    }

    protected function getStats(): array
    {
        $query = Lead::query();

        if ($this->soloPropios) {
            $query->where('asignado_id', auth()->id());
        }

        $cacheKey = $this->soloPropios
            ? 'widget_total_leads_' . auth()->id()
            : 'widget_total_leads_global';

        $totalLeads = Cache::remember(
            $cacheKey,
            now()->addMinutes(5),
            fn () => (clone $query)->count()
        );

        $pendientes = $this->soloPropios
            ? (clone $query)->where('estado', LeadEstadoEnum::SIN_GESTIONAR->value)->count()
            : (clone $query)->whereNull('asignado_id')->count();

        $descripcion = $pendientes > 0
            ? ($this->soloPropios
                ? "Leads propios sin gestionar: {$pendientes}"
                : "Leads sin asignar: {$pendientes}")
            : 'Todo al día';

        $colorEstad = $pendientes > 0 ? 'warning' : 'success';

        $iniciales   = collect(LeadEstadoEnum::cases())->filter->isInicial()->pluck('value');
        $enProgreso  = collect(LeadEstadoEnum::cases())->filter->isEnProgreso()->pluck('value');
        $convertidos = collect(LeadEstadoEnum::cases())->filter->isConvertido()->pluck('value');

        return [
            Stat::make('Leads Totales', $totalLeads)
                ->description($descripcion)
                ->descriptionIcon('heroicon-m-clipboard-document')
                ->color($colorEstad),

            Stat::make('Iniciales', (clone $query)->whereIn('estado', $iniciales)->count())
                ->description('Aún sin gestionar')
                ->descriptionIcon('heroicon-m-eye')
                ->color('gray'),

            Stat::make('En Proceso', (clone $query)->whereIn('estado', $enProgreso)->count())
                ->description('Trabajándose')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info'),

            Stat::make('Convertidos', (clone $query)->whereIn('estado', $convertidos)->count())
                ->description('Cerrados con éxito')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
