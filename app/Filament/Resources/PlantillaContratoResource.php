<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\RichEditor;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\PlantillaContratoResource\Pages\ListPlantillaContratos;
use App\Filament\Resources\PlantillaContratoResource\Pages\CreatePlantillaContrato;
use App\Filament\Resources\PlantillaContratoResource\Pages\EditPlantillaContrato;
use App\Filament\Resources\PlantillaContratoResource\Pages;
use App\Models\PlantillaContrato;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PlantillaContratoResource extends Resource
{
    protected static ?string $model = PlantillaContrato::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';
    protected static string | \UnitEnum | null $navigationGroup = 'Configuración del Negocio';
    protected static ?string $modelLabel = 'Legal - Plantilla de Contrato';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación del Bloque')
                    ->columns(2)
                    ->schema([
                        TextInput::make('titulo')
                            ->label('Título descriptivo')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Anexo I - Recurrentes')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Set $set, $operation) {
                                // Autogenerar clave slugificada solo al crear
                                if ($operation === 'create' && filled($state)) {
                                    $set('clave', Str::slug($state, '_'));
                                }
                            }),

                        TextInput::make('clave')
                            ->label('Clave Interna (Slug)')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('Esta es la ID que usaremos en el código. Ej: "anexo_1", "rgpd", "cabecera".')
                            ->prefix('plantilla_'),
                            
                        Toggle::make('activo')
                            ->label('Activo para nuevos contratos')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),

                Section::make('Contenido Legal')
                    ->schema([
                        RichEditor::make('contenido')
                            ->label('Texto de la Cláusula')
                            ->required()
                            ->columnSpanFull()
                            // Desactivamos subida de archivos para mantenerlo limpio, 
                            // pero puedes activarlo si necesitas imágenes en los anexos.
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'bulletList',
                                'orderedList',
                                'h2',
                                'h3',
                                'link',
                                'redo',
                                'strike',
                                'undo',
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('clave')
                    ->badge()
                    ->color('info')
                    ->copyable(),
                IconColumn::make('activo')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->dateTime('d/m/Y H:i')
                    ->label('Última edición'),
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

    public static function getPages(): array
    {
        return [
            'index' => ListPlantillaContratos::route('/'),
            'create' => CreatePlantillaContrato::route('/create'),
            'edit' => EditPlantillaContrato::route('/{record}/edit'),
        ];
    }
}