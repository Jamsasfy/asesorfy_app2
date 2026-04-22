<?php

namespace App\Filament\Resources;

use AmidEsfahani\FilamentTinyEditor\TinyEditor;
use App\Filament\Resources\PlantillaEmailComisionResource\Pages;
use App\Models\PlantillaEmailComision;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PlantillaEmailComisionResource extends Resource
{
    protected static ?string $model = PlantillaEmailComision::class;

    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-envelope';
    protected static string|\UnitEnum|null   $navigationGroup = 'Comisiones';
    protected static ?string $navigationLabel  = 'Plantillas Email';
    protected static ?string $modelLabel       = 'Plantilla Email';
    protected static ?string $pluralModelLabel = 'Plantillas Email';
    protected static ?int    $navigationSort   = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Información')
                            ->schema([
                                Forms\Components\TextInput::make('nombre')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('codigo')
                                    ->required()
                                    ->disabled(fn ($record) => $record !== null)
                                    ->helperText('No modificable: comision_supera o comision_no_supera'),

                                Forms\Components\Toggle::make('activa')
                                    ->default(true)
                                    ->inline(false),
                            ]),

                        Section::make('Variables Disponibles')
                            ->schema([
                                Forms\Components\Placeholder::make('variables')
                                    ->label('')
                                    ->content(fn ($record) => $record
                                        ? implode(', ', array_map(fn ($v) => '{' . $v . '}', $record->variables_disponibles ?? []))
                                        : 'Guarda el registro para ver las variables disponibles.'),
                            ]),
                    ]),

                Section::make('Email')
                    ->columnSpanFull()
                    ->schema([
                        Forms\Components\TextInput::make('asunto')
                            ->required()
                            ->columnSpanFull()
                            ->helperText('Variables: {comercial_nombre}, {comercial_apellidos}, {periodo}, {total_comisiones}, {total_bonos}, {total_final}'),

                        TinyEditor::make('contenido_html')
                            ->label('Contenido HTML')
                            ->required()
                            ->columnSpanFull()
                            ->profile('full')
                            ->showMenuBar()
                            ->minHeight(600),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->searchable(),

                Tables\Columns\TextColumn::make('codigo')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\ToggleColumn::make('activa'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Modificado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlantillaEmailComisions::route('/'),
            'edit'  => Pages\EditPlantillaEmailComision::route('/{record}/edit'),
        ];
    }
}
