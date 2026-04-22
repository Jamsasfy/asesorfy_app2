<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ComisionesDelMesWidget;
use App\Filament\Widgets\HistorialRendimientoWidget;
use App\Models\ComercialHistorialObjetivo;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MisComisiones extends Page implements HasTable
{
    use InteractsWithTable;
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Mis Comisiones';
    protected static ?string $title = 'Mis Comisiones';
    protected static string|\UnitEnum|null $navigationGroup = 'Mi espacio de trabajo';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'mis-comisiones';

    protected string $view = 'filament.pages.mis-comisiones';

    public float $comisionMesActual   = 0;
    public float $bonosMesActual      = 0;
    public float $totalFinalMesActual = 0;
    public string $estadoMesActual    = '';
    public bool $superaMinimosMesActual = false;

    public function mount(): void
    {
        $historial = ComercialHistorialObjetivo::where('comercial_id', Auth::id())
            ->where('año', now()->year)
            ->where('mes', now()->month)
            ->first();

        if ($historial) {
            $this->comisionMesActual      = (float) $historial->total_comisiones_calculado;
            $this->bonosMesActual         = (float) $historial->total_bonos;
            $this->totalFinalMesActual    = (float) $historial->total_final;
            $this->estadoMesActual        = (string) ($historial->estado ?? '');
            $this->superaMinimosMesActual = (bool) $historial->alcanzo_todos_minimos_obligatorios;
        }
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ComisionesDelMesWidget::class,
            HistorialRendimientoWidget::class,
        ];
    }

    protected function getTableQuery(): Builder
    {
        return ComercialHistorialObjetivo::query()
            ->where('comercial_id', Auth::id())
            ->orderByDesc('año')
            ->orderByDesc('mes');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('periodo')
                ->label('Período')
                ->getStateUsing(fn ($record) => ucfirst(
                    Carbon::create($record->año, $record->mes, 1)->locale('es')->isoFormat('MMMM YYYY')
                )),

            TextColumn::make('total_comisiones_calculado')
                ->label('Comisiones')
                ->money('EUR')
                ->sortable(),

            TextColumn::make('total_bonos')
                ->label('Bonos')
                ->money('EUR')
                ->sortable(),

            TextColumn::make('total_final')
                ->label('Total Final')
                ->money('EUR')
                ->weight(FontWeight::Bold)
                ->sortable(),

            IconColumn::make('alcanzo_todos_minimos_obligatorios')
                ->label('Mínimos')
                ->boolean()
                ->trueIcon('heroicon-o-check-circle')
                ->falseIcon('heroicon-o-x-circle')
                ->trueColor('success')
                ->falseColor('danger'),

            TextColumn::make('estado')
                ->label('Estado')
                ->badge()
                ->color(fn (string $state) => match ($state) {
                    'aprobada' => 'success',
                    'pagada'   => 'primary',
                    'borrador' => 'warning',
                    default    => 'gray',
                }),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            Action::make('ver_detalle')
                ->label('Ver detalle')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn ($record) => VerDetalleComision::getUrl(['historialId' => $record->id])),

            Action::make('descargar_pdf')
                ->label('Descargar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->visible(fn ($record) => !empty($record->informe_pdf_path))
                ->url(fn ($record) => route('descargar-informe-comision', $record->id))
                ->openUrlInNewTab(),
        ];
    }
}
