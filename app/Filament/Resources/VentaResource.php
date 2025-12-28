<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Models\Cliente;
use Filament\Tables\Filters\Filter;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Checkbox;
use Illuminate\Support\Facades\Mail;
use App\Mail\CambioMetodoPagoMail;
use App\Models\LeadAutoEmailLog;
use Exception;
use App\Models\LeadConversionLink;
use App\Mail\PagoFacturaConfirmado;
use Illuminate\Support\Str;
use App\Mail\LeadConversionLinkMail;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Blade;
use App\Filament\Resources\VentaResource\Pages\ListVentas;
use App\Filament\Resources\VentaResource\Pages\CreateVenta;
use App\Filament\Resources\VentaResource\Pages\ViewVenta;
use App\Filament\Resources\VentaResource\Pages\EditVenta;
use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Filament\Resources\VentaResource\Pages;
use App\Filament\Resources\VentaResource\RelationManagers;
use App\Models\Servicio;
use App\Models\Venta;
use App\Models\VentaItem;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use App\Services\ConfiguracionService; // ¡Añade esta línea!
use App\Enums\ServicioTipoEnum; // <-- Esta es la línea que debe estar aquí
use App\Models\ClienteSuscripcion;
use Filament\Tables\Enums\FiltersLayout;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Carbon\Carbon;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Illuminate\Support\HtmlString;
use App\Models\Proyecto;
use App\Enums\ProyectoEstadoEnum;
use Filament\Forms\Components\Toggle;
use App\Models\User;
use App\Enums\VentaCorreccionEstadoEnum;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Placeholder;
use App\Enums\VentaEstadoEnum;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Forms\Components\Hidden;
use Illuminate\Support\Facades\Log;





class VentaResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Venta::class;


    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-currency-dollar'; // O un icono de venta
    protected static string | \UnitEnum | null $navigationGroup = 'Gestión VENTAS'; // O un grupo propio de Ventas
   // protected static ?string $navigationLabel = 'Admin Ventas';
    protected static ?string $modelLabel = 'Venta';
    protected static ?string $pluralModelLabel = 'Admin Ventas';

      public static function getNavigationLabel(): string
    {
        if (auth()->check() && auth()->user()->hasRole('comercial')) {
            return 'Mis Ventas';
        }

        return 'Admin Ventas';
    }
    public static function getEloquentQuery(): Builder
{
    /** @var User|null $user */
    $user = Auth::user();
    // Empezamos con la consulta base y las precargas que ya tenías
    $query = parent::getEloquentQuery()->with(['items.servicio', 'cliente', 'comercial']); // Añadí cliente y comercial a with para eficiencia

    if (!$user) {
        return $query->whereRaw('1 = 0'); // No hay usuario, no mostrar nada
    }

    // Para depurar (descomenta si es necesario):
    // \Illuminate\Support\Facades\Log::info('User in VentaResource::getEloquentQuery():', ['email' => $user->email, 'roles' => $user->getRoleNames()->toArray()]);

    if ($user->hasRole('super_admin')) {
        // El super_admin ve todas las ventas
        return $query;
    }

    if ($user->hasRole('comercial')) {
        // El comercial solo ve sus ventas.
        // Asumiendo que el campo en la tabla 'ventas' es 'user_id' para el comercial.
        // Si tu campo se llama 'comercial_id', cámbialo aquí.
        return $query->where('user_id', $user->id);
    }
    
    // Lógica para otros roles (ej. un jefe de ventas podría ver las ventas de su equipo)
    // if ($user->hasRole('jefe_ventas')) {
    //     $ids_comerciales_equipo = User::where('jefe_id_en_user_table', $user->id)->pluck('id')->toArray();
    //     return $query->whereIn('user_id', $ids_comerciales_equipo); // Asume que 'user_id' es el comercial en Venta
    // }

    // Por defecto, si el rol no está contemplado arriba y no es admin, no muestra ventas.
    return $query->whereRaw('1 = 0');
}

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'boton_crear_venta',
        ];
    }

    
