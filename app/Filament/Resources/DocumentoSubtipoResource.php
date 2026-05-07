<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\DocumentoSubtipoResource\Pages\ListDocumentoSubtipos;
use App\Filament\Resources\DocumentoSubtipoResource\Pages\CreateDocumentoSubtipo;
use App\Filament\Resources\DocumentoSubtipoResource\Pages\EditDocumentoSubtipo;
use App\Filament\Resources\DocumentoSubtipoResource\Pages;
use App\Filament\Resources\DocumentoSubtipoResource\RelationManagers;
use App\Models\DocumentoSubtipo;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DocumentoSubtipoResource extends Resource
{
    protected static ?string $model = DocumentoSubtipo::class;

    protected static string | \BackedEnum | null $navigationIcon = 'icon-subtipodocumento';

    protected static string | \UnitEnum | null $navigationGroup = 'Configuración plataforma';
    protected static ?string $navigationLabel = 'Subtipo documento';
    protected static ?string $modelLabel = 'Subtipo documento';
    protected static ?string $pluralModelLabel = 'Subtipos de documentos';






    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('documento_categoria_id')
                ->label('Categoría')
                ->relationship('categoria', 'nombre')
                ->required()
              
                ->preload()
                ->searchable(),

            TextInput::make('nombre')
                ->label('Nombre del subtipo')
                ->required()
                ->maxLength(150)
               
                ->placeholder('Ej: Modelo 303, Declaración trimestral, Nóminas...'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('categoria.nombre')
                    ->label('Categoría')
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->categoria?->color ?? 'gray'),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('documento_categoria_id')
                    ->label('Filtrar por categoría')
                    ->relationship('categoria', 'nombre')
                    ->searchable(),
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
            'index' => ListDocumentoSubtipos::route('/'),
            'create' => CreateDocumentoSubtipo::route('/create'),
            'edit' => EditDocumentoSubtipo::route('/{record}/edit'),
        ];
    }
}
