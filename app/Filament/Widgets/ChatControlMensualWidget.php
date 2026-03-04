<?php

namespace App\Filament\Widgets;

use App\Models\ChatMensaje;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Elemind\FilamentECharts\Widgets\EChartWidget;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;

class ChatControlMensualWidget extends EChartWidget
{
    use HasWidgetShield;
    use HasFiltersSchema;

    protected static ?string $heading = '💬 Telegram · Mensajes, contestados y tiempos';
    protected static int $contentHeight = 380;
    protected static ?int $sort = 7;
    protected static bool $isLazy = true;


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
            '0'  => 'Todo el año',
            '1'  => 'Enero', '2' => 'Febrero', '3' => 'Marzo', '4' => 'Abril',
            '5'  => 'Mayo',  '6' => 'Junio',   '7' => 'Julio', '8' => 'Agosto',
            '9'  => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
        ];

        return $schema->components([
            Select::make('year')
                ->label('Año')
                ->options(array_combine($years, $years))
                ->default((string) now()->year)
                ->native(false),

            Select::make('month')
                ->label('Mes (ranking)')
                ->options($months)
                ->default((string) now()->month)
                ->native(false),
        ]);
    }

    public function updatedInteractsWithSchemas(string $statePath): void
    {
        $this->updateOptions();
    }

   protected function getOptions(): array
{
    $year = (int) ($this->filters['year'] ?? now()->year);

    $labels = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

    $pendientesReales = array_fill(1, 12, 0); // cliente pendientes reales (B)
    $contestados      = array_fill(1, 12, 0); // cliente no pendientes
    $enviadosAsesor   = array_fill(1, 12, 0); // asesor enviados
    $avgRespMin       = array_fill(1, 12, null); // respuesta media (solo mensajes contestados)
    $sistemaEnviados = array_fill(1, 12, 0);


    // Subquery: último mensaje de asesor por chat
    $lastAsesorSub = ChatMensaje::query()
        ->from('chat_mensajes as a')
        ->selectRaw('a.chat_id, MAX(a.created_at) as last_asesor_at')
        ->where('a.origen', 'asesor')
        ->groupBy('a.chat_id');

    /**
     * 1) Métricas por mes (mensajes cliente) con Pendiente REAL (B)
     * pendiente_real = m.created_at > COALESCE(last_asesor_at, '1970-01-01')
     */
    $rows = ChatMensaje::query()
        ->from('chat_mensajes as m')
        ->leftJoinSub($lastAsesorSub, 'la', function ($join) {
            $join->on('la.chat_id', '=', 'm.chat_id');
        })
        ->where('m.origen', 'cliente')
        ->whereYear('m.created_at', $year)
        ->selectRaw('MONTH(m.created_at) as mes')
        ->selectRaw('COUNT(*) as total_cliente')
        ->selectRaw('SUM(CASE WHEN m.created_at > COALESCE(la.last_asesor_at, "1970-01-01") THEN 1 ELSE 0 END) as pendientes_reales')
        ->selectRaw('SUM(CASE WHEN m.created_at <= COALESCE(la.last_asesor_at, "1970-01-01") THEN 1 ELSE 0 END) as contestados')
        // Tiempo medio de respuesta (solo si hay respuesta posterior)
        ->selectRaw('AVG(
            CASE
                WHEN (
                    SELECT MIN(a2.created_at)
                    FROM chat_mensajes a2
                    WHERE a2.chat_id = m.chat_id
                      AND a2.origen = "asesor"
                      AND a2.created_at > m.created_at
                ) IS NULL THEN NULL
                ELSE TIMESTAMPDIFF(SECOND, m.created_at, (
                    SELECT MIN(a2.created_at)
                    FROM chat_mensajes a2
                    WHERE a2.chat_id = m.chat_id
                      AND a2.origen = "asesor"
                      AND a2.created_at > m.created_at
                ))
            END
        ) as avg_sec')
        ->groupByRaw('MONTH(m.created_at)')
        ->get();

    foreach ($rows as $row) {
        $mes = (int) $row->mes;

        $pendientesReales[$mes] = (int) ($row->pendientes_reales ?? 0);
        $contestados[$mes]      = (int) ($row->contestados ?? 0);

        $avgSec = $row->avg_sec !== null ? (float) $row->avg_sec : null;
        $avgRespMin[$mes] = $avgSec !== null ? round($avgSec / 60, 1) : null;
    }

    /**
     * 2) Mensajes enviados por asesor (AsesorFy) por mes
     */
        $outRows = ChatMensaje::query()
            ->from('chat_mensajes as m')
            ->whereIn('m.origen', ['asesor', 'sistema'])
            ->whereYear('m.created_at', $year)
            ->selectRaw('MONTH(m.created_at) as mes, m.origen, COUNT(*) as total')
            ->groupByRaw('MONTH(m.created_at), m.origen')
            ->get();

        foreach ($outRows as $row) {
            $mes = (int) $row->mes;

            if ($row->origen === 'asesor') {
                $enviadosAsesor[$mes] = (int) ($row->total ?? 0);
            }

            if ($row->origen === 'sistema') {
                $sistemaEnviados[$mes] = (int) ($row->total ?? 0);
            }
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
            'top' => 45,
            'bottom' => 10,
            'containLabel' => true,
        ],
        'xAxis' => [[
            'type' => 'category',
            'data' => $labels,
        ]],
        'yAxis' => [
            ['type' => 'value', 'name' => 'Msgs'],
            ['type' => 'value', 'name' => 'Min'],
        ],
        'series' => [
            [
                'name' => 'Cliente · pendientes reales',
                'type' => 'bar',
                'stack' => 'cliente',
                'data' => array_values($pendientesReales),
                'itemStyle' => ['color' => '#ef4444'],
            ],
            [
                'name' => 'Cliente · contestados',
                'type' => 'bar',
                'stack' => 'cliente',
                'data' => array_values($contestados),
                'itemStyle' => ['color' => '#10b981'],
            ],
            [
                'name' => 'AsesorFy · enviados',
                'type' => 'bar',
                'data' => array_values($enviadosAsesor),
                'itemStyle' => ['color' => '#f59e0b'],
            ],
            [
                'name' => 'Sistema · enviados',
                'type' => 'bar',
                'data' => array_values($sistemaEnviados),
                'itemStyle' => ['color' => '#8b5cf6'], // morado
            ],  
            [
                'name' => 'Tiempo respuesta (avg, min)',
                'type' => 'line',
                'yAxisIndex' => 1,
                'smooth' => true,
                'data' => array_values($avgRespMin),
                'lineStyle' => ['width' => 3, 'color' => '#3b82f6'],
                'itemStyle' => ['color' => '#3b82f6'],
            ],
        ],
    ];
}

    protected function getFooter(): null|string|\Illuminate\Contracts\Support\Htmlable|\Illuminate\Contracts\View\View
    {
        $year = (int) ($this->filters['year'] ?? now()->year);
        $month = (int) ($this->filters['month'] ?? now()->month);

        if ($month < 1 || $month > 12) {
            $month = (int) now()->month;
        }

        /**
         * Ranking:
         * - De cada msg cliente, pillamos el PRIMER msg asesor posterior (user_id y created_at)
         * - Agrupamos por ese user_id
         *
         * Nota: esto es más caro; con el índice recomendado va bien para volumen normal.
         */
        $base = ChatMensaje::query()
            ->from('chat_mensajes as m')
            ->selectRaw('(
                SELECT a.user_id
                FROM chat_mensajes a
                WHERE a.chat_id = m.chat_id
                  AND a.origen = "asesor"
                  AND a.created_at > m.created_at
                ORDER BY a.created_at ASC
                LIMIT 1
            ) as asesor_id')
            ->selectRaw('TIMESTAMPDIFF(SECOND, m.created_at, (
                SELECT a.created_at
                FROM chat_mensajes a
                WHERE a.chat_id = m.chat_id
                  AND a.origen = "asesor"
                  AND a.created_at > m.created_at
                ORDER BY a.created_at ASC
                LIMIT 1
            )) as resp_sec')
            ->where('m.origen', 'cliente')
            ->whereYear('m.created_at', $year)
            ->whereMonth('m.created_at', $month);

        $rows = $base->get()->filter(fn ($r) => filled($r->asesor_id) && filled($r->resp_sec));

        $grouped = $rows->groupBy('asesor_id')->map(function ($items) {
            $n = $items->count();
            $avg = $items->avg('resp_sec');
            return ['n' => $n, 'avg_sec' => (float) $avg];
        })->filter(fn ($x) => $x['n'] >= 5); // mínimo 5 respuestas para ranking serio

        $fast = $grouped->sortBy('avg_sec')->take(5);
        $slow = $grouped->sortByDesc('avg_sec')->take(5);

        $userIds = $fast->keys()->merge($slow->keys())->unique()->values();
        $users = User::query()->whereIn('id', $userIds)->get()->keyBy('id');

        $map = function ($coll) use ($users) {
            return $coll->map(function ($v, $asesorId) use ($users) {
                $name = $users[$asesorId]->name ?? ('Asesor #' . $asesorId);
                return [
                    'name' => $name,
                    'avg_min' => round($v['avg_sec'] / 60, 1),
                    'n' => (int) $v['n'],
                ];
            })->values();
        };

        return view('filament.widgets.chat-control-footer', [
            'month' => $month,
            'year' => $year,
            'fast' => $map($fast),
            'slow' => $map($slow),
        ]);
    }
}
