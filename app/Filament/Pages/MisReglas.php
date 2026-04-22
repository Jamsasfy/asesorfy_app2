<?php

namespace App\Filament\Pages;

use App\Models\ConfiguracionComisiones;
use App\Models\Servicio;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class MisReglas extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Mis Reglas de Comisiones';
    protected static ?string $title = 'Mis Reglas de Comisiones';
    protected static string|\UnitEnum|null $navigationGroup = 'Mi espacio de trabajo';
    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.mis-reglas';

    public Collection $reglas;
    public array $serviciosPorRegla = [];
    public bool $enPeriodoPrueba = false;
    public ?int $mesPrueba = null;
    public bool $tieneContratoFirmado = false;
    public ?Carbon $fechaInicioComercial = null;
    public ?ConfiguracionComisiones $config = null;

    public function mount(): void
    {
        $user = Auth::user();

        $this->reglas = $user->reglasComision()
            ->wherePivot('activa', true)
            ->orderBy('tipo_servicio')
            ->orderBy('nombre')
            ->get();

        $todosLosIds = $this->reglas
            ->flatMap(fn ($regla) => $regla->servicios_ids ?? [])
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        $nombresPorId = count($todosLosIds)
            ? Servicio::whereIn('id', $todosLosIds)->pluck('nombre', 'id')->all()
            : [];

        foreach ($this->reglas as $regla) {
            $ids = $regla->servicios_ids ?? [];
            $this->serviciosPorRegla[$regla->id] = array_values(
                array_filter(array_map(fn ($id) => $nombresPorId[$id] ?? null, $ids))
            );
        }

        $this->enPeriodoPrueba      = $user->estaEnPeriodoPrueba();
        $this->mesPrueba            = $user->getMesActualPrueba();
        $this->tieneContratoFirmado = $user->tieneContratoFirmado();
        $this->fechaInicioComercial = $user->fecha_inicio_comercial;
        $this->config               = ConfiguracionComisiones::first();
    }
}
