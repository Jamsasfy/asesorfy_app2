<?php

namespace App\Filament\Resources\ClienteResource\RelationManagers;

use App\Enums\FacturaEstadoEnum;
use App\Filament\Resources\FacturaResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Illuminate\Support\Facades\Auth;



class FacturasRelationManager extends RelationManager
{
    protected static string $relationship = 'facturas';

    protected static ?string $relatedResource = FacturaResource::class;

    protected static ?string $title = 'Facturas';

   



    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('numero_factura')

            // =========================
            // COLUMNAS
            // =========================
            ->columns([
                Tables\Columns\TextColumn::make('numero_factura')
                    ->label('Nº Factura')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('fecha_emision')
                    ->label('Emisión')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('metodo_pago')
                    ->label('Método')
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_factura')
                    ->label('Total')
                    ->money('EUR')
                    ->sortable(),
            ])

            // =========================
            // FILTROS
            // =========================
            ->filters([

                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options(
                        collect(FacturaEstadoEnum::cases())
                            ->mapWithKeys(fn ($e) => [
                                $e->value => $e->getLabel(),
                            ])
                            ->toArray()
                    ),

                SelectFilter::make('metodo_pago')
                    ->label('Método de pago')
                    ->options([
                        'transferencia' => 'Transferencia',
                        'domiciliacion' => 'Domiciliación',
                        'stripe'        => 'Stripe',
                        'otro'          => 'Otro',
                    ]),

                DateRangeFilter::make('fecha_emision')
                    ->label('Fecha de emisión'),    

                Filter::make('solo_impagadas')
                    ->label('Solo impagadas')
                    ->query(fn ($query) =>
                        $query->whereIn('estado', [
                            FacturaEstadoEnum::PENDIENTE_PAGO,
                            FacturaEstadoEnum::IMPAGADA,
                        ])
                    ),
            ])

            // =========================
            // ACCIONES POR FILA
            // =========================
            ->recordactions([

                ViewAction::make('ver_pdf')
                    ->label('Ver PDF')
                    ->icon('heroicon-m-document-text')
                    ->url(fn ($record) =>
                        route('facturas.generar-pdf', $record)
                    )
                    ->openUrlInNewTab(),

            ])

            // ❌ Sin acciones de cabecera
            ->headerActions([]);
    }
}