public static function form(Schema $schema): Schema
{
    return $schema
        ->components([
            Section::make('Datos de la Venta')
                ->columns(3)
                ->schema([
                    Hidden::make('pago_inicial_metodo')
                    ->default(null),
                    Hidden::make('pago_inicial_notas')
                    ->default(null),
                    Select::make('cliente_id')
                        ->label('Cliente')
                        ->relationship('cliente', 'razon_social')
                        ->required()
                        ->default(fn () => request()->query('cliente_id'))
                        ->searchable()
                        ->preload()
                        ->columnSpan(1)
                        ->reactive()
                        ->suffixIcon('heroicon-m-user'),

                    Select::make('lead_id')
                        ->label('Lead de Origen')
                        ->relationship('lead', 'nombre')
                        ->nullable(false)
                        ->required()
                        ->searchable()
                        ->default(fn () => request()->query('lead_id'))
                        ->preload()
                        ->columnSpan(1)
                        ->suffixIcon('heroicon-m-identification'),

                    Select::make('user_id')
                        ->label('Comercial')
                        ->relationship('comercial', 'name')
                        ->default(Auth::id())
                        ->required()
                        ->searchable()
                        ->preload()
                        ->columnSpan(1)
                        ->suffixIcon('heroicon-m-briefcase'),

                    DateTimePicker::make('fecha_venta')
                        ->label('Fecha de Venta')
                        ->native(false)
                        ->required()
                        ->default(now())
                        ->columnSpan(1),

                    TextInput::make('importe_total')
                        ->label('Importe Total de la Venta')
                        ->suffix('€')
                        ->readOnly()
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpan(1),

                    Textarea::make('observaciones')
                        ->label('Observaciones de la Venta')
                        ->nullable()
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Section::make('Items de la Venta')
                ->description('Añade los servicios incluidos en esta venta.')
                ->schema([
                    Repeater::make('items')
                        ->relationship('items')
                        ->afterStateHydrated(function (Get $get, Set $set) {
                            self::updateTotals($get, $set);
                        })
                        ->schema([
                            Select::make('servicio_id')
                                ->label('Servicio')
                                ->relationship(
                                    'servicio',
                                    'nombre',
                                    function (Builder $query, Get $get) {
                                        $clienteId = $get('../../cliente_id');
                                        if (!$clienteId) {
                                            return;
                                        }

                                        $tienePrincipal = ClienteSuscripcion::query()
                                            ->where('cliente_id', $clienteId)
                                            ->where('es_tarifa_principal', true)
                                            ->whereIn('estado', [
                                                ClienteSuscripcionEstadoEnum::ACTIVA->value,
                                                ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION->value,
                                            ])
                                            ->exists();

                                        if ($tienePrincipal) {
                                            $query->where('es_tarifa_principal', false);
                                        }
                                    }
                                )
                                ->required()
                                ->searchable()
                                ->preload()
                                ->distinct()
                                ->live()
                                ->columnSpan(2)
                                ->afterStateUpdated(function (Get $get, Set $set, ?int $state) {
                                    if ($state && $servicio = Servicio::find($state)) {
                                        $set('precio_unitario', $servicio->precio_base);
                                    } else {
                                        $set('precio_unitario', 0);
                                    }
                                    self::updateTotals($get, $set);
                                }),

                            TextInput::make('nombre_personalizado')
                                ->label('Nombre del Servicio (editable)')
                                ->placeholder('Ej. Servicio jurídico especial...')
                                ->helperText('Solo si el servicio permite nombre personalizado.')
                                ->columnSpan(2)
                                ->required()
                                ->visible(fn (Get $get) => optional(Servicio::find($get('servicio_id')))->es_editable),

                            Toggle::make('requiere_proyecto')
                                ->label('¿Este servicio requiere proyecto?')
                                ->helperText('Se generará un proyecto si está activo.')
                                ->columnSpan(2)
                                ->default(false)
                                ->visible(fn (Get $get) => optional(Servicio::find($get('servicio_id')))->es_editable),

                            TextInput::make('cantidad')
                                ->label('Cantidad')
                                ->numeric()->type('text')->inputMode('numeric')
                                ->required()
                                ->default(1)
                                ->minValue(1)
                                ->live()
                                ->columnSpan(1)
                                ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotals($get, $set)),

                            TextInput::make('precio_unitario')
                                ->label('Precio Base (€)')
                                ->helperText('Precio unitario original del servicio, sin IVA ni descuentos.')
                                ->numeric()->type('text')->inputMode('decimal')
                                ->required()
                                ->suffix('€')
                                ->columnSpan(1)
                                ->live(debounce: 600) // 👈 igual, esperamos un poco
                                ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotals($get, $set)),

                            TextInput::make('subtotal')
                                ->label('Subtotal Base (€)')
                                ->helperText('Subtotal de la línea, sin descuentos ni IVA.')
                                ->numeric()->type('text')
                                ->readOnly()
                                ->suffix('€')
                                ->columnSpan(1),

                            Hidden::make('precio_unitario_aplicado')->dehydrated(true),
                            Hidden::make('subtotal_aplicado')->dehydrated(true),
                            Hidden::make('subtotal_aplicado_con_iva')->dehydrated(true),

                            TextInput::make('subtotal_con_iva')
                                ->label('Subtotal con IVA (Base)')
                                ->numeric()->type('text')
                                ->readOnly()->suffix('€')
                                ->columnSpan(1)
                                ->dehydrated(true),

                           DatePicker::make('fecha_inicio_servicio')
                        ->label('Inicio Servicio')
                        ->native(false)
                        ->nullable()
                        ->required(function (Get $get): bool {
                            // --- LÓGICA DE VISIBILIDAD MOVIDA A 'required' ---
                            // 1. Si el servicio actual no es recurrente, el campo no es obligatorio.
                            $servicioId = $get('servicio_id');
                            if (!$servicioId) return false;
                            
                            $servicio = Servicio::find($servicioId);
                            if (!$servicio || $servicio->tipo->value !== 'recurrente') {
                                return false;
                            }

                            // 2. Comprobamos si CUALQUIER item en la venta requiere un proyecto.
                            $todosLosItems = $get('../../items') ?? [];
                            foreach ($todosLosItems as $itemState) {
                                $itemServicioId = $itemState['servicio_id'] ?? null;
                                if (!$itemServicioId) continue;
                                
                                $itemServicio = Servicio::find($itemServicioId);
                                if (!$itemServicio) continue;
                                
                                // La nueva condición que comprueba ambos casos
                                $esteItemRequiereProyecto = $itemServicio->es_editable
                                    ? ($itemState['requiere_proyecto'] ?? false)
                                    : $itemServicio->requiere_proyecto_activacion;

                                if ($esteItemRequiereProyecto) {
                                    // Si encontramos un proyecto, el campo NO es obligatorio.
                                    return false; 
                                }
                            }

                            // 3. Si no se encontró ningún proyecto en toda la venta, el campo SÍ es obligatorio.
                            return true;
                        })
                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                            $duracion = (int) $get('descuento_duracion_meses');

                            if ($state && $duracion > 0) {
                                $fechaFin = Carbon::parse($state)
                                    ->addMonths($duracion - 1)
                                    ->endOfMonth()
                                    ->format('Y-m-d');

                                $set('descuento_valido_hasta', $fechaFin);
                            }
                        })
                        ->columnSpan(2),

                            Textarea::make('observaciones_item')
                                ->label('Notas del servicio')
                                ->nullable()
                                ->rows(1)
                                ->columnSpan(4),

                           Section::make('Aplicar Descuento')
    ->collapsible()
    ->collapsed()
    ->schema([
        Select::make('descuento_tipo')
            ->label('Tipo de Descuento')
            ->placeholder('Sin descuento')
            ->options([
                'porcentaje' => 'Porcentaje (%)',
                'fijo'       => 'Cantidad Fija (€)',
                'precio_final' => 'Precio Final (€)',
            ])
            ->nullable()
            ->live()
            ->columnSpan(2)
            ->dehydrated(true)
            ->afterStateUpdated(function(Get $get, Set $set) {
                $set('descuento_valor', null);
                $set('descuento_duracion_meses', null);
                $set('descuento_valido_hasta', null);
                $set('observaciones_descuento', null);
                VentaResource::updateTotals($get, $set);
            }),

        TextInput::make('descuento_valor')
            ->label('Valor del Descuento')
            ->numeric()->type('text')->inputMode('decimal')
            ->nullable()
            ->live(debounce: 600)
            ->columnSpan(2)
            ->visible(fn (Get $get) => !empty($get('descuento_tipo')))
            ->suffix(fn(Get $get):?string => match($get('descuento_tipo')) {
                'porcentaje' => '%',
                'fijo', 'precio_final' => '€',
                default => null
            })
            ->helperText(fn(Get $get):?string => match($get('descuento_tipo')) {
                'porcentaje' => 'Introduce solo el número del porcentaje (ej: 50).',
                'fijo'       => 'Introduce la cantidad fija que se descontará.',
                'precio_final' => 'Introduce el precio final que tendrá esta línea.',
                default      => null
            })
            ->dehydrated(true)
            ->afterStateUpdated(fn (Get $get, Set $set) => VentaResource::updateTotals($get, $set)),

        TextInput::make('descuento_duracion_meses')
            ->label('Duración (meses)')
            ->numeric()
            ->type('text')
            ->inputMode('numeric')
            ->nullable()
            ->columnSpan(1)
            ->live()
            ->dehydrated(true)
            ->visible(function (Get $get): bool {
                if (empty($get('descuento_tipo')) || !$servicioId = $get('servicio_id')) {
                    return false;
                }
                return Servicio::find($servicioId)?->tipo?->value === 'recurrente';
            })
            ->afterStateUpdated(function (Get $get, Set $set, ?string $state) {
                $fechaInicio = $get('fecha_inicio_servicio');
                $duracion = (int) $state;

                if ($fechaInicio && $duracion > 0) {
                    $fechaFin = Carbon::parse($fechaInicio)
                        ->addMonths($duracion - 1)
                        ->endOfMonth()
                        ->format('Y-m-d');

                    $set('descuento_valido_hasta', $fechaFin);
                } else {
                    $set('descuento_valido_hasta', null);
                }

                VentaResource::updateTotals($get, $set);
            }),

        DatePicker::make('descuento_valido_hasta')
            ->label('Dto Válido Hasta')
            ->native(false)
            ->nullable()
            ->readOnly()
            ->columnSpan(2)
            ->placeholder('Se calcula automáticamente')
            ->visible(function (Get $get): bool {
                if (empty($get('descuento_tipo')) || !$servicioId = $get('servicio_id')) {
                    return false;
                }
                return Servicio::find($servicioId)?->tipo?->value === 'recurrente';
            })
            ->dehydrated(true),

        Textarea::make('observaciones_descuento') 
            ->label('Descripción del Descuento')
            ->nullable()
            ->columnSpan(3)
            ->visible(fn (Get $get) => !empty($get('descuento_tipo')))
            ->dehydrated(true),

        TextInput::make('subtotal_aplicado') 
            ->label('Final (sin IVA)')
            ->numeric()->type('text')
            ->readOnly()
            ->columnSpan(1)
            ->suffix('€')
            ->visible(fn (Get $get) => !empty($get('descuento_tipo'))),

        TextInput::make('subtotal_aplicado_con_iva') 
            ->label('Final (con IVA)')
            ->numeric()->type('text')
            ->readOnly()
            ->columnSpan(1)
            ->suffix('€')
            ->visible(fn (Get $get) => !empty($get('descuento_tipo'))),
    ])
    ->columns(12)
    ->columnSpanFull(),


                        ])
                        ->columns(12)
                        ->defaultItems(1)
                        ->reorderable(true)
                        ->collapsible()
                        ->cloneable()
                        ->minItems(1)
                        ->addActionLabel('Añadir Servicio')
                        ->live(),

                        
                ])
                ->columnSpanFull(),

                 // ▼▼▼ AÑADE ESTA NUEVA SECCIÓN ▼▼▼
    Section::make('Gestión de la Corrección')
        ->icon('heroicon-o-pencil-square')         
        ->collapsible()
        // Esta sección solo se muestra en la página de 'edit' Y si la venta tiene un estado de corrección
        ->visible(fn (string $operation, Venta $record = null): bool => 
            $operation === 'edit' && !is_null($record?->correccion_estado)
        )
        ->schema([
            // Selector para que el admin cambie el estado
            Select::make('correccion_estado')
                ->label('Estado de la Solicitud')
                ->options(VentaCorreccionEstadoEnum::class)
                ->required(),

            // Campos de solo lectura para mostrar la información de la solicitud
            Placeholder::make('solicitante')
                ->label('Solicitado por')
                ->content(fn (Venta $record): ?string => $record->solicitanteCorreccion?->name),

            Placeholder::make('fecha_solicitud')
                ->label('Fecha de la Solicitud')
                ->content(fn (Venta $record): ?string => $record->correccion_solicitada_at?->format('d/m/Y H:i')),
            
            Placeholder::make('motivo_solicitud')
                ->label('Motivo del Comercial')
                ->content(fn (Venta $record): ?string => $record->correccion_motivo),
        ])
        ->columns(2),
        ]);
}


    private static function updateTotals(Get $get, Set $set): void
    {
        $cantidad = (float)($get('cantidad') ?? 1);
        $precioUnitario = (float)($get('precio_unitario') ?? 0);
        $subtotal = round($cantidad * $precioUnitario, 2);
        $set('subtotal', $subtotal);

        // -------------------------------------------------------------
        // 🔥 LÓGICA DE IMPUESTOS DINÁMICA (CEREBRO FISCAL)
        // -------------------------------------------------------------
        $impuesto = 21.00; // Valor por defecto (Península)

        // Buscamos el cliente seleccionado en el formulario padre
        // Nota: '../../cliente_id' sube niveles en el repeater para buscar el cliente
        $clienteId = $get('cliente_id') ?? $get('../../cliente_id');
        
        if ($clienteId) {
            $cliente = Cliente::find($clienteId);
            if ($cliente) {
                // El helper decide: 0.00 si es Canarias, variable IVA_general si no
                $impuesto = Cliente::getPorcentajeImpuesto(
                    $cliente->codigo_postal, 
                    $cliente->provincia
                );
            }
        }

        $factorIva = 1 + ($impuesto / 100);
        // -------------------------------------------------------------

        // Aplicamos el factor detectado
        $subtotalConIva = round($subtotal * $factorIva, 2);
        $set('subtotal_con_iva', $subtotalConIva);

        // --- LÓGICA DE DESCUENTOS (Se mantiene igual) ---
        $descuentoTipo = $get('descuento_tipo');
        $descuentoValor = (float)($get('descuento_valor') ?? 0);
        $precioFinalConDto = $subtotal;

        if (!empty($descuentoTipo) && is_numeric($descuentoValor) && $descuentoValor > 0) {
            switch ($descuentoTipo) {
                case 'porcentaje':
                    $precioFinalConDto = round($subtotal - ($subtotal * ($descuentoValor / 100)), 2);
                    break;
                case 'fijo':
                    $precioFinalConDto = round($subtotal - $descuentoValor, 2);
                    break;
                case 'precio_final':
                    $precioFinalConDto = round($descuentoValor, 2);
                    break;
            }
        }
        $precioFinalConDto = max(0, $precioFinalConDto);

        $set('subtotal_aplicado', $precioFinalConDto); 
        
        // Aplicamos el mismo factor de IVA al precio final con descuento
        $set('subtotal_aplicado_con_iva', round($precioFinalConDto * $factorIva, 2));
    }

    public static function table(Table $table): Table
    {
        return $table
        ->paginated([25, 50, 100, 'all']) // Ajusta opciones si quieres
        ->striped()
        ->recordUrl(null)    // Esto quita la navegación al hacer clic en la fila
        ->defaultSort('created_at', 'desc') // Ordenar por defecto
            ->columns([
                
                TextColumn::make('estado')
                    ->label('Estado venta')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (VentaEstadoEnum $state) => $state->getLabel())
                    ->color(fn (VentaEstadoEnum $state) => $state->getColor()),
             // Columna de Estado de Firma (BLINDADA PARA NULOS)
                TextColumn::make('signed_at')
                ->label('Contrato')
                ->badge()
                ->getStateUsing(fn (Venta $record) => $record->signed_at ? 'Firmado' : 'Pendiente de firma')
                ->color(fn (string $state) => match ($state) {
                    'Firmado'          => 'success',
                    'Pendiente de firma' => 'warning',
                    default            => 'gray',
                })
                ->icon(fn (string $state) => match ($state) {
                    'Firmado'          => 'heroicon-m-check-badge',
                    'Pendiente de firma' => 'heroicon-m-clock',
                    default            => null,
                })
                ->sortable(),
TextColumn::make('confirmada_at')
    ->label('Fecha Cierre') // Cambio de nombre para ser más preciso
    ->sortable()
    ->badge()
    ->getStateUsing(function (Venta $record): ?string {
        // Si está completada y tiene fecha, mostramos la fecha
        if ($record->estado === VentaEstadoEnum::COMPLETADA && $record->confirmada_at) {
            return $record->confirmada_at->format('d/m/Y');
        }

        // Si está cancelada
        if ($record->estado === VentaEstadoEnum::CANCELADA) {
            return 'Cancelada';
        }

        // Si está pendiente, devolvemos null para que Filament ponga un guion '—'
        // o puedes devolver 'En curso' si prefieres algo distinto a 'Pendiente'.
        return null; 
    })
    ->color(fn ($state) => match ($state) {
        'Cancelada' => 'danger',
        null        => 'gray',    // El guion o vacío se verá gris discreto
        default     => 'success', // La fecha se verá en verde
    })
    ->placeholder('—'), // Esto pone el guion elegante cuando es null
                TextColumn::make('cliente.razon_social')
                    ->label('Cliente')
                    ->url(fn (Venta $record): ?string => 
                    $record->cliente_id
                        ? ClienteResource::getUrl('view', ['record' => $record->cliente_id])
                        : null
                    )
                    // Color amarillo (warning) si es enlace, gris si no
                    ->color(fn (Venta $record): ?string =>
                        $record->cliente_id
                            ? 'warning'
                            : null
                    )
                    ->searchable()
                    ->sortable(),
                   TextColumn::make('lead_id')
                    ->label('Lead')
                    ->formatStateUsing(function ($state, Venta $record) {
                        return $record->lead_id ? "#{$record->lead_id}" : '—';
                    })
                    ->badge()
                    ->color(fn (Venta $record) => $record->lead_id ? 'warning' : 'gray')
                    ->url(fn (Venta $record): ?string =>
                        $record->lead_id
                            ? LeadResource::getUrl('view', ['record' => $record->lead_id])
                            : null
                    )
                    ->openUrlInNewTab()
                    ->sortable(),
                TextColumn::make('comercial.full_name')
                ->label('Vendido por')
                   ->badge()
                   ->color('info')
                    ->sortable(),
                TextColumn::make('fecha_venta')
                    ->dateTime('d/m/y - H:i')
                    ->sortable(),
                
            TextColumn::make('importe_recurrente')
           ->label('Recurrente')
           ->getStateUsing(function (Venta $record): string {
               $totalRec = VentaItem::query()
                   ->where('venta_id', $record->id)
                   ->whereHas('servicio', fn (Builder $q) => $q->where('tipo', 'recurrente'))
                   ->sum('subtotal');

               return number_format($totalRec, 2, ',', '.') . ' €';
           })
           ->sortable(false),

       TextColumn::make('importe_unico')
           ->label('Único')
           ->getStateUsing(function (Venta $record): string {
               $totalUnico = VentaItem::query()
                   ->where('venta_id', $record->id)
                   ->whereHas('servicio', fn (Builder $q) => $q->where('tipo', 'unico'))
                   ->sum('subtotal');

               return number_format($totalUnico, 2, ',', '.') . ' €';
           })
           ->sortable(false),

    

     // ▼▼▼ REEMPLAZA ESTA COLUMNA ▼▼▼
    TextColumn::make('descuento_mensual_recurrente') // Nombre virtual
        ->label('Dto. Mensual Rec.')
        ->badge()
        ->getStateUsing(function (Venta $record): float {
            // Calcula el descuento total solo para items recurrentes
            return $record->items
                ->where('servicio.tipo', ServicioTipoEnum::RECURRENTE)
                ->sum(function ($item) {
                    $subtotalBase = (float)($item->cantidad ?? 1) * (float)($item->precio_unitario ?? 0);
                    $subtotalAplicado = (float)($item->subtotal_aplicado ?? $subtotalBase);
                    return $subtotalBase - $subtotalAplicado;
                });
        })
        ->formatStateUsing(function ($state, Venta $record): string {
            if ($state > 0) {
                $duracionTexto = '';
                // Lógica para encontrar la duración (opcional)
                foreach ($record->items as $item) {
                    if ($item->servicio?->tipo === ServicioTipoEnum::RECURRENTE && !empty($item->descuento_duracion_meses)) {
                        $duracionTexto = " ({$item->descuento_duracion_meses} meses)";
                        break;
                    }
                }
                return '-' . number_format($state, 2, ',', '.') . ' €/mes' . $duracionTexto;
            }
            return 'Sin Dto.';
        })
        ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
           ->toggleable(isToggledHiddenByDefault: true),

    // ▼▼▼ Y REEMPLAZA ESTA OTRA COLUMNA ▼▼▼
    TextColumn::make('descuento_unico') // Nombre virtual
        ->label('Dto. Único')
        ->badge()
        ->getStateUsing(function (Venta $record): float {
            // Calcula el descuento total solo para items únicos
            return $record->items
                ->where('servicio.tipo', ServicioTipoEnum::UNICO)
                ->sum(function ($item) {
                    $subtotalBase = (float)($item->cantidad ?? 1) * (float)($item->precio_unitario ?? 0);
                    $subtotalAplicado = (float)($item->subtotal_aplicado ?? $subtotalBase);
                    return $subtotalBase - $subtotalAplicado;
                });
        })
        ->formatStateUsing(fn ($state) => $state > 0 ? '-' . number_format($state, 2, ',', '.') . ' €' : 'Sin Dto.')
        ->color(fn ($state) => $state > 0 ? 'danger' : 'gray')
           ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('importe_total')
                    ->label('Importe Total')
                    ->color('success')
                   ->size('lg')
                    ->icon('heroicon-o-currency-euro')
                    ->iconPosition('after')
                    ->iconColor('warning')
                    ->weight('bold')
                    ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €')
                    
                    ->sortable(),
                TextColumn::make('created_at')
                ->label('Venta creada')
                    ->dateTime('d/m/y - H:i')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('updated_at')
                ->label('Venta actualizada')
                ->dateTime('d/m/y - H:i')
                ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                   
            ])
            ->filters([
                    // Filtro principal por estado (Pendiente / Completada / Cancelada)
        SelectFilter::make('estado')
            ->label('Estado venta')
            ->options([
                VentaEstadoEnum::PENDIENTE->value  => VentaEstadoEnum::PENDIENTE->getLabel(),
                VentaEstadoEnum::COMPLETADA->value => VentaEstadoEnum::COMPLETADA->getLabel(),
                VentaEstadoEnum::CANCELADA->value  => VentaEstadoEnum::CANCELADA->getLabel(),
            ]),

        // Filtro rápido "Solo ventas cerradas"
        TernaryFilter::make('solo_completadas')
            ->label('Solo cerradas')
            ->placeholder('Todas')
            ->trueLabel('Solo completadas')
            ->falseLabel('Solo no completadas')
            ->queries(
                true: fn ($query) => $query->where('estado', VentaEstadoEnum::COMPLETADA),
                false: fn ($query) => $query->where('estado', '!=', VentaEstadoEnum::COMPLETADA),
                blank: fn ($query) => $query,
            ),
                TernaryFilter::make('signed_at')
                    ->label('Estado del Contrato')
                    ->placeholder('Todas')
                    ->trueLabel('Firmadas')
                    ->falseLabel('Pendientes de Firma')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('signed_at'),
                        false: fn (Builder $query) => $query->whereNull('signed_at'),
                    ),
                /*  Tables\Filters\SelectFilter::make('cliente_id')
                    ->relationship('cliente', 'razon_social')
                    ->searchable()
                    ->preload()
                    ->label('Filtrar por Cliente'), */

              SelectFilter::make('user_id')
                    ->relationship('comercial', 'name', fn (Builder $query) => 
                        // Filtra para mostrar usuarios con el rol 'comercial' O 'super_admin'
                        $query->whereHas('roles', fn (Builder $query) => 
                            $query->where('name', 'comercial')
                                  ->orWhere('name', 'super_admin') // <<< CAMBIO AQUI: Añadir super_admin
                        )
                    )
                    ->searchable()
                    ->preload()
                    ->label('Comercial'),

                // Filtro por Tipo de Servicio (Único/Recurrente)
                SelectFilter::make('tipo_servicio')
                    ->options([
                        'unico'      => 'Servicio Único',    // Usa la cadena literal 'unico'
                        'recurrente' => 'Servicio Recurrente', // Usa la cadena literal 'recurrente'
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        // Este filtro necesita un join con venta_items y servicios
                        if (isset($data['value']) && filled($data['value'])) {
                            $query->whereHas('items', function (Builder $query) use ($data) {
                                $query->whereHas('servicio', function (Builder $query) use ($data) {
                                    $query->where('tipo', $data['value']);
                                });
                            });
                        }
                        return $query;
                    })
                    ->label('Tipo de Servicio'),

                // Filtro por Rango de Fechas de Venta
                DateRangeFilter::make('confirmada_at')
                    
                   
                    ->label('Venta consolidada'),

                // Filtro por si tiene Descuento (cualquier tipo)
                Filter::make('con_descuento')
                    ->query(function (Builder $query): Builder {
                        // Filtra ventas que tengan al menos un item con descuento
                        return $query->whereHas('items', function (Builder $query) {
                            $query->where(function (Builder $query) {
                                // Donde descuento_tipo NO es nulo Y descuento_valor es mayor que 0
                                $query->whereNotNull('descuento_tipo')
                                      ->where('descuento_valor', '>', 0);
                            });
                        });
                    })
                    ->toggle() // Se activa/desactiva con un switch
                    ->label('Descuento'),
                      Filter::make('correccion_solicitada')
                        ->label('Corrección solicitada')
                        ->query(fn (Builder $query): Builder => $query->where('correccion_estado', VentaCorreccionEstadoEnum::SOLICITADA))
                       
                        ->toggle(),
                                        ],layout: FiltersLayout::AboveContent)
                                            ->filtersFormColumns(9)
            ->recordActions([
    // 🔄 ACCIÓN: CAMBIAR MÉTODO DE PAGO (Con Log y Comentario)
Action::make('cambiar_metodo_pago')
    ->label('') 
    ->tooltip('Cambiar método de pago (Tarjeta/Transferencia)')
    ->icon('heroicon-o-arrows-right-left')
    ->color('gray')
    ->schema([
        Radio::make('nuevo_metodo')
            ->label('Selecciona el nuevo método de pago')
            ->options([
                'tarjeta'       => 'Tarjeta (Stripe)',
                'transferencia' => 'Transferencia Bancaria',
            ])
            ->required()
            ->default(fn (Venta $record) => $record->pago_inicial_metodo),
        
        Textarea::make('notas')
            ->label('Notas internas')
            ->rows(2),

        Checkbox::make('notificar_cliente')
            ->label('Enviar email al cliente con las nuevas instrucciones')
            ->default(true)
            ->helperText('Si lo marcas, el cliente recibirá el IBAN o el enlace de pago por correo.'),
    ])
    ->action(function (Venta $record, array $data) {
        // 1. Actualizar BD
        $record->update([
            'pago_inicial_metodo' => $data['nuevo_metodo'],
            'pago_inicial_notas'  => $data['notas'] ?? $record->pago_inicial_notas,
        ]);

        // 2. Enviar Email y Guardar Log Técnico
        if ($data['notificar_cliente'] && $record->cliente && $record->cliente->email_contacto) {
            try {
                Mail::to($record->cliente->email_contacto)
                    ->send(new CambioMetodoPagoMail($record));
                
                // ✅ LOG TÉCNICO
                if ($record->lead_id) {
                    LeadAutoEmailLog::create([
                        'lead_id'             => $record->lead_id,
                        'estado'              => $record->lead->estado->value ?? 'unknown',
                        'intento'             => 1,
                        'template_identifier' => 'payment_method_change',
                        'subject'             => 'Actualización Método de Pago',
                        'body_preview'        => 'Notificación de cambio a ' . $data['nuevo_metodo'],
                        'scheduled_at'        => now(),
                        'sent_at'             => now(),
                        'status'              => 'sent',
                        'triggered_by_user_id'=> auth()->id(),
                        'trigger_source'      => 'venta_resource_change_method',
                    ]);
                }

                Notification::make()->title('Email de instrucciones enviado')->success()->send();
            } catch (Exception $e) {
                Notification::make()->title('Error enviando email')->body($e->getMessage())->warning()->send();
            }
        }

        // 3. ✅ COMENTARIO EN EL LEAD (Historial Visual)
        if ($record->lead && method_exists($record->lead, 'comentarios')) {
            $msg = "🔄 Método de pago cambiado a " . strtoupper($data['nuevo_metodo']);
            if ($data['notificar_cliente']) {
                $msg .= " y notificado por email al cliente.";
            } else {
                $msg .= " (sin notificar al cliente).";
            }

            $record->lead->comentarios()->create([
                'user_id'   => auth()->id(), // Usuario que hizo el cambio
                'contenido' => $msg,
            ]);
        }

        Notification::make()
            ->title('Método de pago actualizado a ' . strtoupper($data['nuevo_metodo']))
            ->success()
            ->send();
    })
    ->visible(fn (Venta $record) => 
        !$record->tienePagoInicialCompletado() && 
        $record->estado !== VentaEstadoEnum::CANCELADA
    ),
                // ✅ ACCIÓN: CONFIRMAR PAGO TRANSFERENCIA (Con envío de Factura)
Action::make('confirmar_transferencia')
    ->label('')
    ->tooltip('Confirmar recepción de Transferencia')
    ->icon('heroicon-o-banknotes')
    ->color('success')
    ->requiresConfirmation()
    ->modalHeading('¿Confirmar recepción de transferencia?')
    ->modalDescription(fn (Venta $record) =>
        "Se generará la factura, se activarán los servicios y se enviará la factura por email al cliente."
    )
    ->visible(fn (Venta $record) =>
        $record->pago_inicial_metodo === 'transferencia' &&
        ! $record->tienePagoInicialCompletado() &&
        $record->estado !== VentaEstadoEnum::CANCELADA
    )
    ->action(function (Venta $record) {

        // 1️⃣ RECUPERAR extraData DEL FORMULARIO FIRMADO
        $extraData = [];

        try {
            $link = LeadConversionLink::where('meta->existing_venta_id', $record->id)
                ->latest()
                ->first();

            if ($link) {
                $extraData = $link->meta['form_data'] ?? [];
            }
        } catch (Exception $e) {
            // no-op
        }

        try {

            // 2️⃣ CONFIRMAR TRANSFERENCIA (esto ya es "pagado")
            $record->procesarCobroInicial(
                fechaPago: now(),
                metodoPago: 'transferencia_confirmada', // ✅ CLAVE
                paymentIntentId: null,
                extraData: $extraData
            );

            // 3️⃣ ENVIAR FACTURA AL CLIENTE (SI EXISTE)
            $factura = $record->facturas()->latest()->first();

            if ($factura && $record->cliente && $record->cliente->email_contacto) {
                try {
                    Mail::to($record->cliente->email_contacto)
                        ->send(new PagoFacturaConfirmado($factura));

                    Notification::make()
                        ->title('Pago confirmado y factura enviada')
                        ->success()
                        ->send();
                } catch (Exception $e) {

                    Notification::make()
                        ->title('Pago confirmado, pero falló el email')
                        ->body($e->getMessage())
                        ->warning()
                        ->send();
                }
            } else {

                Notification::make()
                    ->title('Pago confirmado correctamente')
                    ->success()
                    ->send();
            }

        } catch (Exception $e) {

            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }),



// 🚀 ENVIAR CONTRATO (Manual)
            Action::make('enviar_contrato')
                ->label('') // <--- SIN TEXTO
                ->tooltip('Enviar Contrato para Firma') // Tooltip al pasar el ratón
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Enviar Contrato')
                ->modalDescription('Se generará un enlace único basado en esta venta. El cliente recibirá un email para firmar.')
             ->visible(fn (Venta $record) => 
                        $record->lead_id && 
                        $record->lead && 
                        is_null($record->lead->contract_signed_at) && // Que no haya firmado
                        $record->estado !== VentaEstadoEnum::CANCELADA && // Que no esté cancelada
                        $record->items()->exists() // Que tenga servicios (evita errores)
                    )
                ->action(function (Venta $record) {
                    Log::info('🔥 ENVIAR CONTRATO MANUAL EJECUTADO');

                    if (!$record->lead || !$record->cliente) {
                        Notification::make()->title('Error')->body('Falta Lead o Cliente.')->danger()->send();
                        return;
                    }

                // Construir Blueprint
                $itemsBlueprint = $record->items->map(function ($item) {
                    $svc = $item->servicio;

                    // Nombre real a analizar
                    $nombre = strtoupper($item->nombre_personalizado ?: $svc->nombre);

                    return [
                        'servicio_id'         => $svc->id,
                        'nombre'              => $item->nombre_personalizado ?: $svc->nombre,
                        'tipo'                => $svc->tipo->value,
                        'precio_base'         => $item->precio_unitario_aplicado ?? $item->precio_unitario,
                        'unidades'            => $item->cantidad,
                        'total_linea'         => $item->subtotal_aplicado,
                        'es_tarifa_principal' => $svc->es_tarifa_principal,

                        // 🔥 Flags REALES que usa getActiveForms()
                        'es_alta_autonomo'     => (str_contains($nombre, 'ALTA') && str_contains($nombre, 'AUTON')),
                        'es_creacion_sociedad' => (str_contains($nombre, 'SOCIEDAD') || str_contains($nombre, 'SL')),
                        'es_capitalizacion'    => (str_contains($nombre, 'CAPITALIZA')),
                    ];
                })->toArray();
                        // 🔥🔥🔥 AÑADIR ESTE LOG AQUÍ
                         Log::info('BLUEPRINT MANUAL GENERADO', $itemsBlueprint);
                              // 🔥🔥🔥


                    if (empty($itemsBlueprint)) {
                        Notification::make()->title('Error')->body('Venta sin servicios.')->danger()->send();
                        return;
                    }

                    // Detectar Formulario PRINCIPAL
                    // --------------------------------
                    // Regla general:
                    // 1) Si lleva SL → formulario SL
                    // 2) Si lleva capitalización → formulario capitalización
                    // 3) Si lleva alta autónomo → formulario alta autónomo
                    // 4) Si solo lleva fiscal recurrente → el estándar
                    $formType = 'alta_autonomo_fiscal_recurrente';

                    foreach ($itemsBlueprint as $bpItem) {
                        if (!empty($bpItem['es_creacion_sociedad'])) {
                            $formType = 'creacion_sociedad';
                            break;
                        }

                        if (!empty($bpItem['es_capitalizacion'])) {
                            $formType = 'capitalizacion';
                            // seguimos por si hay SL, pero prioridad después de SL
                        }

                        if (!empty($bpItem['es_alta_autonomo'])) {
                            $formType = 'alta_autonomo';
                            // seguimos por si hay SL o capitalización
                        }
                    }

                    // Pre-rellenar datos
                    $formData = [
                        'nombre'             => $record->cliente->nombre ?? '',
                        'apellidos'          => $record->cliente->apellidos ?? '',
                        'dni'                => $record->cliente->dni_cif,
                        'email'              => $record->cliente->email_contacto,
                        'telefono'           => $record->cliente->telefono_contacto,
                        'direccion'          => $record->cliente->direccion,
                        'cp'                 => $record->cliente->codigo_postal,
                        'localidad'          => $record->cliente->localidad,
                        'provincia'          => $record->cliente->provincia,
                        'comunidad_autonoma' => $record->cliente->comunidad_autonoma,
                        'cuenta_bancaria_ss' => $record->cliente->iban_asesorfy,
                        'tipo_cliente_id'    => $record->cliente->tipo_cliente_id,
                    ];

                    // Crear Link
                    $link = LeadConversionLink::create([
                        'lead_id'    => $record->lead_id,
                        'token'      => Str::uuid(),
                        'expires_at' => now()->addDays(15),
                        'mode'       => 'manual',
                        'meta'       => [
                            'form_type'      => $formType,
                            'sale_blueprint' => ['modo' => 'manual', 'servicios' => $itemsBlueprint],
                            'form_data'      => $formData,
                            'existing_venta_id'   => $record->id,
                            'existing_cliente_id' => $record->cliente_id,
                        ],
                    ]);

                    // Enviar
                    try {
                        Mail::to($record->cliente->email_contacto)
                            ->send(new LeadConversionLinkMail($record->lead, $link));
                        
                        // Logs
                        LeadAutoEmailLog::create([
                            'lead_id'             => $record->lead_id,
                            'estado'              => $record->lead->estado->value ?? 'unknown',
                            'intento'             => 1,
                            'template_identifier' => 'conversion_link_manual',
                            'subject'             => 'Firma tu contrato',
                            'body_preview'        => 'Enlace manual venta #' . $record->id,
                            'scheduled_at'        => now(),
                            'sent_at'             => now(),
                            'status'              => 'sent',
                            'mail_driver'         => config('mail.default'),
                            'triggered_by_user_id'=> auth()->id(),
                            'trigger_source'      => 'manual_action_venta',
                        ]);

                        $record->lead->comentarios()->create([
                            'user_id'   => 9999,
                            'contenido' => "📤 🔗 Contrato enviado manual (Venta #{$record->id}).",
                        ]);
                        
                        Notification::make()->title('Contrato Enviado')->success()->send();
                    } catch (Exception $e) {
                        Notification::make()->title('Error email')->body($e->getMessage())->danger()->send();
                    }
                }),
             
                // 1. Añadimos la acción para VER los detalles (el ojo)
                    ViewAction::make()
                        ->label('') // Sin texto, solo el icono
                        ->tooltip('Ver Venta'),
                EditAction::make()
                ->label('')
                ->tooltip('Editar Venta')
                 ->visible(function (Venta $record): bool {
                    // El botón será visible solo si la venta NO tiene facturas asociadas.
                    return !$record->facturas()->exists();
                }),
      Action::make('solicitar_correccion')
    ->label('')
    ->tooltip('Solicitar Corrección')
    ->icon('heroicon-o-chat-bubble-left-right')
    ->color('warning')
    ->visible(fn (Venta $record): bool => 
        $record->facturas()->exists() &&
        empty($record->getRawOriginal('correccion_estado')) &&
        auth()->user()->hasAnyRole(['comercial', 'coordinador', 'super_admin'])
    )
    ->schema([
        Textarea::make('motivo')
            ->label('Motivo de la corrección')
            ->required()
            ->helperText('Explica detalladamente por qué es necesario modificar esta venta.'),
    ])
    ->action(function (Venta $record, array $data): void {
        // Actualizamos la venta con los datos de la solicitud
        $record->update([
            'correccion_estado'            => VentaCorreccionEstadoEnum::SOLICITADA->value,
            'correccion_motivo'            => $data['motivo'],
            'correccion_solicitada_at'     => now(),
            'correccion_solicitada_por_id' => auth()->id(),
        ]);

        // Notificar a admins y coordinadores
        $destinatarios = User::whereHas('roles', fn ($q) =>
            $q->whereIn('name', ['super_admin', 'coordinador'])
        )->get();

        Notification::make()
            ->title('Solicitud de Corrección de Venta')
            ->body("El comercial " . auth()->user()->name . " solicita corregir la Venta #{$record->id}.")
            ->warning()
            ->sendToDatabase($destinatarios);

        // Mensaje de confirmación
        Notification::make()
            ->title('Solicitud enviada correctamente')
            ->success()
            ->send();
    }),
     Action::make('estado_correccion')
    // ▼▼▼ CAMBIO AQUÍ ▼▼▼
    ->label(fn (Venta $record): string => match ($record->correccion_estado) {
        VentaCorreccionEstadoEnum::SOLICITADA => 'Corrección Solicitada',
        VentaCorreccionEstadoEnum::EN_PROCESO => 'Corrección en Proceso',
        VentaCorreccionEstadoEnum::COMPLETADA => 'Corrección Completada',
        VentaCorreccionEstadoEnum::RECHAZADA => 'Corrección Rechazada',
    })
    ->color(fn (Venta $record): string => match ($record->correccion_estado) {
        VentaCorreccionEstadoEnum::SOLICITADA => 'danger',
        VentaCorreccionEstadoEnum::EN_PROCESO => 'primary',
        VentaCorreccionEstadoEnum::COMPLETADA => 'success',
        VentaCorreccionEstadoEnum::RECHAZADA => 'gray',
    })
    ->icon('heroicon-o-exclamation-triangle')
    ->disabled()
    ->visible(fn (Venta $record): bool => !is_null($record->correccion_estado))
   /*   ->extraAttributes(function (Venta $record): array {
        if ($record->correccion_estado === VentaCorreccionEstadoEnum::SOLICITADA) {
            return ['class' => 'blink-danger'];
        }
        return [];
    }) */
        ->tooltip(fn (Venta $record): ?string => $record->correccion_motivo),
     Action::make('gestionar_correccion')
        ->label('Gestionar Corrección')
        ->icon('heroicon-o-pencil-square')
        ->color('success')
        // Visible solo si el estado es 'solicitada' y el usuario es admin/coordinador
        ->visible(fn (Venta $record): bool => 
            $record->correccion_estado === VentaCorreccionEstadoEnum::SOLICITADA &&
            auth()->user()->hasAnyRole(['super_admin', 'coordinador'])
        )
         ->extraAttributes(function (Venta $record): array {
        if ($record->correccion_estado === VentaCorreccionEstadoEnum::SOLICITADA) {
            return ['class' => 'blink-danger'];
        }
        return [];
    })
        // Al hacer clic, simplemente redirige a la página de edición normal
        ->url(fn (Venta $record): string => VentaResource::getUrl('edit', ['record' => $record]))
          ->openUrlInNewTab(),


            ])->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                     ExportBulkAction::make('exportar_completo')
        ->label('Exportar seleccionados')
        ->exports([
            ExcelExport::make('ventas')
                //->fromTable() // usa los registros seleccionados
                ->withColumns([
                   // Columnas ya existentes
                                Column::make('id')
                                    ->heading('ID Venta'), // Etiqueta más clara
                                Column::make('cliente.razon_social')
                                    ->heading('Cliente'),
                                Column::make('lead.id') // Usar lead.nombre para el nombre del Lead
                                    ->heading('Lead Asociado')
                                    ->formatStateUsing(fn ($state, $record) => $record->lead ? $record->lead->nombre : ''), // Asegura que solo muestre el nombre si existe
                                Column::make('comercial.full_name')
                                    ->heading('Vendido por'),
                                
                                Column::make('fecha_venta')
                                    ->heading('Fecha de venta')
                                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('d/m/Y H:i')), // Formato para Excel
                                
                             // IMPORTE RECURRENTE (USANDO LA LÓGICA DE LA COLUMNA DE LA TABLA)
                                    Column::make('importe_recurrente')
                                        ->heading('Importe Recurrente')
                                        // <<< CAMBIO AQUI: Usar la cadena literal 'recurrente'
                                        ->getStateUsing(function (Venta $record): float {
                                            $totalRec = VentaItem::query()
                                                ->where('venta_id', $record->id)
                                                ->whereHas('servicio', fn (Builder $q) => $q->where('tipo', 'recurrente'))
                                                ->sum('subtotal_aplicado'); // Suma subtotal_aplicado para el valor con descuento
                                            return (float) $totalRec;
                                        })
                                        ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €'),
                                    
                                    // IMPORTE ÚNICO (USANDO LA LÓGICA DE LA COLUMNA DE LA TABLA)
                                    Column::make('importe_unico')
                                        ->heading('Importe Único')
                                        // <<< CAMBIO AQUI: Usar la cadena literal 'unico'
                                        ->getStateUsing(function (Venta $record): float {
                                            $totalUnico = VentaItem::query()
                                                ->where('venta_id', $record->id)
                                                ->whereHas('servicio', fn (Builder $q) => $q->where('tipo', 'unico'))
                                                ->sum('subtotal_aplicado'); // Suma subtotal_aplicado para el valor con descuento
                                            return (float) $totalUnico;
                                        })
                                        ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €'),
                                    
                                    
                                
                                // DESCUENTO MENSUAL RECURRENTE (CORRECCIÓN EN formatStateUsing)
                                Column::make('descuento_mensual_recurrente_total')
                                    ->heading('Descuento Mensual Rec.')
                                    ->formatStateUsing(function ($state, $record) {
                                        if ((float)$state > 0) {
                                            $duracionTexto = '';
                                            // <<< CORRECCIÓN AQUI: Acceso al valor del Enum directamente como cadena
                                            $recurrente_value = 'recurrente'; // Definir la cadena literal aquí
                                            foreach ($record->items as $item) {
                                                $item->loadMissing('servicio');
                                                if ($item->servicio && $item->servicio->tipo->value === $recurrente_value && !empty($item->descuento_duracion_meses) && (float)$item->descuento_valor > 0) {
                                                    $duracionTexto = " ({$item->descuento_duracion_meses} meses)";
                                                    break;
                                                }
                                            }
                                            return '-' . number_format($state, 2, ',', '.') . ' €/mes' . $duracionTexto;
                                        }
                                        return 'Sin Dto.';
                                    }),
                                
                                // DESCUENTO ÚNICO (CORRECCIÓN EN formatStateUsing)
                                Column::make('descuento_unico_total')
                                    ->heading('Descuento Único')
                                    ->formatStateUsing(fn ($state) => ((float)$state > 0) ? '-' . number_format($state, 2, ',', '.') . ' €' : 'Sin Dto.'),
                                // FIN AÑADIDO

                                // Importe Total (asumo que este es el total final con IVA)
                                Column::make('importe_total')
                                    ->heading('Importe Total Final') // Etiqueta más clara
                                    ->formatStateUsing(fn ($state) => number_format($state, 2, ',', '.') . ' €'),
                                
                                Column::make('observaciones')
                                    ->heading('Observaciones'),
                                Column::make('created_at')
                                    ->heading('Creado en App')
                                    ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('d/m/Y H:i')),
                                Column::make('updated_at')
                                    ->heading('Actualizado en App')
                ]),
        ])
        ->icon('icon-excel2')
        ->color('success')
        ->deselectRecordsAfterCompletion()
        ->requiresConfirmation()
        ->modalHeading('Exportar Ventas Seleccionadas')
        ->modalDescription('Exportarás todos los datos de las Ventas seleccionadas.'),
                ]),
            ]);
    }

