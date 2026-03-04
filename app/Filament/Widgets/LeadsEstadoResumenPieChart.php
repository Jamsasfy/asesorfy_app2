<?php

namespace App\Filament\Widgets;

use App\Enums\LeadEstadoEnum;
use App\Models\Lead;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Elemind\FilamentECharts\Widgets\EChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class LeadsEstadoResumenPieChart extends EChartWidget
{
    use HasWidgetShield;
    use InteractsWithPageFilters;

    protected static ?string $heading = '🧾 Leads · Resumen de estados';
    protected static int $contentHeight = 360;
    protected static ?int $sort = 3;
    protected static bool $isLazy = true;


    protected function getOptions(): array
    {
        [$from, $to] = $this->periodFromDashboardFilters();

        $base = Lead::query()->whereBetween('created_at', [$from, $to]);

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

       $data = [
  ['name' => 'Sin gestionar', 'value' => $sinGestionar, 'itemStyle' => ['color' => '#f59e0b']],
  ['name' => 'En progreso',   'value' => $enProgreso,   'itemStyle' => ['color' => '#3b82f6']],
  ['name' => 'Convertido',    'value' => $convertido,   'itemStyle' => ['color' => '#10b981']],
  ['name' => 'Descartado',    'value' => $descartado,   'itemStyle' => ['color' => '#ef4444']],
];


        return [
            'tooltip' => [
                'trigger' => 'item',
                'formatter' => '{b}: {c} ({d}%)',
            ],
            'legend' => [
                'top' => 'bottom',
                'left' => 'center',
            ],
            'series' => [
                [
                    'name' => 'Estados',
                    'type' => 'pie',
                    'radius' => ['45%', '70%'], // donut
                    'avoidLabelOverlap' => true,
                    'itemStyle' => [
                        'borderRadius' => 6,
                        'borderColor' => 'transparent',
                        'borderWidth' => 2,
                    ],
                    'label' => [
                        'show' => true,
                        'formatter' => "{b}\n{c}",
                    ],
                    'labelLine' => [
                        'show' => true,
                    ],
                    'data' => $data,
                ],
            ],
        ];
    }

    private function periodFromDashboardFilters(): array
    {
        $start = $this->filters['startDate'] ?? null;
        $end   = $this->filters['endDate'] ?? null;

        $from = $start ? now()->parse($start)->startOfDay() : now()->startOfMonth();
        $to   = $end ? now()->parse($end)->endOfDay() : now()->endOfDay();

        return [$from, $to];
    }
}
