<?php

namespace App\Filament\Portal\Resources\Facturas\Tables;

use App\Enums\FacturaEstadoEnum;
use App\Models\Factura;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;

class FacturasTable
{
    public static function configure(Table $table): Table
    {
        $monthOptions = [
            '1'  => 'Enero',
            '2'  => 'Febrero',
            '3'  => 'Marzo',
            '4'  => 'Abril',
            '5'  => 'Mayo',
            '6'  => 'Junio',
            '7'  => 'Julio',
            '8'  => 'Agosto',
            '9'  => 'Septiembre',
            '10' => 'Octubre',
            '11' => 'Noviembre',
            '12' => 'Diciembre',
        ];

        return $table
            ->defaultSort('created_at', 'desc')
            ->deferFilters(false)
            ->recordUrl(null)
            ->emptyStateHeading('No se encontraron facturas de AsesorFy')
            ->emptyStateDescription('Cuando tengas facturas emitidas, aparecerán aquí.')
            // ✅ para que no intente navegar a /{record} al hacer click en la fila
            ->recordUrl(null)
            ->columns([
                TextColumn::make('numero_factura')
                    ->label('Nº')
                    ->searchable()
                    ->weight('bold')
                    ->color('primary')
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Número de factura copiado.'),

            

                TextColumn::make('fecha_emision')
                    ->label('Emisión')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('fecha_vencimiento')
                    ->label('Vencimiento')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('total_factura')
                    ->label('Total')
                    ->money('EUR')
                     ->weight('bold')
                    ->color('warning')
                    ->sortable()
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('anio')
                    ->label('Año')
                    ->options(function (): array {
                        $user = auth()->user();
                        $clienteIds = $user?->clientes()->pluck('clientes.id')->all() ?? [];

                        if (empty($clienteIds)) {
                            return [];
                        }

                        return Factura::query()
                            ->whereIn('cliente_id', $clienteIds)
                            ->whereNotNull('fecha_emision')
                            ->selectRaw('YEAR(fecha_emision) as y')
                            ->distinct()
                            ->orderByDesc('y')
                            ->pluck('y', 'y')
                            ->map(fn ($y) => (string) $y)
                            ->all();
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        $year = $data['value'] ?? null;

                        return $query->when(
                            filled($year),
                            fn (Builder $q) => $q->whereYear('fecha_emision', (int) $year),
                        );
                    }),

                SelectFilter::make('mes')
                    ->label('Mes')
                    ->options(function () use ($monthOptions): array {
                        $user = auth()->user();
                        $clienteIds = $user?->clientes()->pluck('clientes.id')->all() ?? [];

                        if (empty($clienteIds)) {
                            return [];
                        }

                        $months = Factura::query()
                            ->whereIn('cliente_id', $clienteIds)
                            ->whereNotNull('fecha_emision')
                            ->selectRaw('MONTH(fecha_emision) as m')
                            ->distinct()
                            ->orderBy('m')
                            ->pluck('m')
                            ->map(fn ($m) => (string) $m)
                            ->all();

                        $out = [];
                        foreach ($months as $m) {
                            $out[$m] = $monthOptions[$m] ?? $m;
                        }

                        return $out;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        $month = $data['value'] ?? null;

                        return $query->when(
                            filled($month),
                            fn (Builder $q) => $q->whereMonth('fecha_emision', (int) $month),
                        );
                    }),

                Filter::make('pendientes_pago')
                    ->label('Pendientes de pago')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->where('estado', FacturaEstadoEnum::PENDIENTE_PAGO->value)),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                Action::make('ver_pdf')
                    ->label('')
                    ->tooltip('Ver Factura PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('info')
                    ->url(fn (Factura $record) => route('portal.facturas.pdf', $record))
                    ->openUrlInNewTab(),
                
                Action::make('descargar_pdf')
                    ->label('')
                    ->tooltip('Descargar PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->url(fn (Factura $record) => route('portal.facturas.pdf.download', $record))
                    ->openUrlInNewTab(),    
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make('exportar_facturas')
                        ->label('Exportar seleccionadas')
                        ->exports([
                            ExcelExport::make('facturas')
                                ->withColumns([
                                    Column::make('numero_factura')->heading('Nº Factura'),
                                    Column::make('concepto_portal')->heading('Concepto'),
                                    Column::make('fecha_emision')->heading('Emisión')
                                        ->formatStateUsing(fn ($state) => filled($state) ? Carbon::parse($state)->format('d/m/Y') : ''),
                                    Column::make('fecha_vencimiento')->heading('Vencimiento')
                                        ->formatStateUsing(fn ($state) => filled($state) ? Carbon::parse($state)->format('d/m/Y') : ''),
                                    Column::make('estado')->heading('Estado'),
                                    Column::make('metodo_pago')->heading('Método de pago'),
                                    Column::make('base_imponible')->heading('Base imponible'),
                                    Column::make('total_iva')->heading('IVA'),
                                    Column::make('total_factura')->heading('Total'),
                                    Column::make('created_at')->heading('Creada en App')
                                        ->formatStateUsing(fn ($state) => filled($state) ? Carbon::parse($state)->format('d/m/Y - H:i') : ''),
                                    Column::make('updated_at')->heading('Actualizada en App')
                                        ->formatStateUsing(fn ($state) => filled($state) ? Carbon::parse($state)->format('d/m/Y - H:i') : ''),
                                ]),
                        ])
                        ->icon('icon-excel2')
                        ->color('success')
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->modalHeading('Exportar Facturas Seleccionadas')
                        ->modalDescription('Exportarás los datos de las facturas seleccionadas.'),
                ]),
            ]);
    }
}
