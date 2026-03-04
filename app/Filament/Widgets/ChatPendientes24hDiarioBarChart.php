<?php

namespace App\Filament\Widgets;

use App\Models\ChatMensaje;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Elemind\FilamentECharts\Widgets\EChartWidget;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Illuminate\Support\Carbon;

class ChatPendientes24hDiarioBarChart extends EChartWidget
{
    use HasWidgetShield;
    use HasFiltersSchema;

   protected function getHeading(): ?string
    {
        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        $month = (int) ($this->filters['month'] ?? now()->month);
        $year  = (int) ($this->filters['year'] ?? now()->year);

        return "📅 Telegram · Mensajes diarios + pendientes +24h · {$months[$month]} {$year}";
    }
    protected static int $contentHeight = 380;
    protected static ?int $sort = 9;
    protected static bool $isLazy = true;


    // ❌ SIN mount() — dejamos que EChartWidget haga su inicialización

    public function filtersSchema(Schema $schema): Schema
    {
        $currentYear = (int) now()->year;

        $years = ChatMensaje::query()
            ->selectRaw('YEAR(created_at) as y')
            ->whereYear('created_at', '<=', $currentYear)
            ->distinct()
            ->orderByDesc('y')
            ->pluck('y')
            ->map(fn ($y) => (string) (int) $y)
            ->values()
            ->all();

        if (empty($years)) {
            $years = [(string) $currentYear];
        }

        $months = [
            '1'  => 'Enero', '2' => 'Febrero', '3' => 'Marzo', '4' => 'Abril',
            '5'  => 'Mayo',  '6' => 'Junio',   '7' => 'Julio', '8' => 'Agosto',
            '9'  => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
        ];

        return $schema->components([
            Select::make('year')
                ->label('Año')
                ->options(array_combine($years, $years))
                ->default((string) now()->year)
                ->native(false)
                ->live(),

            Select::make('month')
                ->label('Mes')
                ->options($months)
                ->default((string) now()->month)
                ->native(false)
                ->live(),
        ]);
    }

    public function updatedInteractsWithSchemas(string $statePath): void
    {
        $this->dispatch('updateOptions', options: $this->getOptions());
        $this->dispatch('$refresh');
    }

    protected function getOptions(): array
    {
        $year  = (int) ($this->filters['year'] ?? now()->year);
        $month = (int) ($this->filters['month'] ?? now()->month);

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end   = (clone $start)->endOfMonth()->endOfDay();

        $daysInMonth = (int) $start->daysInMonth;

        $labels = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $labels[] = (string) $d;
        }

        $clienteTotal = array_fill(1, $daysInMonth, 0);
        $asesorTotal  = array_fill(1, $daysInMonth, 0);
        $sistemaTotal = array_fill(1, $daysInMonth, 0);

        $rows = ChatMensaje::query()
            ->from('chat_mensajes as m')
            ->whereBetween('m.created_at', [$start, $end])
            ->whereIn('m.origen', ['cliente', 'asesor', 'sistema'])
            ->selectRaw('DAY(m.created_at) as dia, m.origen, COUNT(*) as total')
            ->groupByRaw('DAY(m.created_at), m.origen')
            ->get();

        foreach ($rows as $row) {
            $dia = (int) $row->dia;
            if ($dia < 1 || $dia > $daysInMonth) {
                continue;
            }

            $count = (int) $row->total;

            if ($row->origen === 'cliente') {
                $clienteTotal[$dia] += $count;
            } elseif ($row->origen === 'asesor') {
                $asesorTotal[$dia] += $count;
            } elseif ($row->origen === 'sistema') {
                $sistemaTotal[$dia] += $count;
            }
        }

        $cutoff = now()->subHours(24);

        $lastAsesorSub = ChatMensaje::query()
            ->from('chat_mensajes as a')
            ->selectRaw('a.chat_id, MAX(a.created_at) as last_asesor_at')
            ->where('a.origen', 'asesor')
            ->groupBy('a.chat_id');

        $pend24h = array_fill(1, $daysInMonth, 0);

        $rowsPend = ChatMensaje::query()
            ->from('chat_mensajes as m')
            ->leftJoinSub($lastAsesorSub, 'la', function ($join) {
                $join->on('la.chat_id', '=', 'm.chat_id');
            })
            ->where('m.origen', 'cliente')
            ->whereBetween('m.created_at', [$start, $end])
            ->where('m.created_at', '<=', $cutoff)
            ->whereRaw('m.created_at > COALESCE(la.last_asesor_at, "1970-01-01")')
            ->selectRaw('DAY(m.created_at) as dia, COUNT(*) as total')
            ->groupByRaw('DAY(m.created_at)')
            ->get();

        foreach ($rowsPend as $row) {
            $dia = (int) $row->dia;
            if ($dia >= 1 && $dia <= $daysInMonth) {
                $pend24h[$dia] = (int) $row->total;
            }
        }

        $clienteResto = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $clienteResto[$d] = max(0, (int) $clienteTotal[$d] - (int) $pend24h[$d]);
        }

        $blue = '#3b82f6';
        $red  = '#ef4444';

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
                'top' => 45,
                'bottom' => 10,
                'containLabel' => true,
            ],
            'xAxis' => [
                'type' => 'category',
                'data' => $labels,
            ],
            'yAxis' => [
                'type' => 'value',
                'name' => 'Msgs',
            ],
            'series' => [
                [
                    'name' => 'Asesor',
                    'type' => 'bar',
                    'stack' => 'total',
                    'data' => array_values($asesorTotal),
                    'itemStyle' => ['color' => $blue],
                ],
                [
                    'name' => 'Sistema',
                    'type' => 'bar',
                    'stack' => 'total',
                    'data' => array_values($sistemaTotal),
                    'itemStyle' => ['color' => $blue],
                ],
                [
                    'name' => 'Cliente (resto)',
                    'type' => 'bar',
                    'stack' => 'total',
                    'data' => array_values($clienteResto),
                    'itemStyle' => ['color' => $blue],
                ],
                [
                    'name' => 'Cliente · pendientes +24h',
                    'type' => 'bar',
                    'stack' => 'total',
                    'data' => array_values($pend24h),
                    'itemStyle' => ['color' => $red],
                ],
            ],
        ];
    }
}