<?php

namespace App\Filament\Widgets;

use App\Models\Lead;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Schema;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ComercialLeadsChart extends ChartWidget
{
    use HasFiltersSchema;

    protected ?string $pollingInterval = null;
    protected static bool $isDiscovered = false;
    protected int | string | array $columnSpan = 'full';
    protected ?string $maxHeight = '300px';

    public static function canView(): bool
    {
        return Auth::user()?->can('View:ComercialLeadsChart') ?? false;
    }

    public function getHeading(): ?string
    {
        $startDate = $this->filters['startDate'] ?? now()->startOfMonth()->toDateString();
        $endDate   = $this->filters['endDate'] ?? now()->toDateString();

        $start = \Carbon\Carbon::parse($startDate)->locale('es')->isoFormat('D MMM YYYY');
        $end   = \Carbon\Carbon::parse($endDate)->locale('es')->isoFormat('D MMM YYYY');

        return "Leads Asignados ({$start} - {$end})";
    }

    public function filtersSchema(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('startDate')
                    ->label('Desde')
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->default(now()->startOfMonth()->toDateString()),
                DatePicker::make('endDate')
                    ->label('Hasta')
                    ->native(false)
                    ->displayFormat('d M Y')
                    ->default(now()->toDateString()),
            ])
            ->columns(2);
    }

    protected function getData(): array
    {
        $user = Auth::user();

        $startDate = $this->filters['startDate'] ?? now()->startOfMonth()->toDateString();
        $endDate   = $this->filters['endDate'] ?? now()->toDateString();

        $start    = \Carbon\Carbon::parse($startDate);
        $end      = \Carbon\Carbon::parse($endDate);
        $diffDays = $start->diffInDays($end) + 1;

        $labels = [];
        $data   = [];

        if ($diffDays <= 31) {
            // MODO DÍAS: rango ≤ 31 días
            $leadsData = Lead::where('asignado_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('DATE(created_at) as periodo'),
                    DB::raw('count(*) as total')
                )
                ->groupBy('periodo')
                ->pluck('total', 'periodo')
                ->toArray();

            $current = $start->copy();
            while ($current->lte($end)) {
                $labels[] = $current->format('d M');
                $data[]   = $leadsData[$current->format('Y-m-d')] ?? 0;
                $current->addDay();
            }
        } else {
            // MODO MESES: rango > 31 días
            $leadsData = Lead::where('asignado_id', $user->id)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('DATE_FORMAT(created_at, "%Y-%m") as periodo'),
                    DB::raw('count(*) as total')
                )
                ->groupBy('periodo')
                ->pluck('total', 'periodo')
                ->toArray();

            $current  = $start->copy()->startOfMonth();
            $endMonth = $end->copy()->startOfMonth();
            while ($current->lte($endMonth)) {
                $labels[] = $current->format('M Y');
                $data[]   = $leadsData[$current->format('Y-m')] ?? 0;
                $current->addMonth();
            }
        }

        return [
            'datasets' => [
                [
                    'label'           => 'Leads Asignados',
                    'data'            => $data,
                    'backgroundColor' => '#41c0e9',
                    'borderColor'     => '#41c0e9',
                    'borderWidth'     => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
