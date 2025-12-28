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
protected function getStats(): array
{
    $query = Lead::query();

    $esComercial = auth()->user()->hasRole('comercial');

    if ($esComercial) {
        $query->where('asignado_id', auth()->id());
    }

   $totalLeads = Cache::remember(
    'widget_total_leads_' . ($esComercial ? auth()->id() : 'global'),
    now()->addMinutes(5),
    fn () => $query->count()
);

    // 🔰 NUEVO: «pendientes» contextuales
    $pendientes = $esComercial
        ? (clone $query)->where('estado', LeadEstadoEnum::SIN_GESTIONAR->value)->count()
        : (clone $query)->whereNull('asignado_id')->count();

   $descripcion = $pendientes > 0
    ? ($esComercial
        ? "Leads propios sin gestionar: {$pendientes}"
        : "Leads sin asignar: {$pendientes}")
    : "Todo al día";

    $colorEstad = $pendientes > 0 ? 'warning' : 'success';

    // Conjuntos de estados
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
