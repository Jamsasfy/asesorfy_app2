<?php

namespace App\Filament\Pages;

use App\Models\ComercialContratoIncentivo;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class MiContrato extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $navigationLabel = 'Mi Contrato de Comisiones';
    protected static ?string $title = 'Mi Contrato de Comisiones';
    protected static string|\UnitEnum|null $navigationGroup = 'Mi espacio de trabajo';
    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.mi-contrato';

    public ?ComercialContratoIncentivo $contratoBase = null;
    public Collection $anexos;

    public function mount(): void
    {
        $user = Auth::user();

        $this->contratoBase = $user->contratosIncentivos()
            ->where('tipo', 'base')
            ->latest('created_at')
            ->first();

        $this->anexos = $user->contratosIncentivos()
            ->where('tipo', 'anexo')
            ->when($this->contratoBase, fn ($q) => $q->where('contrato_base_id', $this->contratoBase->id))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getTitle(): string
    {
        return 'Mi Contrato de Comisiones';
    }
}
