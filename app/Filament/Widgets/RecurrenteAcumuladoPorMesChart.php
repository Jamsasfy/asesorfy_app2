<?php

namespace App\Filament\Widgets;

use App\Enums\CicloFacturacionEnum;
use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\ServicioTipoEnum;
use App\Models\ClienteSuscripcion;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Elemind\FilamentECharts\Widgets\EChartWidget;
use Illuminate\Support\Carbon;

class RecurrenteAcumuladoPorMesChart extends EChartWidget
{
    use HasWidgetShield;

    protected static ?string $heading = '📈 Recurrente · MRR nuevo vs MRR facturable (acumulado)';
    protected static int $contentHeight = 360;
    protected static ?int $sort = 6;
    protected static bool $isLazy = true;

    public ?string $filter = null;

    // ❌ SIN mount()

    public function getFilters(): ?array
    {
        $currentYear = (int) now()->year;

        $years = ClienteSuscripcion::query()
            ->whereNotNull('fecha_inicio')
            ->selectRaw('YEAR(fecha_inicio) as y')
            ->whereYear('fecha_inicio', '<=', $currentYear)
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

        $labels = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

        $mrrNuevo      = array_fill(1, 12, 0.0);
        $mrrFacturable = array_fill(1, 12, 0.0);

        $startYear = Carbon::create($year, 1, 1)->startOfDay();
        $endYear   = Carbon::create($year, 12, 31)->endOfDay();

        // ✅ CLAVE: solo RECURRENTE (si no, se te dispara y no cuadra con Temperatura)
        $subs = ClienteSuscripcion::query()
            ->whereHas('servicio', function ($q) {
                $q->where('tipo', ServicioTipoEnum::RECURRENTE);
            })
            ->whereNotNull('fecha_inicio')
            ->whereDate('fecha_inicio', '<=', $endYear->toDateString())
            ->where(function ($q) use ($startYear) {
                $q->whereNull('fecha_fin')
                  ->orWhereDate('fecha_fin', '>=', $startYear->toDateString());
            })
            ->with(['servicio:id,precio_base,tipo'])
            ->get([
                'id',
                'estado',
                'fecha_inicio',
                'fecha_fin',
                'precio_acordado',
                'cantidad',
                'ciclo_facturacion',
                'no_cobrar_primer_periodo',
                'servicio_id',
            ]);

        foreach ($subs as $s) {
            $estado = $this->estadoValue($s->estado);

            // ❌ Estados que NO cuentan
            if (in_array($estado, [
                ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION->value,
                ClienteSuscripcionEstadoEnum::IMPAGADA->value,
                ClienteSuscripcionEstadoEnum::CANCELADA->value,
                ClienteSuscripcionEstadoEnum::FINALIZADA->value,
                ClienteSuscripcionEstadoEnum::REEMPLAZADA->value,
                ClienteSuscripcionEstadoEnum::PAUSADA->value,
            ], true)) {
                continue;
            }

            // ✅ Estados que cuentan
            $cuenta = in_array($estado, [
                ClienteSuscripcionEstadoEnum::ACTIVA->value,
                ClienteSuscripcionEstadoEnum::EN_PRUEBA->value,
                ClienteSuscripcionEstadoEnum::PENDIENTE_CANCELACION->value,
            ], true);

            if (! $cuenta) {
                continue;
            }

            // ⚠️ Tu regla: PENDIENTE_CANCELACION solo cuenta el mes en curso (no en meses futuros del gráfico)
            if ($estado === ClienteSuscripcionEstadoEnum::PENDIENTE_CANCELACION->value) {
                // Si el año del gráfico es el actual, solo cuenta en el mes actual.
                if ($year === (int) now()->year) {
                    $mesActual = (int) now()->month;

                    // Dejamos que pase, pero luego solo lo sumaremos en ese mes.
                    // (controlamos abajo cuando iteramos meses)
                } else {
                    continue;
                }
            }

            $fechaInicio = $s->fecha_inicio ? Carbon::parse($s->fecha_inicio)->startOfDay() : null;
            if (! $fechaInicio) {
                continue;
            }

            $fechaFin = $s->fecha_fin ? Carbon::parse($s->fecha_fin)->endOfDay() : null;

            // ✅ Precio base: precio_acordado o fallback a servicio->precio_base (igual que Temperatura)
            $cantidad = max(1, (int) ($s->cantidad ?? 1));

            $precioBase =
                (float) ($s->precio_acordado ?? 0)
                ?: (float) ($s->servicio?->precio_base ?? 0);

            if ($precioBase <= 0) {
                continue;
            }

            $monthly = $this->toMonthly(
                $precioBase,
                $cantidad,
                $s->ciclo_facturacion
            );

            // ✅ MRR nuevo: mes de fecha_inicio (si cae en el año)
            if ((int) $fechaInicio->year === $year) {
                $m = (int) $fechaInicio->month;
                if ($m >= 1 && $m <= 12) {
                    $mrrNuevo[$m] += $monthly;
                }
            }

            // ✅ Facturable desde mes siguiente al inicio (+1 si no_cobrar_primer_periodo)
            $billFrom = $fechaInicio->copy()->startOfMonth()->addMonth();
            if ((bool) $s->no_cobrar_primer_periodo) {
                $billFrom->addMonth();
            }

            $mesActual = (int) now()->month;

            for ($month = 1; $month <= 12; $month++) {
                $monthStart = Carbon::create($year, $month, 1)->startOfDay();

                if ($billFrom->gt($monthStart)) {
                    continue;
                }

                if ($fechaFin && $fechaFin->lt($monthStart)) {
                    continue;
                }

                // ⚠️ PENDIENTE_CANCELACION: solo mes actual (si año actual)
                if ($estado === ClienteSuscripcionEstadoEnum::PENDIENTE_CANCELACION->value) {
                    if ($year !== (int) now()->year || $month !== $mesActual) {
                        continue;
                    }
                }

                $mrrFacturable[$month] += $monthly;
            }
        }

        $nuevoData = array_map(fn ($v) => round((float) $v, 2), array_values($mrrNuevo));
        $factData  = array_map(fn ($v) => round((float) $v, 2), array_values($mrrFacturable));

        return [
            'tooltip' => [
                'trigger' => 'axis',
                'axisPointer' => ['type' => 'line'],
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
                'type' => 'value',
                'name' => '€/mes',
            ],
            'series' => [
                [
                    'name' => 'MRR nuevo (cerrado ese mes)',
                    'type' => 'line',
                    'smooth' => true,
                    'data' => $nuevoData,
                    'lineStyle' => ['width' => 3, 'color' => '#3b82f6'],
                    'itemStyle' => ['color' => '#3b82f6'],
                ],
                [
                    'name' => 'MRR facturable (acumulado)',
                    'type' => 'line',
                    'smooth' => true,
                    'data' => $factData,
                    'lineStyle' => ['width' => 3, 'color' => '#10b981'],
                    'itemStyle' => ['color' => '#10b981'],
                ],
            ],
        ];
    }

    private function estadoValue($estado): string
    {
        return $estado instanceof \BackedEnum ? (string) $estado->value : (string) $estado;
    }

    private function toMonthly(float $precioBase, int $cantidad, $cicloFacturacion): float
    {
        $cantidad = $cantidad > 0 ? $cantidad : 1;
        $base = $precioBase * $cantidad;

        $ciclo = $cicloFacturacion instanceof \BackedEnum
            ? (string) $cicloFacturacion->value
            : (string) $cicloFacturacion;

        return match ($ciclo) {
            CicloFacturacionEnum::MENSUAL->value    => $base,
            CicloFacturacionEnum::TRIMESTRAL->value => round($base / 3, 6),
            CicloFacturacionEnum::ANUAL->value      => round($base / 12, 6),
            default                                => $base,
        };
    }
}
