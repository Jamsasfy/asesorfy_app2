<?php

namespace App\Filament\Pages;

use App\Models\ComercialHistorialObjetivo;
use App\Models\ComisionMensual;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class VerDetalleComision extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $slug = 'ver-detalle-comision/{historialId}';
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.ver-detalle-comision';

    public int $historialId;
    public ComercialHistorialObjetivo $historial;

    public function mount(int $historialId): void
    {
        $this->historialId = $historialId;
        $this->historial   = ComercialHistorialObjetivo::findOrFail($historialId);

        abort_if(
            $this->historial->comercial_id !== Auth::id()
            && ! Auth::user()?->hasRole('super_admin'),
            403
        );
    }

    public function getTitle(): string
    {
        if (! isset($this->historial)) {
            return 'Detalle de comisión';
        }

        $fecha = Carbon::create($this->historial->año, $this->historial->mes, 1);

        return 'Detalle de comisiones — ' . ucfirst($fecha->locale('es')->isoFormat('MMMM YYYY'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('volver')
                ->label('Volver a Mis Comisiones')
                ->icon('heroicon-o-arrow-left')
                ->url(MisComisiones::getUrl())
                ->color('gray'),
        ];
    }

    protected function getViewData(): array
    {
        $comisionesMensuales = ComisionMensual::where('comercial_id', $this->historial->comercial_id)
            ->where('año', $this->historial->año)
            ->where('mes', $this->historial->mes)
            ->with([
                'regla',
                'detalles.servicio',
                'detalles.clienteSuscripcion',
                'detalles.factura.cliente',
            ])
            ->get();

        return [
            'comisionesMensuales' => $comisionesMensuales,
        ];
    }
}