public static function infolist(Schema $schema): Schema
{
    return $schema
        ->components([
            // --- BLOQUE 1: Información General y Contexto ---
            Grid::make(3)->schema([
                // Columna izquierda
                Section::make('Detalles de la Venta')
                    ->columnSpan(2)
                    ->columns(2)
                    ->schema([
                        TextEntry::make('cliente.razon_social')->label('Cliente')
                            ->url(fn (Venta $record) => ClienteResource::getUrl('view', ['record' => $record->cliente_id]))->openUrlInNewTab()->icon('heroicon-m-user-circle')->color('primary')->weight('semibold')->columnSpanFull(),
                        TextEntry::make('fecha_venta')->label('Fecha de Venta')
                            ->icon('heroicon-m-calendar-days')->dateTime('d/m/Y H:i'),
                        TextEntry::make('observaciones')->label('Observaciones')
                            ->placeholder('Sin observaciones.')->columnSpanFull(),
                    ]),

                // Columna derecha
                Section::make('Contexto y Estado')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('comercial.name')->label('Comercial')->badge(),
                        TextEntry::make('lead.nombre')->label('Lead de Origen')
                            ->placeholder('Venta directa.')->url(fn (Venta $record) => $record->lead_id ? LeadResource::getUrl('view', ['record' => $record->lead_id]) : null)->openUrlInNewTab()->icon('heroicon-m-link'),
                        TextEntry::make('lead.procedencia.procedencia')->label('Procedencia del Lead')
                            ->badge()->color('gray'),
                        TextEntry::make('estado_general')->label('Estado General')->badge()
                            ->state(function (Venta $record): string {
                                if ($record->suscripciones()->where('estado', ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION)->exists()) {
                                    return 'Pendiente de Activación';
                                }
                                if ($record->suscripciones()->where('estado', ClienteSuscripcionEstadoEnum::ACTIVA)->exists()) {
                                    return 'Activa';
                                }
                                return 'Finalizada';
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'Pendiente de Activación' => 'warning',
                                'Activa' => 'success',
                                default => 'gray',
                            }),
                    ]),
            ]),
            
            // --- BLOQUE 2: Resumen Económico ---
    Section::make('Resumen Económico')
                        ->columns(2)
                        ->schema([
                            Grid::make(2)->schema([
                                TextEntry::make('importe_base_sin_descuento')
                                    ->label('Importe Original (Base)')->money('EUR')
                                    ->helperText('Coste real de los servicios sin descuentos.')
                                    ->state(fn (Venta $record): float => $record->items->sum('subtotal')),
                                
                                TextEntry::make('descuento_servicios_unicos')
                                    ->label('Dto. Servicios Únicos')->money('EUR')->color('danger')
                                    ->state(function (Venta $record): float {
                                        return $record->items
                                            ->where('servicio.tipo', ServicioTipoEnum::UNICO)
                                            ->sum(fn ($item) => ($item->cantidad * $item->precio_unitario) - $item->subtotal_aplicado);
                                    }),
                                
                                TextEntry::make('importe_total')
                                    ->label('Importe Final (Base)')->money('EUR')
                                    ->helperText('Final sin Impuestos con descuentos aplicados.')
                                    ->weight('bold'),
                                
                                // ... (Entry de ahorro recurrente igual que antes) ...
                                TextEntry::make('ahorro_total_recurrente')
                                    ->label('Ahorro Total Recurrente')->money('EUR')->color('danger')->weight('bold')
                                    ->state(fn (Venta $record) => round($record->items->where('servicio.tipo', ServicioTipoEnum::RECURRENTE)->sum(fn ($item) => (($item->cantidad * $item->precio_unitario) - $item->subtotal_aplicado) * ($item->descuento_duracion_meses ?? 1)), 2)),
                            ])->columnSpan(1),
                            
                            Grid::make(1)->schema([
                                // 🔥 CORRECCIÓN AQUI: Total con IVA Dinámico
                                TextEntry::make('importe_total_con_iva')
                                    ->label('Total a Facturar (IVA/IGIC incl.)')
                                    ->money('EUR')->weight('extrabold')->size('lg')->color('success')
                                    ->state(function (Venta $record) {
                                        // Detectamos impuesto según el cliente de la venta
                                        $porcentaje = Cliente::getPorcentajeImpuesto(
                                            $record->cliente?->codigo_postal, 
                                            $record->cliente?->provincia
                                        );
                                        // Calculamos
                                        return round($record->importe_total * (1 + ($porcentaje / 100)), 2);
                                    })
                                    ->helperText(fn (Venta $record) => 
                                        "Calculado con " . Cliente::getPorcentajeImpuesto($record->cliente?->codigo_postal, $record->cliente?->provincia) . "% de impuestos."
                                    ),
                            ])->columnSpan(1),
                        ]),
            
            // --- BLOQUE 3: Desglose de Servicios Vendidos ---
            Section::make('Desglose de Servicios Vendidos')
                ->schema([
                    RepeatableEntry::make('items')->label(false)->contained(false)
                        ->schema([
                            Grid::make(12)->schema([
                                TextEntry::make('nombre_final')
                                    ->label(false)
                                    ->columnSpan(5)
                                    ->html()
                                    ->formatStateUsing(function ($state, VentaItem $record): HtmlString {
                                        $nombreServicioHtml = e($state);
                                        if ($record->proyecto) {
                                            $url = ProyectoResource::getUrl('view', ['record' => $record->proyecto]);
                                            $icon = Blade::render("<x-heroicon-s-briefcase class='h-5 w-5 text-primary-600 mr-2' />");
                                            $nombreServicioHtml = "<a href='{$url}' target='_blank' class='text-primary-600 hover:underline font-semibold flex items-center'>{$icon}" . e($state) . "</a>";
                                        }
                                        $precioOriginal = number_format($record->precio_unitario, 2, ',', '.');
                                        $textoPVP = "PVP: {$precioOriginal} €";
                                        if ($record->servicio->tipo === ServicioTipoEnum::RECURRENTE) {
                                            $periodicidad = $record->servicio->ciclo_facturacion?->value ?? '';
                                            if($periodicidad) $textoPVP .= " ({$periodicidad})";
                                        }
                                        $precioHtml = "<div class='text-xs text-gray-500'>{$textoPVP}</div>";
                                        return new HtmlString("<div>{$nombreServicioHtml}{$precioHtml}</div>");
                                    }),
                                TextEntry::make('estado_del_item')->label(false)->alignEnd()->columnSpan(3)->badge()->placeholder('---')
                                    ->state(function (VentaItem $record) {
                                        if ($record->proyecto) return $record->proyecto->estado;
                                        if ($record->servicio->tipo === ServicioTipoEnum::RECURRENTE) return $record->suscripcion?->estado;
                                        return null;
                                    })
                                    ->color(fn ($state): string => match ($state) {
                                        ProyectoEstadoEnum::Pendiente => 'warning', ProyectoEstadoEnum::EnProgreso => 'primary',
                                        ProyectoEstadoEnum::Finalizado => 'success', ProyectoEstadoEnum::Cancelado => 'danger',
                                        ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION => 'warning',
                                        ClienteSuscripcionEstadoEnum::ACTIVA => 'success',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn ($state) => $state?->getLabel() ?? ''),
                                TextEntry::make('descuento_info')->label(false)->alignEnd()->color('danger')->weight('semibold')->columnSpan(2)
                                    ->state(function (VentaItem $record): string {
                                        if (!$record->descuento_tipo) return '---';
                                        $valor = number_format($record->descuento_valor, 2, ',', '.');
                                        $texto = $record->descuento_tipo === 'porcentaje' ? "-{$valor}%" : "-{$valor} €";
                                        if ($record->descuento_duracion_meses) $texto .= " ({$record->descuento_duracion_meses} meses)";
                                        return $texto;
                                    }),
                                TextEntry::make('subtotal_aplicado')->label(false)->money('EUR')->weight('bold')->alignEnd()->columnSpan(2),
                            ])
                        ])
                ]),
                
            // --- BLOQUE 4: Detalles de la Corrección (NUEVO) ---
            Section::make('Detalles de la Corrección')
                ->heading('Gestión de la Corrección - ¡Atención!') // <-- AÑADE ESTA LÍNEA
                ->description('Si esta venta tiene una corrección solicitada, aquí encontrarás los detalles y podrás gestionarla. Una corrección de la venta modifica el estado de suscripciones del cliente y las facturas que estvieran emitidas, generaidno rectificativa y nueva factura')
                ->icon('heroicon-o-exclamation-triangle')
                //->color('warning')
                ->visible(fn (Venta $record): bool => !is_null($record->correccion_estado))
                ->schema([
                    TextEntry::make('correccion_estado')
                        ->label('Estado')
                        ->badge(),
                    TextEntry::make('solicitanteCorreccion.name')
                        ->label('Solicitado por'),
                    TextEntry::make('correccion_solicitada_at')
                        ->label('Fecha Solicitud')
                        ->dateTime('d/m/Y H:i'),
                    TextEntry::make('correccion_motivo')
                        ->label('Motivo de la Solicitud')
                        ->columnSpanFull(),
                ])
                ->columns(3),
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
            'index' => ListVentas::route('/'),
            'create' => CreateVenta::route('/create'),
            'view' => ViewVenta::route('/{record}'), 
            'edit' => EditVenta::route('/{record}/edit'),
        ];
    }
}
