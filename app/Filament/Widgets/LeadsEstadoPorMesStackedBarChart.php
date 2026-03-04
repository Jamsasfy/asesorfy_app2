<?php

namespace App\Filament\Widgets;

use App\Enums\LeadEstadoEnum;
use App\Models\Lead;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Elemind\FilamentECharts\Widgets\EChartWidget;

class LeadsEstadoPorMesStackedBarChart extends EChartWidget
{
    use HasWidgetShield;

    protected static ?string $heading = '📊 Leads por mes (estados resumidos)';
    protected static int $contentHeight = 360;
    protected static ?int $sort = 2;
    protected static bool $isLazy = true;


    // Importante: NO uses mount() aquí.
    public ?string $filter = null; // año

    public function getFilters(): ?array
    {
        $currentYear = (int) now()->year;

        // Años que realmente tienen leads (hasta el año actual)
        $yearsWithLeads = Lead::query()
            ->selectRaw('YEAR(created_at) as y')
            ->whereNotNull('created_at')
            ->whereYear('created_at', '<=', $currentYear)
            ->distinct()
            ->orderByDesc('y')
            ->pluck('y')
            ->map(fn ($y) => (int) $y)
            ->values()
            ->all();

        // Si no hay leads aún, al menos muestra el año actual
        $years = collect([$currentYear])
            ->merge($yearsWithLeads)
            ->unique()
            ->sortDesc()
            ->values();

        return $years
            ->mapWithKeys(fn (int $y) => [(string) $y => (string) $y])
            ->toArray();
    }

    protected function getOptions(): array
    {
        $year = (int) ($this->filter ?: now()->year);

        // Colores (idénticos al donut)
        $colors = [
            'sin_gestionar' => '#f59e0b',
            'en_progreso'   => '#3b82f6',
            'convertido'    => '#10b981',
            'descartado'    => '#ef4444',
        ];

        $labels = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

        $estadosEnProgreso = [
            LeadEstadoEnum::INTENTO_CONTACTO->value,
            LeadEstadoEnum::CONTACTADO->value,
            LeadEstadoEnum::ANALISIS_NECESIDADES->value,
            LeadEstadoEnum::ESPERANDO_INFORMACION->value,
            LeadEstadoEnum::PROPUESTA_ENVIADA->value,
            LeadEstadoEnum::EN_NEGOCIACION->value,
        ];

        $estadosConvertido = [
            LeadEstadoEnum::CONVERTIDO->value,
            LeadEstadoEnum::CONVERTIDO_ESPERA_DATOS->value,
            LeadEstadoEnum::CONVERTIDO_ESPERA_FIRMA->value,
            LeadEstadoEnum::CONVERTIDO_FIRMADO->value,
        ];

        $sinGestionar = $this->countByMonth($year, [LeadEstadoEnum::SIN_GESTIONAR->value]);
        $enProgreso   = $this->countByMonth($year, $estadosEnProgreso);
        $convertido   = $this->countByMonth($year, $estadosConvertido);
        $descartado   = $this->countByMonth($year, [LeadEstadoEnum::DESCARTADO->value]);

        return [
            'tooltip' => [
                'trigger' => 'axis',
                'axisPointer' => ['type' => 'shadow'],
            ],
            'legend' => [
                'top' => 0,
            ],
            'grid' => [
                'left' => '2%',
                'right' => '2%',
                'top' => 40,
                'bottom' => 10,
                'containLabel' => true,
            ],

            // ✅ en formato array (más robusto con ECharts)
            'xAxis' => [[
                'type' => 'category',
                'data' => $labels,
                'axisTick' => ['alignWithLabel' => true],
            ]],
            'yAxis' => [[
                'type' => 'value',
            ]],

            'series' => [
                [
                    'name' => 'Sin gestionar',
                    'type' => 'bar',
                    'stack' => 'total',
                    'itemStyle' => ['color' => $colors['sin_gestionar']],
                    'emphasis' => ['focus' => 'series'],
                    'data' => $sinGestionar,
                ],
                [
                    'name' => 'En progreso',
                    'type' => 'bar',
                    'stack' => 'total',
                    'itemStyle' => ['color' => $colors['en_progreso']],
                    'emphasis' => ['focus' => 'series'],
                    'data' => $enProgreso,
                ],
                [
                    'name' => 'Convertido',
                    'type' => 'bar',
                    'stack' => 'total',
                    'itemStyle' => ['color' => $colors['convertido']],
                    'emphasis' => ['focus' => 'series'],
                    'data' => $convertido,
                ],
                [
                    'name' => 'Descartado',
                    'type' => 'bar',
                    'stack' => 'total',
                    'itemStyle' => ['color' => $colors['descartado']],
                    'emphasis' => ['focus' => 'series'],
                    'data' => $descartado,
                ],
            ],
        ];
    }

    private function countByMonth(int $year, array $estadoValues): array
    {
        $rows = Lead::query()
            ->selectRaw('MONTH(created_at) as mes, COUNT(*) as total')
            ->whereYear('created_at', $year)
            ->whereIn('estado', $estadoValues)
            ->groupByRaw('MONTH(created_at)')
            ->pluck('total', 'mes');

        $data = [];
        foreach (range(1, 12) as $m) {
            $data[] = (int) ($rows[$m] ?? 0);
        }

        return $data;
    }
}
