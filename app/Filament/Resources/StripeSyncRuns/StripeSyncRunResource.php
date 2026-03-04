<?php

namespace App\Filament\Resources\StripeSyncRuns;

use App\Filament\Resources\StripeSyncRuns\Pages\ListStripeSyncRuns;
use App\Filament\Resources\StripeSyncRuns\Pages\ViewStripeSyncRun;
use App\Filament\Resources\StripeSyncRuns\Schemas\StripeSyncRunForm;
use App\Filament\Resources\StripeSyncRuns\Schemas\StripeSyncRunInfolist;
use App\Filament\Resources\StripeSyncRuns\Tables\StripeSyncRunsTable;
use App\Models\StripeSyncRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class StripeSyncRunResource extends Resource
{
    protected static ?string $model = StripeSyncRun::class;

    protected static string|BackedEnum|null $navigationIcon = 'icon-stripe2';
    protected static string | \UnitEnum | null $navigationGroup = 'Gestión Pagos y Facturas';

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return StripeSyncRunForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StripeSyncRunInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StripeSyncRunsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStripeSyncRuns::route('/'),
          //  'create' => CreateStripeSyncRun::route('/create'),
            'view' => ViewStripeSyncRun::route('/{record}'),
           // 'edit' => EditStripeSyncRun::route('/{record}/edit'),
        ];
    }
}
