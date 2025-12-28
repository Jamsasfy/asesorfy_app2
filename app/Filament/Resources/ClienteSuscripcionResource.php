<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\Filter;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Schemas\Components\Section;
use App\Filament\Resources\ClienteSuscripcionResource\Pages\ListClienteSuscripcions;
use App\Filament\Resources\ClienteSuscripcionResource\Pages\CreateClienteSuscripcion;
use App\Filament\Resources\ClienteSuscripcionResource\Pages\ViewClienteSuscripcion;
use App\Filament\Resources\ClienteSuscripcionResource\Pages\EditClienteSuscripcion;
use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\ServicioTipoEnum;
use App\Filament\Resources\ClienteSuscripcionResource\Pages;
use App\Models\ClienteSuscripcion;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables\Columns\ViewColumn;

class ClienteSuscripcionResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = ClienteSuscripcion::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

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
        return $schema->components([
            Select::make('cliente_id')
                ->relationship('cliente', 'razon_social')
                ->searchable()
                ->preload()
                ->required(),

            Select::make('servicio_id')
                ->relationship('servicio', 'nombre')
                ->preload()
                ->searchable()
                ->required(),

            Select::make('estado')
                ->options(collect(ClienteSuscripcionEstadoEnum::cases())
                    ->mapWithKeys(fn ($estado) => [$estado->value => $estado->name]))
                ->required(),

            DatePicker::make('fecha_inicio')->required(),
            DatePicker::make('fecha_fin'),

            TextInput::make('precio_acordado')->numeric()->prefix('€')->required(),
            TextInput::make('cantidad')->numeric()->default(1),
            TextInput::make('ciclo_facturacion'),

            DatePicker::make('proxima_fecha_facturacion'),

            TextInput::make('descuento_tipo'),
            TextInput::make('descuento_valor')->numeric(),
            TextInput::make('descuento_descripcion'),
            DatePicker::make('descuento_valido_hasta'),

            TextInput::make('stripe_subscription_id'),
            Textarea::make('observaciones'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
         ->defaultSort('created_at', 'desc') // Ordenar por defecto
        ->columns([
            TextColumn::make('cliente.razon_social')->searchable(),
             TextColumn::make('nombre_final') // Usamos el accesor del modelo
        ->label('Servicio Contratado')
        ->searchable(query: function (Builder $query, string $search): Builder {
            // Hacemos que la búsqueda funcione en ambos campos
            return $query
                ->where('nombre_personalizado', 'like', "%{$search}%")
                ->orWhereHas('servicio', fn ($q) => $q->where('nombre', 'like', "%{$search}%"));
        }),
            TextColumn::make('estado')
                ->badge()
                ->color(fn (ClienteSuscripcionEstadoEnum $state): string => match ($state) {
                    ClienteSuscripcionEstadoEnum::ACTIVA => 'success',
                    ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION => 'warning',
                    ClienteSuscripcionEstadoEnum::EN_PRUEBA => 'info',
                    ClienteSuscripcionEstadoEnum::IMPAGADA => 'danger',
                    ClienteSuscripcionEstadoEnum::PENDIENTE_CANCELACION => 'warning',
                    ClienteSuscripcionEstadoEnum::CANCELADA => 'gray',
                    ClienteSuscripcionEstadoEnum::FINALIZADA => 'gray',
                    ClienteSuscripcionEstadoEnum::REEMPLAZADA => 'gray',
                    ClienteSuscripcionEstadoEnum::PAUSADA => 'secondary',
                    default => 'gray',
                })
    ->formatStateUsing(fn(ClienteSuscripcionEstadoEnum $state) => $state->getLabel()),
            TextColumn::make('fecha_inicio')->date('d/m/Y'),
            TextColumn::make('fecha_fin')->date('d/m/Y'),
            TextColumn::make('precio_acordado')->money('EUR'),
    // ▼▼▼ REEMPLAZA LA COLUMNA DEL DESCUENTO POR ESTA ▼▼▼
ViewColumn::make('descuento')
    ->label('Dto.')
    ->view('filament.tables.columns.discount-icon-tooltip') // <-- Carga nuestro archivo Blade
   ->tooltip(function ($record): ?string {
    if (!$record->descuento_tipo) {
        return null;
    }
    
    $descuentoVigente = $record->descuento_valido_hasta && now()->lte($record->descuento_valido_hasta);
    $prefix = $descuentoVigente ? '[EN CURSO]' : '[APLICADO FINALIZADO]';
    
    $parts = [$prefix];

    // ▼▼▼ LÓGICA DE TIEMPO RESTANTE ACTUALIZADA ▼▼▼
    if ($descuentoVigente) {
        // Obtenemos la diferencia de meses. Usamos ceil() para redondear hacia arriba.
        // Ej: si quedan 1.2 meses, lo contará como 2.
        $mesesRestantes = ceil(now()->floatDiffInMonths($record->descuento_valido_hasta));
        
        // Lo convertimos a entero para asegurar
        $mesesRestantesEntero = (int) $mesesRestantes;
        
        if ($mesesRestantesEntero > 1) {
            $parts[] = "Quedan: {$mesesRestantesEntero} meses";
        } elseif ($mesesRestantesEntero === 1) {
            $parts[] = "Queda: Este es el último mes";
        }
    }
    
    $parts[] = '---';
    $parts[] = 'Tipo: ' . $record->descuento_tipo;
    $valor = number_format($record->descuento_valor, 2, ',', '.');
    $parts[] = 'Valor: ' . ($record->descuento_tipo === 'porcentaje' ? "{$valor}%" : "{$valor} €");
    
    if ($record->descuento_duracion_meses) {
        $parts[] = 'Duración Total: ' . $record->descuento_duracion_meses . ' meses';
    }
    
    if ($record->descuento_valido_hasta) {
        $parts[] = 'Finaliza el: ' . $record->descuento_valido_hasta->format('d/m/Y');
    }
    if ($record->descuento_descripcion) {
        $parts[] = 'Descripción: ' . $record->descuento_descripcion;
    }
    
    return implode("\n", $parts);
}),

            TextColumn::make('ciclo_facturacion'),
             TextColumn::make('proxima_fecha_facturacion')->date('d/m/Y'),
            TextColumn::make('created_at')
                ->dateTime('d/m/Y H:i')
                ->label('Creado'),
            TextColumn::make('updated_at')
                ->dateTime('d/m/Y H:i')
                ->label('Creado'),
        ])
        ->filters([
                // Filtro por estado usando el Enum directamente
                SelectFilter::make('estado')
                    ->options(ClienteSuscripcionEstadoEnum::class), // Filament v3 lo convierte a opciones automáticamente

                // Filtro para buscar por cliente
                SelectFilter::make('cliente_id')
                    ->label('Cliente')
                    ->relationship('cliente', 'razon_social')
                    ->searchable()
                    ->preload(),

                // Filtro para buscar por servicio
                SelectFilter::make('servicio_id')
                    ->label('Servicio')
                    ->relationship('servicio', 'nombre')
                    ->searchable()
                    ->preload(),
                
                // Filtro para saber si es tarifa principal
                TernaryFilter::make('es_tarifa_principal')
                    ->label('Es Tarifa Principal'),

            // ▼▼▼ EL NUEVO FILTRO PARA FACTURACIÓN ▼▼▼
                Filter::make('listos_para_facturar')
                    ->label('Listos para Facturar (Recurrentes Activos)')
                    ->query(function (Builder $query): Builder {
                        return $query
                            // 1. Solo estado ACTIVA
                            ->where('estado', ClienteSuscripcionEstadoEnum::ACTIVA)
                            // 2. Solo servicios de tipo RECURRENTE
                            ->whereHas('servicio', function (Builder $q) {
                                $q->where('tipo', ServicioTipoEnum::RECURRENTE);
                            })
                            // 3. Que ya hayan empezado
                            ->where('fecha_inicio', '<=', now())
                            // 4. Y que no hayan finalizado
                            ->where(function (Builder $q) {
                                $q->whereNull('fecha_fin')
                                  ->orWhere('fecha_fin', '>=', now());
                            });
                    })
                    ->toggle(), // Es un simple interruptor de Sí/No
           
                Filter::make('filtros_combinados')
                ->label('Filtros Avanzados')
                ->schema([
                    Grid::make(4) // <-- Cambiamos la rejilla a 4 columnas
                        ->schema([
                            Select::make('year')
                                ->label('Año')
                                ->options(fn () => ClienteSuscripcion::query()->selectRaw('YEAR(fecha_inicio) as year')->whereNotNull('fecha_inicio')->distinct()->orderBy('year', 'desc')->pluck('year', 'year')->toArray()),
                            
                            Select::make('month')
                                ->label('Mes')
                                ->options([
                                    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                                    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                                    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
                                ]),
                            
                            Select::make('estado')
                                ->label('Estado')
                                ->options(ClienteSuscripcionEstadoEnum::class),
                            
                            // ▼▼▼ EL NUEVO FILTRO DE TIPO DE SERVICIO ▼▼▼
                            Select::make('tipo_servicio')
                                ->label('Tipo de Servicio')
                                ->options(ServicioTipoEnum::class),
                        ])
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            $data['year'],
                            fn (Builder $query, $year): Builder => $query->whereYear('fecha_inicio', $year)
                        )
                        ->when(
                            $data['month'],
                            fn (Builder $query, $month): Builder => $query->whereMonth('fecha_inicio', $month)
                        )
                        ->when(
                            $data['estado'],
                            fn (Builder $query, $estado): Builder => $query->where('estado', $estado)
                        )
                        // ▼▼▼ Lógica para el nuevo filtro ▼▼▼
                        ->when(
                            $data['tipo_servicio'],
                            fn (Builder $query, $tipo): Builder => $query->whereHas('servicio', function (Builder $q) use ($tipo) {
                                $q->where('tipo', $tipo);
                            })
                        );
                })
                ->columnSpan(2),
   
            ], layout: FiltersLayout::AboveContent) // <-- Coloca los filtros arriba de la tabla
        ->recordActions([
            ViewAction::make(),
            EditAction::make(),
        ])
        ->toolbarActions([
            DeleteBulkAction::make(),
        ]);
    }

   public static function infolist(Schema $schema): Schema
{
    return $schema
        ->components([
            Grid::make(3)->schema([
                // --- COLUMNA IZQUIERDA (PRINCIPAL) ---
                Section::make('Detalles de la Suscripción')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                      TextEntry::make('nombre_final') // <-- Usamos el accesor aquí
                        ->label('Servicio Contratado')
                        ->weight('bold')
                        ->size('lg')
                        ->columnSpanFull(),

                        TextEntry::make('estado')
                            ->label('Estado Actual')
                            ->badge()
                            ->formatStateUsing(fn (ClienteSuscripcionEstadoEnum $state) => $state->getLabel())
                            ->color(fn (ClienteSuscripcionEstadoEnum $state): string => match ($state) {
                                ClienteSuscripcionEstadoEnum::ACTIVA => 'success',
                                ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION => 'warning',
                                ClienteSuscripcionEstadoEnum::CANCELADA, ClienteSuscripcionEstadoEnum::FINALIZADA => 'danger',
                                default => 'gray',
                            }),
                        
                        TextEntry::make('servicio.tipo')
                            ->label('Tipo de Servicio')
                            ->badge()
                            ->color(fn ($state) => $state === ServicioTipoEnum::RECURRENTE ? 'info' : 'success'),
                        
                        TextEntry::make('precio_acordado')
                            ->label('Precio')
                            ->money('eur')
                            ->helperText(function (ClienteSuscripcion $record): ?string {
                                if ($record->servicio->tipo === ServicioTipoEnum::RECURRENTE) {
                                    return 'Precio por ciclo de facturación';
                                }
                                return null;
                            }),

                            TextEntry::make('info_descuento') // Nombre virtual
        ->label('Descuento Aplicado')
        ->icon(fn (ClienteSuscripcion $record): ?string =>
            $record->descuento_tipo ? 'heroicon-o-exclamation-circle' : 'heroicon-o-check-circle'
        )
        ->color(fn (ClienteSuscripcion $record): string =>
            $record->descuento_tipo ? 'warning' : 'success'
        )
        ->state(fn (ClienteSuscripcion $record): string =>
            $record->descuento_tipo ? 'Sí' : 'No'
        )
        ->tooltip(function (ClienteSuscripcion $record): ?string {
            if (!$record->descuento_tipo) {
                return null;
            }
            
            $parts = [];
            $parts[] = 'Tipo: ' . $record->descuento_tipo;

            $valor = number_format($record->descuento_valor, 2, ',', '.');
            $parts[] = 'Valor: ' . ($record->descuento_tipo === 'porcentaje' ? "{$valor}%" : "{$valor} €");
            
            if ($record->descuento_valido_hasta) {
                $parts[] = 'Válido hasta: ' . $record->descuento_valido_hasta->format('d/m/Y');
            }
            if ($record->descuento_descripcion) {
                $parts[] = 'Descripción: ' . $record->descuento_descripcion;
            }
            
            return implode("\n", $parts);
        }),
           



                        TextEntry::make('ciclo_facturacion')
                            ->label('Periodicidad')
                            ->badge()
                            ->color('gray')
                            ->visible(fn ($record) => $record->servicio->tipo === ServicioTipoEnum::RECURRENTE),

                        TextEntry::make('fecha_inicio')
                            ->label('Fecha de Inicio')
                            ->date('d/m/Y'),

                        TextEntry::make('fecha_fin')
                            ->label('Fecha de Fin')
                            ->date('d/m/Y')
                            ->placeholder('Indefinido'),
                    ]),

                // --- COLUMNA DERECHA (ASIDE) ---
                Section::make('Contexto')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('cliente.razon_social')
                            ->label('Cliente')
                            ->url(fn (ClienteSuscripcion $record) => ClienteResource::getUrl('view', ['record' => $record->cliente_id]))
                            ->openUrlInNewTab()
                            ->icon('heroicon-m-user-circle'),
                        
                        TextEntry::make('ventaOrigen.id')
                            ->label('Venta de Origen')
                            ->url(fn (ClienteSuscripcion $record) => VentaResource::getUrl('view', ['record' => $record->venta_origen_id]))
                            ->openUrlInNewTab()
                            ->icon('heroicon-m-shopping-cart')
                            ->formatStateUsing(fn ($state) => "Venta #{$state}"),
                    ]),
            ]),

            // --- SECCIÓN DE DESCUENTOS (SOLO SI EXISTE) ---
            Section::make('Condiciones del Descuento Aplicado')
                ->visible(fn ($record) => $record->descuento_tipo)
                ->columns(3)
                ->schema([
                    TextEntry::make('descuento_tipo')->label('Tipo de Descuento')->badge(),
                    TextEntry::make('descuento_valor')->label('Valor del Descuento')
                        ->formatStateUsing(function ($state, ClienteSuscripcion $record): string {
                            if ($record->descuento_tipo === 'porcentaje') {
                                return "{$state}%";
                            }
                            return number_format($state, 2, ',', '.') . ' €';
                        }),
                    TextEntry::make('descuento_valido_hasta')->label('Descuento Válido Hasta')->date('d/m/Y'),
                    TextEntry::make('descuento_descripcion')->label('Concepto / Observaciones')->columnSpanFull(),
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
            'index' => ListClienteSuscripcions::route('/'),
            'create' => CreateClienteSuscripcion::route('/create'),
            'view' => ViewClienteSuscripcion::route('/{record}'), // <-- AÑADIR ESTA LÍNEA
            'edit' => EditClienteSuscripcion::route('/{record}/edit'),
        ];
    }
}
