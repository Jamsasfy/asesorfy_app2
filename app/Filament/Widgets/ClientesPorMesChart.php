<?php

namespace App\Filament\Widgets;

use App\Models\Cliente;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;


class ClientesPorMesChart extends ChartWidget
{
    use HasWidgetShield;

    protected ?string $heading = '📊 Nuevos clientes y usuarios por mes';
    protected ?string $maxHeight = '320px';
    public ?string $filter = '2025';

    public function getFilters(): ?array
    {
        return collect(range(now()->year - 1, now()->year + 1))
            ->mapWithKeys(fn ($year) => [$year => $year])
            ->toArray();
    }

    protected function getData(): array
    {
        $year = $this->filter ?? now()->year;

        $clientesPorMes = Cliente::selectRaw('MONTH(created_at) as mes, COUNT(*) as total')
            ->whereYear('fecha_alta', $year)
            ->groupByRaw('MONTH(created_at)')
            ->pluck('total', 'mes');

        $usuariosPorMes = User::selectRaw('MONTH(created_at) as mes, COUNT(*) as total')
            ->whereYear('created_at', $year)
            ->groupByRaw('MONTH(created_at)')
            ->pluck('total', 'mes');

        $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        $clientesData = [];
        $usuariosData = [];

        foreach (range(1, 12) as $mes) {
            $clientesData[] = $clientesPorMes[$mes] ?? 0;
            $usuariosData[] = $usuariosPorMes[$mes] ?? 0;
        }

        return [
            'datasets' => [
                [
                    'label' => "Clientes",
                    'data' => $clientesData,
                    'backgroundColor' => '#3b82f6',
                    'stack' => 'total',
                ],
                [
                    'label' => "Usuarios",
                    'data' => $usuariosData,
                    'backgroundColor' => '#10b981',
                    'stack' => 'total',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): ?array
    {
        return [
            'scales' => [
                'x' => [
                    'stacked' => true,
                ],
                'y' => [
                    'stacked' => true,
                ],
            ],
        ];
    }
}
