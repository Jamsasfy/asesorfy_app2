<?php

namespace App\Filament\Resources\VentaResource\Widgets;

use App\Models\Venta;
use App\Models\VentaItem;
use Filament\Forms\Components\Select; // ¡Importante para el filtro!
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class AnnualSalesChart extends ChartWidget
{
   // protected static ?string $heading = 'Ventas totales de este año';
     // Que el widget ocupe todas las columnas posibles
     protected ?string $maxHeight = '300px';
     protected int | string | array $columnSpan = 'full';

public function getHeading(): string
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user && $user->hasRole('comercial')) {
            return 'Mis Ventas Totales de este Año';
        }
        return 'Ventas Totales del Sistema este Año'; // Título para admin y otros roles
    }

    

    protected function getData(): array
    {
        /** @var User|null $user */
        $user = Auth::user();
        $year = now()->year;

        $ventaItemsQuery = VentaItem::query()
            ->selectRaw('MONTH(v.fecha_venta) as month, s.tipo, SUM(vi.subtotal) as total')
            ->from('venta_items as vi')
            ->join('ventas as v', 'vi.venta_id', '=', 'v.id')
            ->join('servicios as s', 'vi.servicio_id', '=', 's.id')
            ->whereYear('v.fecha_venta', $year)
            ->where('v.estado', \App\Enums\VentaEstadoEnum::COMPLETADA->value);

        if ($user && $user->hasRole('comercial')) {
            // Si es comercial, añadimos el filtro para que solo vea sus ventas.
            // ASEGÚRATE de que 'v.user_id' sea el campo correcto en tu tabla 'ventas'
            // que almacena el ID del comercial. Si es 'v.comercial_id', cámbialo.
            $ventaItemsQuery->where('v.user_id', $user->id);
        }
        // Los super_admins (y otros roles no 'comercial') no tendrán este filtro adicional,
        // por lo que verán los datos globales.

        $results = $ventaItemsQuery
            ->groupByRaw('month, s.tipo')
            ->orderBy('month')
            ->get()
            ->groupBy('tipo'); // Agrupa los resultados por tipo de servicio

        $labels = [
            'Enero', 'Febrero', 'Marzo', 'Abril',
            'Mayo', 'Junio', 'Julio', 'Agosto',
            'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
        ];

        $buildDataset = fn(string $tipo, string $label, string $color) => [
            'label'           => $label,
            'data'            => array_map(fn($i) => 
                (float) ($results->get($tipo)?->pluck('total', 'month')[$i+1] ?? 0)
            , range(0, 11)),
            'backgroundColor' => $color,
            'borderColor' => $color, // Opcional: para que el borde coincida
            'borderWidth' => 1
        ];

        return [
            'labels'   => $labels,
            'datasets' => [
                $buildDataset('unico',      'Único',      'rgba(54, 162, 235, 0.7)'), // Azul
                $buildDataset('recurrente', 'Recurrente', 'rgba(255, 159, 64, 0.7)'), // Naranja
            ],
        ];
    }
    






    protected function getType(): string
    {
        return 'bar';
    }
}
