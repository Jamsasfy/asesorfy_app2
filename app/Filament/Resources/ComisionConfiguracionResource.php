<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComisionConfiguracionResource\Pages;
use App\Models\ConfiguracionComisiones;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ComisionConfiguracionResource extends Resource
{
    protected static ?string $model = ConfiguracionComisiones::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string|\UnitEnum|null $navigationGroup  = 'Comisiones';
    protected static ?string $navigationLabel  = 'Configuración';
    protected static ?string $modelLabel       = 'Configuración de Comisiones';
    protected static ?string $pluralModelLabel = 'Configuración de Comisiones';
    protected static ?int $navigationSort      = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Alertas de Despido')
                    ->schema([
                        Forms\Components\TextInput::make('meses_consecutivos_despido')
                            ->label('Meses Consecutivos sin Mínimo para Aviso')
                            ->numeric()
                            ->required()
                            ->default(3)
                            ->minValue(1),

                        Forms\Components\TextInput::make('meses_alternos_despido')
                            ->label('Meses Alternos sin Mínimo para Aviso')
                            ->numeric()
                            ->required()
                            ->default(5)
                            ->minValue(1),

                        Forms\Components\TextInput::make('periodo_meses_alternos')
                            ->label('Período para contar meses alternos')
                            ->numeric()
                            ->required()
                            ->default(12)
                            ->minValue(1)
                            ->suffix('meses'),
                    ])
                    ->columns(3),

                Section::make('Emails de Notificación')
                    ->schema([
                        Forms\Components\TagsInput::make('emails_notificacion_despido')
                            ->label('Emails de RRHH/Coordinación')
                            ->helperText('Emails que recibirán la alerta cuando un comercial cumpla condiciones de despido')
                            ->placeholder('Añadir email...')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('meses_consecutivos_despido')
                    ->label('Consecutivos para aviso'),

                Tables\Columns\TextColumn::make('meses_alternos_despido')
                    ->label('Alternos para aviso'),

                Tables\Columns\TextColumn::make('periodo_meses_alternos')
                    ->label('Período (meses)'),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListComisionConfiguraciones::route('/'),
            'edit'  => Pages\EditComisionConfiguracion::route('/{record}/edit'),
        ];
    }
}
