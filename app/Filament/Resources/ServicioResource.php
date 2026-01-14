<?php

namespace App\Filament\Resources;

use App\Enums\CicloFacturacionEnum;
use App\Enums\ServicioTipoEnum;
use App\Filament\Resources\ServicioResource\Pages\CreateServicio;
use App\Filament\Resources\ServicioResource\Pages\EditServicio;
use App\Filament\Resources\ServicioResource\Pages\ListServicios;
use App\Models\Servicio;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ServicioResource extends Resource
{
    protected static ?string $model = Servicio::class;

    protected static string | \BackedEnum | null $navigationIcon = 'icon-servicios';
    protected static string | \UnitEnum | null $navigationGroup = 'Gestión VENTAS';
    protected static ?string $modelLabel = 'Servicio';
    protected static ?string $pluralModelLabel = 'Servicios que ofrecemos';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // ===============================
            // DATOS PRINCIPALES
            // ===============================
            Section::make('Servicio')
                ->description('Configura el servicio que se puede vender en la conversión (y su comportamiento).')
                ->schema([
                    TextInput::make('nombre')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Select::make('tipo')
                        ->label('Tipo')
                        ->required()
                        ->options(
                            collect(ServicioTipoEnum::cases())
                                ->mapWithKeys(fn ($c) => [$c->value => $c->getLabel()])
                                ->toArray()
                        )
                        ->live()
                        ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                            $esRecurrente = $state === ServicioTipoEnum::RECURRENTE->value;

                            if ($esRecurrente) {
                                // Defaults / limpieza
                                if (blank($get('ciclo_facturacion'))) {
                                    $set('ciclo_facturacion', CicloFacturacionEnum::MENSUAL->value);
                                }

                                // Este flag solo tiene sentido en UNICO
                                $set('bloquea_recurrente', false);

                                // PLAN solo aplica en recurrentes (si quieres default true, déjalo)
                                if ($get('es_tarifa_principal') === null) {
                                    $set('es_tarifa_principal', true);
                                }
                            } else {
                                // UNICO: ciclo no aplica
                                $set('ciclo_facturacion', null);

                                // PLAN no aplica en únicos
                                $set('es_tarifa_principal', false);

                                // Por defecto, los únicos suelen requerir proyecto (según tu modelo actual)
                                if ($get('requiere_proyecto_activacion') === null) {
                                    $set('requiere_proyecto_activacion', true);
                                }
                            }
                        }),

                    TextInput::make('precio_base')
                        ->label('Precio base')
                        ->required()
                        ->numeric()
                        ->prefix('€')
                        ->inputMode('decimal')
                        ->step('0.01')
                        ->minValue(0),

                    Select::make('ciclo_facturacion')
                        ->label('Ciclo de facturación (solo recurrentes)')
                        ->options(
                            collect(CicloFacturacionEnum::cases())
                                ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
                                ->toArray()
                        )
                        ->default(CicloFacturacionEnum::MENSUAL->value)
                        ->searchable()
                        ->nullable()
                        ->visible(fn (Get $get) => $get('tipo') === ServicioTipoEnum::RECURRENTE->value)
                        ->required(fn (Get $get) => $get('tipo') === ServicioTipoEnum::RECURRENTE->value),

                    Select::make('departamento_id')
                        ->label('Departamento responsable')
                        ->relationship('departamento', 'nombre')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->placeholder('Selecciona el departamento')
                        ->createOptionForm([
                            TextInput::make('nombre')
                                ->label('Nombre del nuevo departamento')
                                ->required()
                                ->maxLength(255)
                                ->columnSpanFull(),

                            Select::make('coordinador_id')
                                ->label('Asignar coordinador')
                                ->relationship(
                                    name: 'coordinador',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn (Builder $query) => $query->whereHas(
                                        'roles',
                                        fn ($q) => $q->where('name', 'coordinador')
                                    )
                                )
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->placeholder('Puedes asignarlo ahora o más tarde'),
                        ])
                        ->createOptionAction(function ($action) {
                            $action
                                ->modalHeading('Crear nuevo departamento')
                                ->modalSubmitActionLabel('Crear departamento')
                                ->modalWidth('xl');
                        }),
                ])
                ->columns(3)
                ->columnSpanFull(),

            // ===============================
            // COMPORTAMIENTO / FLAGS
            // ===============================
            Section::make('Comportamiento del servicio')
                ->description('Estos flags se “copian” a la venta (VentaItem) y definen proyecto / bloqueo / etc.')
                ->schema([
                    Toggle::make('activo')
                        ->label('Activo (visible para vender)')
                        ->helperText('Si lo desactivas, no aparece en el formulario de conversión.')
                        ->default(true)
                        ->inline(false),

                    Toggle::make('es_editable')
                        ->label('Editable por comercial')
                        ->helperText('Permite modificar nombre/precio y ajustes del item en la venta.')
                        ->default(false)
                        ->inline(false),

                    Toggle::make('requiere_proyecto_activacion')
                        ->label('Crea proyecto / requiere ejecución')
                        ->helperText('Si está marcado, se crea proyecto interno para ejecutar/controlar el servicio.')
                        ->default(true)
                        ->inline(false),

                    Toggle::make('bloquea_recurrente')
                        ->label('Bloquea recurrentes (inicio diferido)')
                        ->helperText('Si este servicio está en la venta, la cuota mensual se difiere hasta activar.')
                        ->default(false)
                        ->inline(false)
                        ->visible(fn (Get $get) => $get('tipo') === ServicioTipoEnum::UNICO->value),

                    Toggle::make('es_tarifa_principal')
                        ->label('PLAN principal (vs ADDON)')
                        ->helperText('PLAN = tarifa base. Si no, se considera ADDON.')
                        ->default(true)
                        ->inline(false)
                        ->visible(fn (Get $get) => $get('tipo') === ServicioTipoEnum::RECURRENTE->value),
                ])
                ->columns(3)
                ->columnSpanFull(),

            // ===============================
            // DESCRIPCIÓN
            // ===============================
            Section::make('Descripción')
                ->schema([
                    Textarea::make('descripcion')
                        ->label('Descripción')
                        ->rows(4)
                        ->maxLength(65535)
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->columnSpanFull(),
        ]);
    }

