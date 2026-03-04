<?php

namespace App\Filament\Resources\ClienteSuscripcionResource\Widgets;

use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\ServicioTipoEnum;
use App\Models\Cliente;
use App\Models\ClienteSuscripcion;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class SuscripcionesTemperatura extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected int|array|null $columns = 5;

    protected function getStats(): array
    {
        $base = ClienteSuscripcion::query();

        $total = (clone $base)->count();

        // ✅ Activas = ACTIVA + EN_PRUEBA (pero NO lo mostramos en descripción)
        $activas = (clone $base)
            ->whereIn('estado', [
                ClienteSuscripcionEstadoEnum::ACTIVA,
                ClienteSuscripcionEstadoEnum::EN_PRUEBA,
            ])
            ->count();

        $impagadas  = (clone $base)->where('estado', ClienteSuscripcionEstadoEnum::IMPAGADA)->count();
        $pausadas   = (clone $base)->where('estado', ClienteSuscripcionEstadoEnum::PAUSADA)->count();
        $canceladas = (clone $base)->where('estado', ClienteSuscripcionEstadoEnum::CANCELADA)->count();

        /**
         * ✅ Base recurrente "operativa" (en rango hoy)
         * - recurrentes
         * - activas / en prueba (y opcional pendiente_cancelacion solo mes actual, ver método)
         */
        $recurrentesEnRangoHoyQuery = (clone $base)
            ->whereHas('servicio', function ($q) {
                $q->where('tipo', ServicioTipoEnum::RECURRENTE);
            })
            ->where('fecha_inicio', '<=', now())
            ->where(function ($q) {
                $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', now());
            });

        // ✅ “Listas para facturar” (tal cual lo tenías)
        $listosQuery = (clone $recurrentesEnRangoHoyQuery)
            ->whereIn('estado', [
                ClienteSuscripcionEstadoEnum::ACTIVA,
                ClienteSuscripcionEstadoEnum::EN_PRUEBA,
            ]);

        $listosParaFacturar = (clone $listosQuery)->count();

        // ✅ Próximas 7 días
        $hoy = Carbon::now()->startOfDay();
        $en7 = Carbon::now()->addDays(7)->endOfDay();

        $proximas7Dias = (clone $base)
            ->whereIn('estado', [
                ClienteSuscripcionEstadoEnum::ACTIVA,
                ClienteSuscripcionEstadoEnum::EN_PRUEBA,
            ])
            ->whereNotNull('proxima_fecha_facturacion')
            ->whereBetween('proxima_fecha_facturacion', [$hoy, $en7])
            ->count();

        /**
         * ✅ NUEVO: MRR acumulado (run-rate mensual equivalente, NETO, sin IVA)
         * - recurrentes en rango hoy
         * - estados que cuentan (ACTIVA, EN_PRUEBA, + PENDIENTE_CANCELACION solo mes actual)
         */
        $mrrAcumulado = $this->calcularMrrAcumuladoNeto(clone $recurrentesEnRangoHoyQuery);

        /**
         * ✅ Ajuste: “Aprox. próximo mes (€)” ahora significa:
         *      € estimados a emitir en el mes siguiente (con IVA),
         *      usando proxima_fecha_facturacion (lo más fiel a "lo que se va a facturar en marzo").
         */
        $mesObjetivo = now()->addMonthNoOverflow();
        $fromMes = $mesObjetivo->copy()->startOfMonth();
        $toMes   = $mesObjetivo->copy()->endOfMonth();

        $totalAFacturarProxMes = $this->calcularTotalFacturacionMesEuros($fromMes, $toMes);

        return [
            Stat::make('Total', number_format($total, 0, ',', '.'))
                ->icon('heroicon-m-rectangle-stack')
                ->color('info')
                ->extraAttributes(['class' => 'fi-ta-text text-sky-600 dark:text-sky-400']),

            Stat::make('Activas', number_format($activas, 0, ',', '.'))
                ->icon('heroicon-m-check-circle')
                ->color('success')
                ->extraAttributes(['class' => 'fi-ta-text text-emerald-600 dark:text-emerald-400']),

            Stat::make('Impagadas', number_format($impagadas, 0, ',', '.'))
                ->icon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->extraAttributes(['class' => 'fi-ta-text text-rose-600 dark:text-rose-400']),

            Stat::make('Pausadas', number_format($pausadas, 0, ',', '.'))
                ->icon('heroicon-m-pause-circle')
                ->color('warning')
                ->extraAttributes(['class' => 'fi-ta-text text-amber-600 dark:text-amber-400']),

            Stat::make('Canceladas', number_format($canceladas, 0, ',', '.'))
                ->icon('heroicon-m-x-circle')
                ->color('gray')
                ->extraAttributes(['class' => 'fi-ta-text text-gray-600 dark:text-gray-400']),

            Stat::make('Listas para facturar', number_format($listosParaFacturar, 0, ',', '.'))
                ->icon('heroicon-m-banknotes')
                ->color($listosParaFacturar > 0 ? 'info' : 'gray')
                ->description('Activas + recurrentes + en rango')
                ->extraAttributes([
                    'class' => $listosParaFacturar > 0
                        ? 'fi-ta-text text-sky-600 dark:text-sky-400'
                        : 'fi-ta-text text-gray-600 dark:text-gray-400',
                ]),

            Stat::make('MRR acumulado (€/mes)', $this->formatEuro($mrrAcumulado))
                ->icon('heroicon-m-chart-bar-square')
                ->color($mrrAcumulado > 0 ? 'success' : 'gray')
                ->description('Run-rate mensual neto (equivalente)')
                ->extraAttributes([
                    'class' => $mrrAcumulado > 0
                        ? 'fi-ta-text text-emerald-600 dark:text-emerald-400'
                        : 'fi-ta-text text-gray-600 dark:text-gray-400',
                ]),

            Stat::make('Aprox. próximo mes (€)', $this->formatEuro($totalAFacturarProxMes))
                ->icon('heroicon-m-currency-euro')
                ->color($totalAFacturarProxMes > 0 ? 'info' : 'gray')
                ->description('€ a emitir (con IVA) según próxima fecha')
                ->extraAttributes([
                    'class' => $totalAFacturarProxMes > 0
                        ? 'fi-ta-text text-sky-600 dark:text-sky-400'
                        : 'fi-ta-text text-gray-600 dark:text-gray-400',
                ]),

            Stat::make('Próximas 7 días', number_format($proximas7Dias, 0, ',', '.'))
                ->icon('heroicon-m-calendar-days')
                ->color($proximas7Dias > 0 ? 'warning' : 'gray')
                ->description('Según próxima fecha')
                ->extraAttributes([
                    'class' => $proximas7Dias > 0
                        ? 'fi-ta-text text-amber-600 dark:text-amber-400'
                        : 'fi-ta-text text-gray-600 dark:text-gray-400',
                ]),
        ];
    }

    /**
     * ✅ MRR acumulado neto (sin IVA): suma mensual equivalente de recurrentes en rango
     * Estados que cuentan:
     * - ACTIVA, EN_PRUEBA
     * - PENDIENTE_CANCELACION SOLO si es el mes actual (tu regla del chart)
     */
    private function calcularMrrAcumuladoNeto($baseQuery): float
    {
        $total = 0.0;

        $baseQuery
            ->with([
                'servicio:id,precio_base,tipo',
            ])
            ->select([
                'id',
                'estado',
                'servicio_id',
                'precio_acordado',
                'cantidad',
                'ciclo_facturacion',
            ])
            ->chunk(200, function ($rows) use (&$total) {
                foreach ($rows as $s) {
                    $estado = $this->estadoValue($s->estado);

                    // Estados que NO cuentan
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

                    // Estados que cuentan
                    $cuenta = in_array($estado, [
                        ClienteSuscripcionEstadoEnum::ACTIVA->value,
                        ClienteSuscripcionEstadoEnum::EN_PRUEBA->value,
                        ClienteSuscripcionEstadoEnum::PENDIENTE_CANCELACION->value,
                    ], true);

                    if (! $cuenta) {
                        continue;
                    }

                    // PENDIENTE_CANCELACION solo mes actual
                    if ($estado === ClienteSuscripcionEstadoEnum::PENDIENTE_CANCELACION->value) {
                        if (! now()->isSameMonth(now())) {
                            // (esto siempre true) → dejamos el check explícito por claridad
                        }
                        // Para el MRR "a día de hoy" sí la contamos en el mes actual.
                    }

                    $cantidad = max(1, (int) ($s->cantidad ?? 1));

                    $base =
                        (float) ($s->precio_acordado ?? 0)
                        ?: (float) ($s->servicio?->precio_base ?? 0);

                    if ($base <= 0) {
                        continue;
                    }

                    $mrrMensual = $this->toMonthly($base, $cantidad, $s->ciclo_facturacion);

                    $total += $mrrMensual;
                }
            });

        return round($total, 2);
    }

    /**
     * ✅ € a emitir en un mes concreto (con IVA) usando proxima_fecha_facturacion
     * (esto es lo que quieres que cuadre con "marzo" en la gráfica si haces la serie por mes)
     */
    private function calcularTotalFacturacionMesEuros(Carbon $from, Carbon $to): float
    {
        $total = 0.0;

        ClienteSuscripcion::query()
            ->whereHas('servicio', fn ($q) => $q->where('tipo', ServicioTipoEnum::RECURRENTE))
            ->whereIn('estado', [
                ClienteSuscripcionEstadoEnum::ACTIVA,
                ClienteSuscripcionEstadoEnum::EN_PRUEBA,
                ClienteSuscripcionEstadoEnum::PENDIENTE_CANCELACION,
            ])
            ->whereNotNull('proxima_fecha_facturacion')
            ->whereBetween('proxima_fecha_facturacion', [$from, $to])
            ->with([
                'cliente:id,codigo_postal,provincia',
                'servicio:id,precio_base,tipo',
            ])
            ->select(['id', 'cliente_id', 'servicio_id', 'estado', 'precio_acordado', 'cantidad'])
            ->chunk(200, function ($rows) use (&$total, $from) {
                foreach ($rows as $s) {
                    $estado = $this->estadoValue($s->estado);

                    // PENDIENTE_CANCELACION solo cuenta el mes en curso (tu regla)
                    if ($estado === ClienteSuscripcionEstadoEnum::PENDIENTE_CANCELACION->value) {
                        if (! $from->isSameMonth(now())) {
                            continue;
                        }
                    }

                    $cantidad = max(1, (int) ($s->cantidad ?? 1));

                    $base =
                        (float) ($s->precio_acordado ?? 0)
                        ?: (float) ($s->servicio?->precio_base ?? 0);

                    if ($base <= 0) {
                        continue;
                    }

                    $base *= $cantidad;

                    $iva = 0.0;
                    if ($s->cliente) {
                        $iva = (float) Cliente::getPorcentajeImpuesto($s->cliente->codigo_postal, $s->cliente->provincia);
                    }

                    $total += round($base * (1 + ($iva / 100)), 2);
                }
            });

        return round($total, 2);
    }

    private function estadoValue($estado): string
    {
        return $estado instanceof \BackedEnum ? (string) $estado->value : (string) $estado;
    }

    /**
     * Convierte un precio a MRR mensual equivalente según ciclo.
     * Nota: aquí NO aplicamos IVA (MRR neto).
     */
    private function toMonthly(float $precioBase, int $cantidad, $cicloFacturacion): float
    {
        $cantidad = $cantidad > 0 ? $cantidad : 1;
        $base = $precioBase * $cantidad;

        $ciclo = $cicloFacturacion instanceof \BackedEnum
            ? (string) $cicloFacturacion->value
            : (string) $cicloFacturacion;

        return match ($ciclo) {
            'mensual'    => $base,
            'trimestral' => round($base / 3, 6),
            'anual'      => round($base / 12, 6),
            default      => $base,
        };
    }

    private function formatEuro(float $value): string
    {
        return number_format($value, 2, ',', '.') . ' €';
    }
}
