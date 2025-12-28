<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\DocumentoCategoriaResource\Pages\ListDocumentoCategorias;
use App\Filament\Resources\DocumentoCategoriaResource\Pages\CreateDocumentoCategoria;
use App\Filament\Resources\DocumentoCategoriaResource\Pages\EditDocumentoCategoria;
use App\Filament\Resources\DocumentoCategoriaResource\Pages;
use App\Filament\Resources\DocumentoCategoriaResource\RelationManagers;
use App\Models\DocumentoCategoria;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;





class DocumentoCategoriaResource extends Resource implements HasShieldPermissions 
{
    protected static ?string $model = DocumentoCategoria::class;

    protected static string | \BackedEnum | null $navigationIcon = 'icon-tipodocumento';

    protected static string | \UnitEnum | null $navigationGroup = 'Configuración plataforma';
    protected static ?string $navigationLabel = 'Tipo general documento';
    protected static ?string $modelLabel = 'Tipo general documento';
    protected static ?string $pluralModelLabel = 'Tipos general de documentos';

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
        ];
    }



    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                ->label('Nombre de la categoría')
                ->required()
                ->maxLength(100)
                ->placeholder('Ej: Fiscal, Contable, General')
                ->helperText('Define una categoría general para agrupar tipos de documentos.'),
                Select::make('color')
                ->label('Color del badge')
                ->options([
                    'primary' => 'Azul (Primary)',
                    'success' => 'Verde (Success)',
                    'warning' => 'Amarillo (Warning)',
                    'danger' => 'Rojo (Danger)',
                    'info' => 'Celeste (Info)',
                    'gray' => 'Gris (Gray)',
                ])
                ->default('gray')
                ->required()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
        ->columns([
            TextColumn::make('nombre')
                ->label('Nombre')
                ->badge()
                ->color(fn ($record) => $record->color ?? 'gray')
                ->searchable()
                ->sortable(),
            TextColumn::make('created_at')
                ->label('Creado')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
        ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => ListDocumentoCategorias::route('/'),
            'create' => CreateDocumentoCategoria::route('/create'),
            'edit' => EditDocumentoCategoria::route('/{record}/edit'),
        ];
    }
}
