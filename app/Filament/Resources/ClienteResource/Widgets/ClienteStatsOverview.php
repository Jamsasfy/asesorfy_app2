<?php

namespace App\Filament\Resources\ClienteResource\Widgets;

use App\Models\Cliente;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClienteStatsOverview extends StatsOverviewWidget
{
    protected array|int|null $columns = [
    'default' => 2,
    'md'      => 3,
    'lg'      => 4,
    'xl'      => 6,
];


    protected function getStats(): array
    {
        $total          = Cliente::count();
        $sinAsignar     = Cliente::whereNull('asesor_id')->count();
        $activo         = Cliente::where('estado', 'activo')->count();
        $atencionImpago = Cliente::whereIn('estado', ['requiere_atencion', 'impagado'])->count();
        $bloqueados     = Cliente::where('estado', 'bloqueado')->count();
        $rescindido     = Cliente::where('estado', 'rescindido')->count();
        $baja           = Cliente::where('estado', 'baja')->count();

        return [
            Stat::make('Sin asignar', $sinAsignar)
                ->description("de {$total} clientes totales en AsesorFy")
                ->descriptionIcon('heroicon-m-adjustments-horizontal')
                ->color($sinAsignar > 0 ? 'warning' : 'success'),

            Stat::make('Clientes en estado Activo', $activo)
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Requiere Atención / Impagados', $atencionImpago)
                ->description(
                    $atencionImpago > 0
                        ? 'Impagados o que requieren atención'
                        : 'No hay incidencias'
                )
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color($atencionImpago > 0 ? 'danger' : 'success'),

            Stat::make('Bloqueado', $bloqueados)
                ->description($bloqueados > 0 ? 'Clientes bloqueados' : 'Todo al día 😎')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($bloqueados > 0 ? 'danger' : 'success'),

            Stat::make('Rescindidos', $rescindido)
                ->description($rescindido > 0 ? 'Contratos rescindidos' : 'Todo al día 😎')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($rescindido > 0 ? 'danger' : 'success'),

            Stat::make('Clientes en estado de Baja', $baja)
                ->description($baja > 0 ? 'Clientes de baja' : 'Todo al día 😎')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color($baja > 0 ? 'danger' : 'success'),
        ];
    }
}
