<?php

namespace App\Filament\Portal\Resources\Facturas;

use App\Filament\Portal\Resources\Facturas\Pages\CreateFactura;
use App\Filament\Portal\Resources\Facturas\Pages\EditFactura;
use App\Filament\Portal\Resources\Facturas\Pages\ListFacturas;
use App\Filament\Portal\Resources\Facturas\Pages\ViewFactura;
use App\Filament\Portal\Resources\Facturas\Schemas\FacturaForm;
use App\Filament\Portal\Resources\Facturas\Schemas\FacturaInfolist;
use App\Filament\Portal\Resources\Facturas\Tables\FacturasTable;
use App\Models\Factura;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;


class FacturaResource extends Resource
{
    protected static ?string $model = Factura::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-currency-euro';



    protected static string|\UnitEnum|null $navigationGroup = 'Facturación y pagos';
    protected static ?string $recordTitleAttribute = 'numero_factura';

    // ✅ Igual que Documentos (portal sin shield/policies)
    protected static bool $shouldSkipAuthorization = true;

    // ✅ Igual que Documentos: usuario portal -> clientes via pivote cliente_user
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        $clienteIds = $user?->clientes()->pluck('clientes.id')->all() ?? [];

        return parent::getEloquentQuery()
            ->whereIn('cliente_id', $clienteIds);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    // (Opcional) UX igual que Documentos
    protected static ?string $navigationLabel = 'Mis Facturas AsesorFy';
    protected static ?string $modelLabel = 'Factura AsesorFy';
    protected static ?string $pluralModelLabel = 'Mis Facturas AsesorFy';

    public static function form(Schema $schema): Schema
    {
        return FacturaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FacturaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FacturasTable::configure($table);
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
            'index' => ListFacturas::route('/'),  
        ];
    }
}
