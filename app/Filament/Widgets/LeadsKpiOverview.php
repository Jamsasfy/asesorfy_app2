<?php

namespace App\Filament\Widgets;

use App\Enums\LeadEstadoEnum;
use App\Models\Lead;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LeadsKpiOverview extends StatsOverviewWidget
{
    use HasWidgetShield;
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;
    protected static bool $isLazy = true;


protected int|array|null $columns = 5;

    protected function getStats(): array
    {
        [$from, $to] = $this->periodFromDashboardFilters();

        $base = Lead::query()->whereBetween('created_at', [$from, $to]);

        $nuevos = (clone $base)->count();

        $sinGestionar = (clone $base)
            ->where('estado', LeadEstadoEnum::SIN_GESTIONAR->value)
            ->count();

        $enProgreso = (clone $base)
            ->whereIn('estado', [
                LeadEstadoEnum::INTENTO_CONTACTO->value,
                LeadEstadoEnum::CONTACTADO->value,
                LeadEstadoEnum::ANALISIS_NECESIDADES->value,
                LeadEstadoEnum::ESPERANDO_INFORMACION->value,
                LeadEstadoEnum::PROPUESTA_ENVIADA->value,
                LeadEstadoEnum::EN_NEGOCIACION->value,
            ])
            ->count();

        $convertido = (clone $base)
            ->whereIn('estado', [
                LeadEstadoEnum::CONVERTIDO->value,
                LeadEstadoEnum::CONVERTIDO_ESPERA_DATOS->value,
                LeadEstadoEnum::CONVERTIDO_ESPERA_FIRMA->value,
                LeadEstadoEnum::CONVERTIDO_FIRMADO->value,
            ])
            ->count();

        $descartado = (clone $base)
            ->where('estado', LeadEstadoEnum::DESCARTADO->value)
            ->count();

        $pctConv = $nuevos > 0 ? round(($convertido / $nuevos) * 100, 1) : 0;
        $pctDesc = $nuevos > 0 ? round(($descartado / $nuevos) * 100, 1) : 0;

        return [
            Stat::make('Leads nuevos', $nuevos)
                ->description('En el rango seleccionado')
                ->icon('heroicon-o-user-plus'),

            Stat::make('Sin gestionar', $sinGestionar)
                ->description($nuevos > 0 ? round(($sinGestionar / $nuevos) * 100, 1) . '% del total' : null)
                ->icon('heroicon-o-inbox')
                ->color($sinGestionar > 0 ? 'warning' : 'success'),

            Stat::make('En progreso', $enProgreso)
                ->description($nuevos > 0 ? round(($enProgreso / $nuevos) * 100, 1) . '% del total' : null)
                ->icon('heroicon-o-clock')
                ->color('info'),

            Stat::make('Convertido', $convertido)
                ->description("Conversión: {$pctConv}%")
                ->icon('heroicon-o-check-badge')
                ->color($pctConv >= 50 ? 'success' : 'warning'),

            Stat::make('Descartado', $descartado)
                ->description("Descarte: {$pctDesc}%")
                ->icon('heroicon-o-x-circle')
                ->color($descartado > 0 ? 'danger' : 'gray'),
        ];
    }

    private function periodFromDashboardFilters(): array
    {
        $start = $this->filters['startDate'] ?? null;
        $end   = $this->filters['endDate'] ?? null;

        $from = $start ? now()->parse($start)->startOfDay() : now()->startOfMonth();
        $to   = $end   ? now()->parse($end)->endOfDay() : now()->endOfDay();

        return [$from, $to];
    }
}
