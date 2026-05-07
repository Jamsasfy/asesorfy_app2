<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ProcedenciaResource\Pages\ListProcedencias;
use App\Filament\Resources\ProcedenciaResource\Pages\CreateProcedencia;
use App\Filament\Resources\ProcedenciaResource\Pages\EditProcedencia;
use App\Filament\Resources\ProcedenciaResource\Pages;
use App\Filament\Resources\ProcedenciaResource\RelationManagers;
use App\Models\Procedencia;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProcedenciaResource extends Resource
{
    protected static ?string $model = Procedencia::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static string | \UnitEnum | null $navigationGroup = 'Gestión LEADS';
    protected static ?string $navigationLabel = 'Procedencia Leads';
    protected static ?string $modelLabel = 'Procedencia';
    protected static ?string $pluralModelLabel = 'Procedencia de los Leads';




    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Detalles de la Procedencia')
                    ->columns(2) // Usar 2 columnas para mejor distribución
                    ->schema([
                        TextInput::make('procedencia') // Tu campo original
                            ->label('Nombre de la Procedencia') // Etiqueta más descriptiva
                           // ->autocapitalize('words')
                            ->required()
                            ->maxLength(255) // Límite razonable
                            ->unique(table: Procedencia::class, column: 'procedencia', ignoreRecord: true) // Validación de unicidad
                            ->columnSpan(1), // Ocupa 1 de 2 columnas

                        TextInput::make('key')
                            ->label('Clave Fija (Identificador)')
                            ->helperText('Identificador interno único (ej: solicitud_interna, web). No cambiar una vez creado.')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->unique(table: Procedencia::class, column: 'key', ignoreRecord: true) // Único, ignorando el registro actual al editar
                            ->disabledOn('edit') // No permitir editar la clave una vez creada
                            ->nullable() // Permitir nulo en DB (para registros antiguos o no clave)
                            ->maxLength(255)
                            ->columnSpan(1), // Ocupa 1 de 2 columnas

                        Textarea::make('descripcion')
                            ->label('Descripción (Opcional)')
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(), // Ocupa las 2 columnas

                        Toggle::make('activo')
                            ->label('Procedencia Activa')
                            ->required()
                            ->default(true) // Por defecto está activo
                            ->columnSpanFull(), // Ocupa las 2 columnas
                    ])
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('procedencia')
                    ->searchable(),
                    TextColumn::make('created_at')
                    ->label('Fecha creación')
                        ->dateTime('d/m/y - H:m')
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: false),
                    TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
            'index' => ListProcedencias::route('/'),
            'create' => CreateProcedencia::route('/create'),
            'edit' => EditProcedencia::route('/{record}/edit'),
        ];
    }
}
