<?php

namespace App\Filament\Resources\StripeSyncRuns\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StripeSyncRunForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('user_id')
                    ->numeric(),
                Toggle::make('solo_activas')
                    ->required(),
                Toggle::make('backfill_invoices')
                    ->required(),
                TextInput::make('chunk_size')
                    ->required()
                    ->numeric()
                    ->default(50),
                TextInput::make('status')
                    ->required()
                    ->default('queued'),
                TextInput::make('total')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('subs_updated')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('subs_no_change')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('skipped_invalid')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('inv_created')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('inv_skipped')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('inv_errors')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('errors')
                    ->required()
                    ->numeric()
                    ->default(0),
                Textarea::make('error_message')
                    ->columnSpanFull(),
                DateTimePicker::make('started_at'),
                DateTimePicker::make('finished_at'),
            ]);
    }
}
