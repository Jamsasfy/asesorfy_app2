<?php

namespace App\Filament\Widgets;

use App\Models\ComisionMensual;
use App\Models\ComercialHistorialObjetivo;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class ComisionesDelMesWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    public static function canView(): bool
    {
        return Auth::user()?->can('View:ComisionesDelMesWidget') ?? false;
    }

    protected function getStats(): array
    {
        $user = Auth::user();

        // Mes actual (en borrador)
        $mesActual = now();
        $historialActual = ComercialHistorialObjetivo::where('comercial_id', $user->id)
            ->where('año', $mesActual->year)
            ->where('mes', $mesActual->month)
            ->first();

        // Último mes aprobado
        $historialAnterior = ComercialHistorialObjetivo::where('comercial_id', $user->id)
            ->where('estado', 'aprobada')
            ->latest('año')
            ->latest('mes')
            ->first();

        $stats = [];

        // Stat 1: Comisión mes actual (borrador)
        if ($historialActual) {
            $stats[] = Stat::make(
                'Comisión ' . ucfirst($mesActual->locale('es')->monthName),
                '€' . number_format($historialActual->total_comisiones_calculado, 2, ',', '.')
            )
                ->description($historialActual->alcanzo_todos_minimos_obligatorios ? '✅ Alcanza mínimos' : '❌ No alcanza mínimos')
                ->descriptionIcon($historialActual->alcanzo_todos_minimos_obligatorios ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
                ->color($historialActual->alcanzo_todos_minimos_obligatorios ? 'success' : 'danger');
        } else {
            $stats[] = Stat::make(
                'Comisión ' . ucfirst($mesActual->locale('es')->monthName),
                'Pendiente cálculo'
            )
                ->description('Se calculará el día 1 del mes siguiente')
                ->descriptionIcon('heroicon-o-clock')
                ->color('gray');
        }

        // Stat 2: Bonos mes actual
        if ($historialActual) {
            $stats[] = Stat::make('Bonos', '€' . number_format($historialActual->total_bonos, 2, ',', '.'))
                ->descriptionIcon('heroicon-o-gift')
                ->color('warning');
        }

        // Stat 3: Total a cobrar mes anterior aprobado
        if ($historialAnterior) {
            $mesAnteriorNombre = \Carbon\Carbon::create($historialAnterior->año, $historialAnterior->mes, 1)
                ->locale('es')
                ->monthName;

            $stats[] = Stat::make(
                'Total de ' . ucfirst($mesAnteriorNombre) . ' (' . $historialAnterior->estado . ')',
                '€' . number_format($historialAnterior->total_final, 2, ',', '.')
            )
                ->description('Comisión + Bonos')
                ->descriptionIcon('heroicon-o-currency-euro')
                ->color($historialAnterior->estado === 'pagada' ? 'success' : 'warning');
        }

        return $stats;
    }
}
