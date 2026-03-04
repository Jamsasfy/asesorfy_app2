<?php

namespace App\Filament\Widgets;

use App\Enums\ProyectoEstadoEnum;
use App\Models\Proyecto;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Elemind\FilamentECharts\Widgets\EChartWidget;

class ProyectosEstadoPorMesStackedBarChart extends EChartWidget
{
    use HasWidgetShield;

    protected static ?string $heading = '📦 Proyectos por mes (estados)';
    protected static int $contentHeight = 360;
    protected static ?int $sort = 6; // ajusta si lo quieres al lado del de ventas
    protected static bool $isLazy = true;


    public ?string $filter = null; // año

    public function getFilters(): ?array
    {
        $currentYear = (int) now()->year;

        $yearsWithProyectos = Proyecto::query()
            ->selectRaw('YEAR(created_at) as y')
            ->whereNotNull('created_at')
            ->whereYear('created_at', '<=', $currentYear)
            ->distinct()
            ->orderByDesc('y')
            ->pluck('y')
            ->map(fn ($y) => (int) $y)
            ->values()
            ->all();

        $years = collect([$currentYear])
            ->merge($yearsWithProyectos)
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

        $labels = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

        // Colores coherentes (puedes cambiarlos si quieres)
        $colors = [
            ProyectoEstadoEnum::Pendiente->value  => '#f59e0b', // amber
            ProyectoEstadoEnum::EnProgreso->value => '#3b82f6', // blue
            ProyectoEstadoEnum::Finalizado->value => '#10b981', // green
            ProyectoEstadoEnum::Cancelado->value  => '#ef4444', // red
        ];

        // 12 meses inicializados
        $pendiente  = array_fill(1, 12, 0);
        $enProgreso = array_fill(1, 12, 0);
        $finalizado = array_fill(1, 12, 0);
        $cancelado  = array_fill(1, 12, 0);

        // 1 query agrupada
        $rows = Proyecto::query()
            ->selectRaw('MONTH(created_at) as mes, estado, COUNT(*) as total')
            ->whereYear('created_at', $year)
            ->groupByRaw('MONTH(created_at), estado')
            ->get();

        foreach ($rows as $row) {
            $mes = (int) $row->mes;
            $total = (int) $row->total;

            $estado = $row->estado instanceof ProyectoEstadoEnum
                ? $row->estado
                : ProyectoEstadoEnum::from((string) $row->estado);

            match ($estado) {
                ProyectoEstadoEnum::Pendiente  => $pendiente[$mes]  += $total,
                ProyectoEstadoEnum::EnProgreso => $enProgreso[$mes] += $total,
                ProyectoEstadoEnum::Finalizado => $finalizado[$mes] += $total,
                ProyectoEstadoEnum::Cancelado  => $cancelado[$mes]  += $total,
            };
        }

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
                    'name' => 'Pendiente',
                    'type' => 'bar',
                    'stack' => 'total',
                    'data' => array_values($pendiente),
                    'itemStyle' => ['color' => $colors[ProyectoEstadoEnum::Pendiente->value]],
                ],
                [
                    'name' => 'En progreso',
                    'type' => 'bar',
                    'stack' => 'total',
                    'data' => array_values($enProgreso),
                    'itemStyle' => ['color' => $colors[ProyectoEstadoEnum::EnProgreso->value]],
                ],
                [
                    'name' => 'Finalizado',
                    'type' => 'bar',
                    'stack' => 'total',
                    'data' => array_values($finalizado),
                    'itemStyle' => ['color' => $colors[ProyectoEstadoEnum::Finalizado->value]],
                ],
                [
                    'name' => 'Cancelado',
                    'type' => 'bar',
                    'stack' => 'total',
                    'data' => array_values($cancelado),
                    'itemStyle' => ['color' => $colors[ProyectoEstadoEnum::Cancelado->value]],
                ],
            ],
        ];
    }
}
