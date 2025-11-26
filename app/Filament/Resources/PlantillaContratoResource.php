<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlantillaContratoResource\Pages;
use App\Models\PlantillaContrato;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PlantillaContratoResource extends Resource
{
    protected static ?string $model = PlantillaContrato::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Configuración del Negocio';
    protected static ?string $modelLabel = 'Legal - Plantilla de Contrato';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificación del Bloque')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('titulo')
                            ->label('Título descriptivo')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Anexo I - Recurrentes')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, Forms\Set $set, $operation) {
                                // Autogenerar clave slugificada solo al crear
                                if ($operation === 'create' && filled($state)) {
                                    $set('clave', Str::slug($state, '_'));
                                }
                            }),

                        Forms\Components\TextInput::make('clave')
                            ->label('Clave Interna (Slug)')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('Esta es la ID que usaremos en el código. Ej: "anexo_1", "rgpd", "cabecera".')
                            ->prefix('plantilla_'),
                            
                        Forms\Components\Toggle::make('activo')
                            ->label('Activo para nuevos contratos')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Contenido Legal')
                    ->schema([
                        Forms\Components\RichEditor::make('contenido')
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
                Tables\Columns\TextColumn::make('titulo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('clave')
                    ->badge()
                    ->color('info')
                    ->copyable(),
                Tables\Columns\IconColumn::make('activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d/m/Y H:i')
                    ->label('Última edición'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlantillaContratos::route('/'),
            'create' => Pages\CreatePlantillaContrato::route('/create'),
            'edit' => Pages\EditPlantillaContrato::route('/{record}/edit'),
        ];
    }
}