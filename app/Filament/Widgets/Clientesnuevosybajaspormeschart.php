<?php

namespace App\Filament\Widgets;

use App\Models\Cliente;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Elemind\FilamentECharts\Widgets\EChartWidget;

class ClientesNuevosYBajasPorMesChart extends EChartWidget
{
    use HasWidgetShield;

    protected static ?string $heading = '👥 Clientes · Nuevos vs Bajas por mes';
    protected static int $contentHeight = 360;
    protected static ?int $sort = 4;
    protected static bool $isLazy = true;


    public ?string $filter = null;

    public function getFilters(): ?array
    {
        $currentYear = (int) now()->year;

        $years = Cliente::query()
            ->selectRaw('YEAR(created_at) as y')
            ->whereYear('created_at', '<=', $currentYear)
            ->distinct()
            ->orderByDesc('y')
            ->pluck('y')
            ->map(fn ($y) => (int) $y)
            ->values()
            ->all();

        $years = collect([$currentYear])
            ->merge($years)
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

        $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        $nuevos = array_fill(1, 12, 0);
        $bajas  = array_fill(1, 12, 0);

        // Clientes nuevos por mes (created_at = momento en que entran en BBDD)
        $rowsNuevos = Cliente::query()
            ->selectRaw('MONTH(created_at) as mes, COUNT(*) as total')
            ->whereYear('created_at', $year)
            ->groupByRaw('MONTH(created_at)')
            ->get();

        foreach ($rowsNuevos as $row) {
            $mes = (int) $row->mes;
            if ($mes >= 1 && $mes <= 12) {
                $nuevos[$mes] = (int) $row->total;
            }
        }

        // Bajas por mes (fecha_baja)
        $rowsBajas = Cliente::query()
            ->selectRaw('MONTH(fecha_baja) as mes, COUNT(*) as total')
            ->whereYear('fecha_baja', $year)
            ->whereNotNull('fecha_baja')
            ->groupByRaw('MONTH(fecha_baja)')
            ->get();

        foreach ($rowsBajas as $row) {
            $mes = (int) $row->mes;
            if ($mes >= 1 && $mes <= 12) {
                $bajas[$mes] = (int) $row->total;
            }
        }

        // Neto acumulado
        $netoAcumulado = [];
        $acum = 0;
        for ($m = 1; $m <= 12; $m++) {
            $acum += $nuevos[$m] - $bajas[$m];
            $netoAcumulado[] = $acum;
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
            'xAxis' => [
                'type' => 'category',
                'data' => $labels,
            ],
            'yAxis' => [
                [
                    'type' => 'value',
                    'name' => 'Clientes',
                ],
                [
                    'type' => 'value',
                    'name' => 'Neto acum.',
                ],
            ],
            'series' => [
                [
                    'name' => 'Nuevos',
                    'type' => 'bar',
                    'data' => array_values($nuevos),
                    'itemStyle' => ['color' => '#10b981'],
                ],
                [
                    'name' => 'Bajas',
                    'type' => 'bar',
                    'data' => array_values($bajas),
                    'itemStyle' => ['color' => '#ef4444'],
                ],
                [
                    'name' => 'Neto acumulado',
                    'type' => 'line',
                    'yAxisIndex' => 1,
                    'smooth' => true,
                    'data' => $netoAcumulado,
                    'lineStyle' => ['width' => 3, 'color' => '#3b82f6'],
                    'itemStyle' => ['color' => '#3b82f6'],
                ],
            ],
        ];
    }
}