<?php

namespace App\Filament\Widgets;

use App\Models\ComercialHistorialObjetivo;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class HistorialRendimientoWidget extends Widget
{
    protected string $view = 'filament.widgets.historial-rendimiento-widget';
    protected ?string $pollingInterval = null;
    protected static bool $isDiscovered = false;
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->can('View:HistorialRendimientoWidget') ?? false;
    }

    public function getViewData(): array
    {
        $user = Auth::user();

        // Últimos 12 meses
        $historial = ComercialHistorialObjetivo::where('comercial_id', $user->id)
            ->where('año', '>=', now()->subMonths(12)->year)
            ->orderBy('año', 'desc')
            ->orderBy('mes', 'desc')
            ->limit(12)
            ->get()
            ->reverse();

        // Calcular rachas
        $consecutivosSinMinimo = 0;
        $alternosSinMinimo     = 0;

        foreach ($historial->reverse() as $item) {
            if (!$item->alcanzo_todos_minimos_obligatorios) {
                $consecutivosSinMinimo++;
                $alternosSinMinimo++;
            } else {
                $consecutivosSinMinimo = 0;
            }
        }

        $config = \App\Models\ConfiguracionComisiones::first();
        $enRiesgoConsecutivos = $config && $consecutivosSinMinimo >= ($config->meses_consecutivos_despido - 1);
        $enRiesgoAlternos     = $config && $alternosSinMinimo >= ($config->meses_alternos_despido - 1);

        return [
            'historial'              => $historial,
            'consecutivosSinMinimo'  => $consecutivosSinMinimo,
            'alternosSinMinimo'      => $alternosSinMinimo,
            'enRiesgoConsecutivos'   => $enRiesgoConsecutivos,
            'enRiesgoAlternos'       => $enRiesgoAlternos,
            'config'                 => $config,
        ];
    }
}
