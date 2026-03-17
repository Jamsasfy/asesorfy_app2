<?php

namespace App\Filament\Widgets;

use App\Enums\FacturaEstadoEnum;
use App\Models\Factura;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Elemind\FilamentECharts\Widgets\EChartWidget;
use Illuminate\Support\Facades\DB;

class VentasFacturacionPorMesChart extends EChartWidget
{
    use HasWidgetShield;

    protected static ?string $heading = '💶 Ventas y facturación por mes';
    protected static int $contentHeight = 360;
    protected static ?int $sort = 5;
    protected static bool $isLazy = true;


    public ?string $filter = null; // año

    public function getFilters(): ?array
    {
        $currentYear = (int) now()->year;

        $yearsWithFacturas = Factura::query()
            ->selectRaw('YEAR(fecha_emision) as y')
            ->whereNotNull('fecha_emision')
            ->whereYear('fecha_emision', '<=', $currentYear)
            ->distinct()
            ->orderByDesc('y')
            ->pluck('y')
            ->map(fn ($y) => (int) $y)
            ->values()
            ->all();

        $years = collect([$currentYear])
            ->merge($yearsWithFacturas)
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

        // 12 meses inicializados
        $ventas      = array_fill(1, 12, 0);
        $factUni     = array_fill(1, 12, 0.0);
        $factRec     = array_fill(1, 12, 0.0);

        $rows = DB::table('facturas as f')
            ->join('venta_items as vi', 'vi.venta_id', '=', 'f.venta_id')
            ->join('servicios as s', 's.id', '=', 'vi.servicio_id')
            ->whereNotNull('f.fecha_emision')
            ->whereYear('f.fecha_emision', $year)
            ->where('f.estado', FacturaEstadoEnum::PAGADA->value)
            ->join('ventas as v', 'v.id', '=', 'f.venta_id')
            ->where('v.estado', '!=', \App\Enums\VentaEstadoEnum::RECURRENTE_CANCELADO->value)
            ->selectRaw('MONTH(f.fecha_emision) as mes')
            ->selectRaw('COUNT(DISTINCT f.venta_id) as ventas_total')
            ->selectRaw("SUM(CASE WHEN s.tipo = 'unico' THEN vi.subtotal ELSE 0 END) as facturacion_unica")
            ->selectRaw("SUM(CASE WHEN s.tipo = 'recurrente' THEN vi.subtotal ELSE 0 END) as facturacion_recurrente")
            ->groupByRaw('MONTH(f.fecha_emision)')
            ->get();

        foreach ($rows as $row) {
            $mes = (int) $row->mes;

            $ventas[$mes]  = (int) ($row->ventas_total ?? 0);
            $factUni[$mes] = (float) ($row->facturacion_unica ?? 0);
            $factRec[$mes] = (float) ($row->facturacion_recurrente ?? 0);
        }

        $ventasData = array_values($ventas);
        $uniData    = array_map(fn ($v) => round((float) $v, 2), array_values($factUni));
        $recData    = array_map(fn ($v) => round((float) $v, 2), array_values($factRec));

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
            'yAxis' => [
                [
                    'type' => 'value',
                    'name' => 'Ventas',
                ],
                [
                    'type' => 'value',
                    'name' => '€',
                ],
            ],
            'series' => [
                [
                    'name' => 'Ventas',
                    'type' => 'bar',
                    'yAxisIndex' => 0,
                    'data' => $ventasData,
                    'itemStyle' => ['color' => '#3b82f6'], // azul
                ],
                [
                    'name' => 'Facturación Única (€)',
                    'type' => 'line',
                    'yAxisIndex' => 1,
                    'smooth' => true,
                    'data' => $uniData,
                    'lineStyle' => ['width' => 3, 'color' => '#f59e0b'], // ámbar
                    'itemStyle' => ['color' => '#f59e0b'],
                ],
                [
                    'name' => 'Facturación Recurrente (€)',
                    'type' => 'line',
                    'yAxisIndex' => 1,
                    'smooth' => true,
                    'data' => $recData,
                    'lineStyle' => ['width' => 3, 'color' => '#10b981'], // verde
                    'itemStyle' => ['color' => '#10b981'],
                ],
            ],
        ];
    }
}
