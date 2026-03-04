<?php

namespace App\Filament\Widgets;

use App\Enums\ClienteEstadoEnum;
use App\Models\Cliente;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Elemind\FilamentECharts\Widgets\EChartWidget;

class AsesoresClientesAsignadosHorizontalBarChart extends EChartWidget
{
    use HasWidgetShield;

    protected static ?string $heading = '👥 Asesores · Clientes por estado (apilado) + sin asignar';
    protected static int $contentHeight = 620;
    protected static ?int $sort = 10;
    protected static bool $isLazy = true;


    protected function getOptions(): array
    {
        $states = [
            ClienteEstadoEnum::PENDIENTE->value,
            ClienteEstadoEnum::PENDIENTE_ASIGNACION->value,
            ClienteEstadoEnum::ACTIVO->value,
            ClienteEstadoEnum::REQUIERE_ATENCION->value,
            ClienteEstadoEnum::IMPAGADO->value,
            ClienteEstadoEnum::BLOQUEADO->value,
            ClienteEstadoEnum::RESCINDIDO->value,
            ClienteEstadoEnum::BAJA->value,
        ];

        $stateLabels = [
            ClienteEstadoEnum::PENDIENTE->value            => 'Pendiente',
            ClienteEstadoEnum::PENDIENTE_ASIGNACION->value => 'Pend. asignación',
            ClienteEstadoEnum::ACTIVO->value               => 'Activo',
            ClienteEstadoEnum::REQUIERE_ATENCION->value    => 'Requiere atención',
            ClienteEstadoEnum::IMPAGADO->value             => 'Impagado',
            ClienteEstadoEnum::BLOQUEADO->value            => 'Bloqueado',
            ClienteEstadoEnum::RESCINDIDO->value           => 'Rescindido',
            ClienteEstadoEnum::BAJA->value                 => 'Baja',
        ];

        $colors = [
            ClienteEstadoEnum::PENDIENTE->value            => '#f59e0b', // amarillo
            ClienteEstadoEnum::PENDIENTE_ASIGNACION->value => '#f59e0b', // amarillo
            ClienteEstadoEnum::ACTIVO->value               => '#10b981',
            ClienteEstadoEnum::REQUIERE_ATENCION->value    => '#3b82f6',
            ClienteEstadoEnum::IMPAGADO->value             => '#ef4444',
            ClienteEstadoEnum::BLOQUEADO->value            => '#111827',
            ClienteEstadoEnum::RESCINDIDO->value           => '#6b7280',
            ClienteEstadoEnum::BAJA->value                 => '#9ca3af',
        ];

        $rows = Cliente::query()
            ->selectRaw('asesor_id, estado, COUNT(*) as total')
            ->whereIn('estado', $states)
            ->groupBy('asesor_id', 'estado')
            ->get();

        $asesorIds = $rows
            ->pluck('asesor_id')
            ->filter(fn ($id) => ! is_null($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $namesById = User::query()
            ->whereIn('id', $asesorIds)
            ->pluck('name', 'id');

        $counts = [];

        foreach ($rows as $row) {
            $key = is_null($row->asesor_id) ? 'sin_asignar' : (int) $row->asesor_id;

            $estado = $row->estado instanceof ClienteEstadoEnum
                ? $row->estado->value
                : (string) $row->estado;

            $counts[$key][$estado] = (int) $row->total;
        }

        // Orden asesores por total desc
        $totalsByAsesor = [];
        foreach ($asesorIds as $id) {
            $sum = 0;
            foreach ($states as $st) {
                $sum += (int) ($counts[$id][$st] ?? 0);
            }
            $totalsByAsesor[$id] = $sum;
        }
        arsort($totalsByAsesor);

        $rowKeys = array_keys($totalsByAsesor);

        // sin asignar abajo siempre
        $rowKeys[] = 'sin_asignar';

        $yLabels = [];
        foreach ($rowKeys as $k) {
            $yLabels[] = $k === 'sin_asignar'
                ? '⚠️ Sin asignar'
                : (string) ($namesById[$k] ?? "Asesor #{$k}");
        }

        $series = [];
        foreach ($states as $st) {
            $data = [];
            foreach ($rowKeys as $k) {
                $data[] = (int) ($counts[$k][$st] ?? 0);
            }

            $series[] = [
                'name' => $stateLabels[$st] ?? $st,
                'type' => 'bar',
                'stack' => 'total',
                'barMaxWidth' => 16,
                'barCategoryGap' => '30%',
                'barGap' => '0%',
                'data' => $data,
                'itemStyle' => [
                    'color' => $colors[$st] ?? '#3b82f6',
                ],
            ];
        }

        // ✅ Scroll interno si hay más de 10 filas
        $enableScroll = count($yLabels) > 10;

        // Mostrar ~10 filas visibles: end% = (10 / total) * 100
        $visibleRows = 10;
        $endPercent = (int) max(20, min(100, round(($visibleRows / max(1, count($yLabels))) * 100)));

        $options = [
            'tooltip' => [
                'trigger' => 'axis',
                'axisPointer' => ['type' => 'shadow'],
            ],
            'legend' => [
                'top' => 0,
                'type' => 'scroll',
            ],
            'grid' => [
                'left' => '4%',
                'right' => $enableScroll ? '4%' : '2%',
                'top' => 50,
                'bottom' => 10,
                'containLabel' => true,
            ],
            'xAxis' => [
                'type' => 'value',
                'name' => 'Clientes',
                'splitLine' => ['show' => true],
            ],
            'yAxis' => [
                'type' => 'category',
                'data' => $yLabels,
                'axisLabel' => [
                    'interval' => 0,
                    'overflow' => 'truncate',
                    'width' => 220,
                ],
                'axisTick' => ['show' => false],
                'axisLine' => ['show' => false],
            ],
            'series' => $series,
        ];

        // ✅ IMPORTANTÍSIMO: si NO hay scroll, NO devolvemos dataZoom (nada de [])
        if ($enableScroll) {
            $options['dataZoom'] = [
                [
                    'type' => 'slider',
                    'yAxisIndex' => 0,
                    'right' => 6,
                    'start' => 0,
                    'end' => $endPercent,
                    'filterMode' => 'none',
                ],
                [
                    'type' => 'inside',
                    'yAxisIndex' => 0,
                    'filterMode' => 'none',
                ],
            ];
        }

        return $options;
    }
}