public static function table(Table $table): Table
{
    return $table
        ->striped()
        ->paginated([10, 25, 50, 100, 'all'])
        ->defaultPaginationPageOption(25)
        ->extremePaginationLinks()
        ->columns([
            TextColumn::make('nombre')
                ->label('Nombre')
                ->searchable()
                ->sortable(),

            TextColumn::make('departamento.nombre')
                ->label('Departamento')
                ->badge()
                ->color('warning')
                ->placeholder('Sin asignar')
                ->searchable()
                ->sortable(),

            TextColumn::make('tipo')
                ->label('Tipo')
                ->badge()
                ->formatStateUsing(function ($state): string {
                    $enum = $state instanceof ServicioTipoEnum ? $state : ServicioTipoEnum::tryFrom((string) $state);
                    return $enum?->getLabel() ?? Str::of((string) $state)->upper()->toString();
                })
                ->color(function ($state): string {
                    $enum = $state instanceof ServicioTipoEnum ? $state : ServicioTipoEnum::tryFrom((string) $state);
                    return match ($enum) {
                        ServicioTipoEnum::UNICO => 'info',
                        ServicioTipoEnum::RECURRENTE => 'success',
                        default => 'gray',
                    };
                })
                ->sortable(),

            TextColumn::make('ciclo_facturacion')
                ->label('Ciclo')
                ->badge()
                ->formatStateUsing(function ($state): string {
                    $enum = $state instanceof CicloFacturacionEnum ? $state : CicloFacturacionEnum::tryFrom((string) $state);
                    return $enum?->label() ?? '—';
                })
                ->toggleable()
                ->sortable(),

            TextColumn::make('precio_base')
                ->label('Precio base')
                ->money('EUR')
                ->sortable(),

            IconColumn::make('activo')
                ->label('Activo')
                ->boolean()
                ->sortable()
                ->action(function (Servicio $record): void {
                    $record->update(['activo' => ! (bool) $record->activo]);
                }),

            IconColumn::make('es_editable')
                ->label('Editable')
                ->boolean()
                ->sortable()
                ->action(function (Servicio $record): void {
                    $record->update(['es_editable' => ! (bool) $record->es_editable]);
                }),

            IconColumn::make('requiere_proyecto_activacion')
                ->label('Proyecto')
                ->boolean()
                ->sortable()
                ->action(function (Servicio $record): void {
                    $record->update([
                        'requiere_proyecto_activacion' => ! (bool) $record->requiere_proyecto_activacion,
                    ]);
                }),

            // ✅❌ clicable (sin toggle switch)
            IconColumn::make('bloquea_recurrente')
                ->label('Bloquea')
                ->boolean()
                ->sortable()
                ->action(function (Servicio $record): void {
                    $nuevo = ! (bool) $record->bloquea_recurrente;

                    // Si quieres forzar coherencia: solo tiene sentido en UNICO
                    if ($record->tipo === ServicioTipoEnum::RECURRENTE->value) {
                        $nuevo = false;
                    }

                    $record->update(['bloquea_recurrente' => $nuevo]);
                }),

            IconColumn::make('es_tarifa_principal')
                ->label('Plan Principal')
                ->boolean()
                ->sortable()
                ->action(function (Servicio $record): void {
                    $record->update(['es_tarifa_principal' => ! (bool) $record->es_tarifa_principal]);
                }),

            TextColumn::make('created_at')
                ->label('Creado')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('updated_at')
                ->label('Actualizado')
                ->dateTime('d/m/Y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->filters([
            Filter::make('tipo_y_ciclo')
                ->label('Tipo y Ciclo')
                ->schema([
                    Select::make('tipo')
                        ->label('Tipo de Servicio')
                        ->options(
                            collect(ServicioTipoEnum::cases())
                                ->mapWithKeys(fn ($c) => [$c->value => $c->getLabel()])
                                ->toArray()
                        )
                        ->live(),

                    Select::make('ciclo_facturacion')
                        ->label('Ciclo de Facturación')
                        ->options(
                            collect(CicloFacturacionEnum::cases())
                                ->mapWithKeys(fn ($c) => [$c->value => $c->label()])
                                ->toArray()
                        )
                        ->visible(fn (Get $get): bool => $get('tipo') === ServicioTipoEnum::RECURRENTE->value),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when($data['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo', $tipo))
                        ->when($data['ciclo_facturacion'] ?? null, fn ($q, $ciclo) => $q->where('ciclo_facturacion', $ciclo));
                }),

            SelectFilter::make('departamento')
                ->label('Departamento')
                ->relationship('departamento', 'nombre'),

            Filter::make('activo')
                ->label('Solo activos')
                ->query(fn (Builder $query) => $query->where('activo', true))
                ->toggle(),

            Filter::make('es_editable')
                ->label('Solo editables')
                ->query(fn (Builder $query) => $query->where('es_editable', true))
                ->toggle(),

            Filter::make('requiere_proyecto_activacion')
                ->label('Solo con proyecto')
                ->query(fn (Builder $query) => $query->where('requiere_proyecto_activacion', true))
                ->toggle(),

            Filter::make('bloquea_recurrente')
                ->label('Solo bloqueantes')
                ->query(fn (Builder $query) => $query->where('bloquea_recurrente', true))
                ->toggle(),

            Filter::make('es_tarifa_principal')
                ->label('Solo PLAN principal')
                ->query(fn (Builder $query) => $query->where('es_tarifa_principal', true))
                ->toggle(),
        ], layout: FiltersLayout::AboveContent)
        ->filtersFormColumns(6)
        ->recordActions([
            EditAction::make(),
            ViewAction::make(),
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
            'index'  => ListServicios::route('/'),
            'create' => CreateServicio::route('/create'),
            'edit'   => EditServicio::route('/{record}/edit'),
        ];
    }
}
