<?php

namespace App\Filament\Resources\StripeSyncRuns\Tables;

use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables;
use Filament\Tables\Table;

class StripeSyncRunsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('user_id')
                    ->label('User id')
                    ->sortable(),

                Tables\Columns\IconColumn::make('solo_activas')
                    ->label('Solo activas')
                    ->boolean(),

                Tables\Columns\IconColumn::make('backfill_invoices')
                    ->label('Backfill invoices')
                    ->boolean(),

                Tables\Columns\TextColumn::make('chunk_size')
                    ->label('Chunk size')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'running'  => 'warning',
                        'finished' => 'success',
                        'failed'   => 'danger',
                        default    => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subs_updated')
                    ->label('Subs updated')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('subs_no_change')
                    ->label('Subs no change')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('skipped_invalid')
                    ->label('Skipped invalid')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('inv_created')
                    ->label('Inv created')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('inv_skipped')
                    ->label('Inv skipped')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('inv_errors')
                    ->label('Inv errors')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('errors')
                    ->label('Errors')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('started_at')
                    ->label('Started at')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('finished_at')
                    ->label('Finished at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}
