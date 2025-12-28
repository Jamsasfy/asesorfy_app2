<?php

namespace App\Filament\Resources\ClienteResource\RelationManagers;

use App\Models\Lead;
use App\Models\Proyecto;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Storage;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;


class DocumentosVinculadosRelationManager extends RelationManager
{
    protected static string $relationship = 'documentosVinculados';

    protected static ?string $title = 'Documentos de Proyectos y Leads';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $cliente = $this->getOwnerRecord();

                $query->whereHasMorph(
                    'documentable',
                    [Proyecto::class, Lead::class],
                    function ($q, $type) use ($cliente) {
                        if ($type === Proyecto::class) {
                            $q->where('cliente_id', $cliente->id);
                        }

                        if ($type === Lead::class) {
                            $q->where('cliente_id', $cliente->id);
                        }
                    }
                );
            })

            ->columns([

                TextColumn::make('nombre')
                    ->label('Documento')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('tipo.nombre')
                    ->label('Tipo')
                    ->badge(),

                TextColumn::make('subtipo.nombre')
                    ->label('Subtipo')
                    ->badge(),

                TextColumn::make('documentable_type')
                    ->label('Origen')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        Proyecto::class => 'Proyecto',
                        Lead::class     => 'Lead',
                        default         => '—',
                    })
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        Proyecto::class => 'info',
                        Lead::class     => 'warning',
                        default         => 'gray',
                    }),

                IconColumn::make('verificado')
                    ->label('Verificado')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Subido')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])

            ->filters([
                // 📅 Filtro por fecha
                DateRangeFilter::make('created_at')
                    ->label('Fecha de subida'),

                // 🧩 Filtro por origen
                SelectFilter::make('origen')
                    ->label('Origen')
                    ->options([
                        Proyecto::class => 'Proyecto',
                        Lead::class     => 'Lead',
                    ])
                    ->query(function ($query, array $data) {
                        if (filled($data['value'])) {
                            $query->where('documentable_type', $data['value']);
                        }
                    }),
            ], layout: FiltersLayout::AboveContent)

            ->recordActions([
                ViewAction::make()
                    ->label('Ver')
                    ->url(fn ($record) => route('filament.admin.resources.documentos.view', ['record' => $record->id]))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
                
            ])
             ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
