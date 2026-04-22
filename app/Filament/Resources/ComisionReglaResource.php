<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ComisionReglaResource\Pages;
use App\Models\ComisionRegla;
use App\Enums\ServicioTipoEnum;
use App\Models\Servicio;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ComisionReglaResource extends Resource
{
    protected static ?string $model = ComisionRegla::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';
    protected static string|\UnitEnum|null $navigationGroup  = 'Comisiones';
    protected static ?string $navigationLabel  = 'Reglas de Comisión';
    protected static ?string $modelLabel       = 'Regla de Comisión';
    protected static ?string $pluralModelLabel = 'Reglas de Comisión';
    protected static ?int $navigationSort      = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Información General')
                    ->schema([
                        Forms\Components\TextInput::make('nombre')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ej: Recurrentes Fiscal 2026')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('tipo_servicio')
                            ->label('Tipo de Servicio')
                            ->options([
                                'recurrente' => 'Recurrente',
                                'unico'      => 'Único',
                            ])
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('servicios_ids')
                            ->label('Servicios Aplicables')
                            ->multiple()
                            ->options(fn (callable $get) => filled($get('tipo_servicio'))
                                ? Servicio::where('tipo', ServicioTipoEnum::from($get('tipo_servicio')))->pluck('nombre', 'id')->toArray()
                                : []
                            )
                            ->helperText('Dejar vacío para aplicar a TODOS los servicios del tipo seleccionado'),
                    ])
                    ->columns(2),

                Section::make('Configuración de Comisión')
                    ->schema([
                        Forms\Components\TextInput::make('minimo_mensual')
                            ->label('Mínimo Mensual (€)')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->prefix('€')
                            ->helperText('Facturación mínima requerida antes de comisionar'),

                        Forms\Components\TextInput::make('porcentaje_comision')
                            ->label('Porcentaje de Comisión (%)')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->helperText('Se aplica sobre la facturación que supere el mínimo'),

                        Forms\Components\TextInput::make('penalizacion_baja_antes_meses')
                            ->label('Penalización por Baja (meses)')
                            ->numeric()
                            ->required()
                            ->default(3)
                            ->helperText('Si el cliente se da de baja antes de X meses, se penaliza'),
                    ])
                    ->columns(3),

                Section::make('Estado')
                    ->schema([
                        Forms\Components\Toggle::make('activa')
                            ->label('Activa')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipo_servicio')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'recurrente' => 'primary',
                        'unico'      => 'warning',
                        default      => 'gray',
                    }),

                Tables\Columns\TextColumn::make('minimo_mensual')
                    ->label('Mínimo (€)')
                    ->money('EUR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('porcentaje_comision')
                    ->label('Porcentaje')
                    ->formatStateUsing(fn ($state) => $state . '%')
                    ->sortable(),

                Tables\Columns\IconColumn::make('activa')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo_servicio')
                    ->label('Tipo')
                    ->options([
                        'recurrente' => 'Recurrente',
                        'unico'      => 'Único',
                    ]),

                Tables\Filters\TernaryFilter::make('activa')
                    ->label('Activa'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListComisionReglas::route('/'),
            'create' => Pages\CreateComisionRegla::route('/create'),
            'edit'   => Pages\EditComisionRegla::route('/{record}/edit'),
        ];
    }
}
