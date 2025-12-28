<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use App\Filament\Resources\ServicioResource\Pages\ListServicios;
use App\Filament\Resources\ServicioResource\Pages\CreateServicio;
use App\Filament\Resources\ServicioResource\Pages\EditServicio;
use App\Enums\CicloFacturacionEnum;
use App\Enums\ServicioTipoEnum; // Importar Enum
use App\Filament\Resources\ServicioResource\Pages;
use App\Models\Servicio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn; // Importar IconColumn
use Filament\Tables\Columns\TextColumn;

use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter; // Importar SelectFilter
use Filament\Tables\Filters\TernaryFilter; // Importar TernaryFilter
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;



class ServicioResource extends Resource
{
    protected static ?string $model = Servicio::class;

    protected static string | \BackedEnum | null $navigationIcon = 'icon-servicios'; // O el icono que prefieras
    protected static string | \UnitEnum | null $navigationGroup = 'Gestión VENTAS'; // O donde quieras agruparlo
    protected static ?string $modelLabel = 'Servicio';
    protected static ?string $pluralModelLabel = 'Servicios que ofrecemos';
    protected static ?int $navigationSort = 1; // Orden en el menú

    public static function form(Schema $schema): Schema
{
    return $schema
        ->components([
            Section::make('Detalles del Servicio')
                ->schema([
                    Grid::make(5)->schema([
                        TextInput::make('nombre')
                            ->required()
                            ->maxLength(255),

                        Select::make('tipo')
                            ->required()
                            ->reactive()
                            ->options(ServicioTipoEnum::class),

                        TextInput::make('precio_base')
                            ->required()
                            ->numeric()
                            ->prefix('€')
                            ->inputMode('decimal')
                            ->step('0.01')
                            ->minValue(0),

                        Select::make('ciclo_facturacion')
                            ->label('Ciclo de facturación por defecto')
                            ->options(
                                collect(CicloFacturacionEnum::cases())
                                    ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                                    ->toArray()
                            )
                            ->default(CicloFacturacionEnum::MENSUAL->value)
                            ->nullable()
                            ->searchable()
                            ->visible(fn (Get $get) => $get('tipo') === ServicioTipoEnum::RECURRENTE->value)
                            ->required(fn (Get $get) => $get('tipo') === ServicioTipoEnum::RECURRENTE->value),
                         Select::make('departamento_id')
                            ->label('Departamento Responsable')
                            ->relationship('departamento', 'nombre')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->placeholder('Selecciona el departamento')
                            
                            // --- Formulario para crear un nuevo departamento ---
                            ->createOptionForm([
                                TextInput::make('nombre')
                                    ->label('Nombre del nuevo departamento')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                    
                                // --- Selector para asignar el coordinador ---
                                Select::make('coordinador_id')
                                    ->label('Asignar Coordinador')
                                    ->relationship(
                                        name: 'coordinador', 
                                        titleAttribute: 'name',
                                        // Mostramos solo usuarios con el rol 'coordinador'
                                        modifyQueryUsing: fn (Builder $query) => $query->whereHas(
                                            'roles', fn ($q) => $q->where('name', 'coordinador')
                                        )
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->nullable() // El coordinador puede ser opcional al crear el depto.
                                    ->placeholder('Puedes asignar un coordinador ahora o más tarde'),
                            ])
                            // --- Personalización de la ventana modal ---
                            ->createOptionAction(function ($action) {
                                $action
                                    ->modalHeading('Crear Nuevo Departamento')
                                    ->modalSubmitActionLabel('Crear departamento')
                                    ->modalWidth('xl'); // Hacemos la ventana un poco más ancha
                            }),
    
                    ]),

                    Grid::make(4)->schema([
                        Toggle::make('activo')
                            ->required()
                            ->default(true)
                            ->label('Servicio Activo'),

                        Toggle::make('es_tarifa_principal')
                            ->required()
                            ->default(true)
                            ->label('¿Es tarifa principal?')
                            ->helperText('Se usará como tarifa por defecto para este servicio.'),

                        Toggle::make('requiere_proyecto_activacion')
                            ->label('Requiere Proyecto de Activación')
                            ->helperText('Ej. alta de autónomo, capitalización, etc.'),

                        Toggle::make('es_editable')
                            ->label('¿Servicio editable por comercial?')
                            ->helperText('Permite modificar nombre, precio y requisitos en la venta.')
                            ->default(false),    
                    ]),

                  
                    Textarea::make('descripcion')
                        ->maxLength(65535)
                        ->columnSpanFull(),
                ]),
        ]);
}
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
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
                    ->badge() // Mostrar el tipo como badge
                    ->formatStateUsing(fn (ServicioTipoEnum $state): string => $state->getLabel()) // Usar etiqueta legible
                    ->color(fn (ServicioTipoEnum $state): string => match ($state) {
                        ServicioTipoEnum::UNICO => 'info',
                        ServicioTipoEnum::RECURRENTE => 'success',
                    }) // Colores para los badges
                    ->sortable(),
                    
                     TextColumn::make('ciclo_facturacion')
                    ->label('Ciclo de facturación')
                    ->formatStateUsing(fn (CicloFacturacionEnum $state): string => $state->label()) // Usar etiqueta del Enum
                    ->color(fn (CicloFacturacionEnum $state): string => match ($state) {
                        CicloFacturacionEnum::MENSUAL => 'primary',
                        CicloFacturacionEnum::TRIMESTRAL => 'secondary',
                        CicloFacturacionEnum::ANUAL => 'warning',
                    }) // Colores para los badges
                   
                    ->searchable()
                    ->sortable(),
                TextColumn::make('precio_base')
                    ->money('EUR') // Formatear como moneda
                    ->sortable(),
                IconColumn::make('activo') // Columna de icono para booleano
                    ->boolean()
                    ->sortable(),
                IconColumn::make('es_tarifa_principal') // Columna de icono para booleano
                ->label('¿Es tarifa principal?')
                    ->boolean()
                    ->sortable(),    
//requiere_proyecto_activacion
            IconColumn::make('requiere_proyecto_activacion') // Columna de icono para booleano
                ->label('Proyecto asiociado')
                    ->boolean()
                    ->sortable(), 
            IconColumn::make('es_editable') // Columna de icono para booleano
                ->label('Es editable')
                    ->boolean()
                    ->sortable(), 
                // Opcional: ToggleColumn para cambiar activo/inactivo desde la tabla
                // ToggleColumn::make('activo'),
                TextColumn::make('created_at')
                ->label('Servicio creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                    
                TextColumn::make('updated_at')
                    ->dateTime('d/m/Y H:i')
                    ->label('Actualizado servicio')
                    ->sortable(),
                   
            ])
             ->filters([
            // --- Filtro Condicional para TIPO y CICLO ---
            Filter::make('tipo_y_ciclo')
                    ->label('Tipo y Ciclo')
                ->schema([
                    Select::make('tipo')
                        ->label('Tipo de Servicio')
                        // CAMBIO: Construimos las opciones manualmente
                        ->options(
                            collect(ServicioTipoEnum::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])
                        )
                        ->live(),

                    Select::make('ciclo_facturacion')
                        ->label('Ciclo de Facturación')
                        // CAMBIO: Construimos las opciones manualmente
                        ->options(
                            collect(CicloFacturacionEnum::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                        )
                        ->visible(fn (Get $get): bool => $get('tipo') === ServicioTipoEnum::RECURRENTE->value),
                ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['tipo'], fn ($q, $tipo) => $q->where('tipo', $tipo))
                            ->when($data['ciclo_facturacion'], fn ($q, $ciclo) => $q->where('ciclo_facturacion', $ciclo));
                    }),
            // --- Filtro por Departamento ---
            SelectFilter::make('departamento')
                ->relationship('departamento', 'nombre'),
            
            // --- Filtros de tipo Sí/No/Todos ---
           Filter::make('activo')
                ->label('Solo Activos')
                ->query(fn (Builder $query): Builder => $query->where('activo', true))
                ->toggle(),

            Filter::make('es_tarifa_principal')
                ->label('Solo Tarifas Principales')
                ->query(fn (Builder $query): Builder => $query->where('es_tarifa_principal', true))
                ->toggle(),

            Filter::make('requiere_proyecto_activacion')
                ->label('Solo los que Requieren Proyecto')
                ->query(fn (Builder $query): Builder => $query->where('requiere_proyecto_activacion', true))
                ->toggle(),
            Filter::make('es_editable')
                ->label('Son editables')
                ->query(fn (Builder $query): Builder => $query->where('es_editable', true))
                ->toggle(),
    

        ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(7)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                // Opcional: Podrías añadir DeleteAction si se pueden borrar
                // Tables\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Opcional: Podrías añadir DeleteBulkAction
                    // Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            // No hay relaciones definidas aquí por ahora
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServicios::route('/'),
            'create' => CreateServicio::route('/create'),
          //  'view' => Pages\ViewServicio::route('/{record}'),
            'edit' => EditServicio::route('/{record}/edit'),
        ];
    }
}