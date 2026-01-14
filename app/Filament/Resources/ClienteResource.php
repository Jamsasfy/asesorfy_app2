<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;

use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;
use Carbon\Carbon;
use App\Filament\Resources\Clienteresource\RelationManagers\ComentariosRelationManager;
use App\Filament\Resources\Clienteresource\RelationManagers\DocumentosRelationManager;
use App\Filament\Resources\Clienteresource\RelationManagers\UsuariosRelationManager;
use App\Filament\Resources\ClienteResource\RelationManagers\LeadsRelationManager;
use App\Filament\Resources\ClienteResource\RelationManagers\SuscripcionesRelationManager;
use App\Filament\Resources\ClienteResource\Pages\ListClientes;
use App\Filament\Resources\ClienteResource\Pages\CreateCliente;
use App\Filament\Resources\ClienteResource\Pages\ViewCliente;
use App\Filament\Resources\ClienteResource\Pages\EditCliente;
use App\Filament\Resources\ClienteResource\Pages;
use App\Filament\Resources\ClienteResource\RelationManagers;
use App\Models\Cliente;
use App\Models\User;
use App\Rules\NombreOrazonSocial;
use App\Rules\ValidIban;
//use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Actions\Action as ActionInfolist;
use App\Filament\Resources\VentaResource;
use Filament\Forms\Components\Hidden;
use Illuminate\Support\HtmlString;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use App\Enums\ClienteEstadoEnum;
use App\Filament\Resources\ClienteResource\RelationManagers\FacturasRelationManager;
use App\Mail\ContractCopyMail;
use App\Models\Lead;

use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Illuminate\Validation\Rule;
use Filament\Infolists\Components\Grid;





class ClienteResource extends Resource 
{
// 1. EL MODELO (Siempre ?string)
    protected static ?string $model = Cliente::class;

    // 2. EL ICONO (El error anterior pedía BackedEnum)
    protected static string | \BackedEnum | null $navigationIcon = 'icon-customer';

    // 3. EL GRUPO (El error ACTUAL pide UnitEnum)
    protected static string | \UnitEnum | null $navigationGroup = 'Usuarios plataforma';

    // 4. ETIQUETAS (Suelen ser ?string)
    protected static ?string $navigationLabel = 'Clientes AsesorFy';
    protected static ?string $modelLabel = 'Cliente AsesorFy';
    protected static ?string $pluralModelLabel = 'Clientes AsesorFy';


  
   

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }
      /*  // *** IMPORTANTE: Este método controla si el recurso aparece en la navegación y es accesible ***
       public static function canViewAny(): bool
       {
           // Permite que administradores, supervisores Y asesores lo vean en la navegación.
           return auth()->user()->hasRole('admin') || auth()->user()->hasRole('supervisor') || auth()->user()->hasRole('asesor');
       } */



public static function form(Schema $schema): Schema
{
    return $schema->schema([

        Hidden::make('comercial_id')
            ->default(fn () => auth()->id()),

        /* =====================================================
         | FILA 1 — NO TOCAR (solo se añade CCC)
         ===================================================== */
        Group::make()
            ->columns(2)
            ->columnSpanFull()
            ->schema([

               Section::make('Datos básicos del cliente')
    ->description('Información general para identificar y clasificar al cliente.')
    ->schema([
        Select::make('tipo_cliente_id')
            ->label('Tipo de cliente')
            ->relationship('tipoCliente', 'nombre')
            ->required()
            ->live(),

                TextInput::make('dni_cif')
                ->required()
                ->label(fn ($get) =>
                    (int) $get('tipo_cliente_id') === 1
                        ? 'DNI / NIE'
                        : 'CIF'
                )
                ->rules([
                    fn ($record) => Rule::unique('clientes', 'dni_cif')
                        ->ignore($record?->id),
                ]),

        TextInput::make('nombre')
            ->required()
            ->label(fn ($get) =>
                (int) $get('tipo_cliente_id') === 1
                    ? 'Nombre'
                    : 'Nombre (administrador)'
            )
            ->live()
            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                if ((int) $get('tipo_cliente_id') === 1) {
                    $apellidos = $get('apellidos');
                    $set('razon_social', trim($state . ' ' . $apellidos));
                }
            }),

        TextInput::make('apellidos')
            ->required()
            ->label(fn ($get) =>
                (int) $get('tipo_cliente_id') === 1
                    ? 'Apellidos'
                    : 'Apellidos (administrador)'
            )
            ->live()
            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                if ((int) $get('tipo_cliente_id') === 1) {
                    $nombre = $get('nombre');
                    $set('razon_social', trim($nombre . ' ' . $state));
                }
            }),

        TextInput::make('razon_social')
            ->required()
            ->label(fn ($get) =>
                (int) $get('tipo_cliente_id') === 1
                    ? 'Razón social'
                    : 'Razón social (denominación)'
            )
            ->disabled(fn ($get) => (int) $get('tipo_cliente_id') === 1)
            ->dehydrated(),

        TextInput::make('ccc')
            ->label('CCC'),

        TextInput::make('nombre_comercial')
            ->required()
            ->label('Nombre comercial'),
    ])
    ->columns(2),


                Section::make('Datos de contacto')
                    ->description('Teléfono, email y dirección fiscal del cliente.')
                    ->schema([
                        TextInput::make('email_contacto')
                            ->label('Email')
                            ->email()
                            ->required(),

                        TextInput::make('telefono_contacto')
                            ->label('Teléfono')
                            ->required(),

                        Textarea::make('direccion')
                            ->label('Dirección')
                            ->required()
                            ->columnSpanFull(),

                        TextInput::make('codigo_postal')
                            ->label('Código postal')
                            ->required(),

                        TextInput::make('localidad')
                            ->label('Localidad')
                            ->required(),

                        Select::make('provincia')
                            ->label('Provincia')
                            ->options(array_combine(
                                array_keys(config('provincias.provincias')),
                                array_keys(config('provincias.provincias'))
                            ))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn ($state, $set) =>
                                $set('comunidad_autonoma', config('provincias.provincias')[$state] ?? null)
                            ),

                        TextInput::make('comunidad_autonoma')
                            ->label('Comunidad Autónoma')
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(3),
            ]),

        /* =====================================================
         | FILA 2 — AJUSTADA
         ===================================================== */
        Group::make()
            ->columns(3)
            ->columnSpanFull()
            ->schema([

                /* ---------- Datos bancarios (2 columnas) ---------- */
                Section::make('Datos bancarios')
                    ->schema([
                        TextInput::make('iban_asesorfy')
                            ->label('IBAN AsesorFy')
                            ->rules(['nullable', new ValidIban])
                            ->required(fn ($get) => $get('estado') === ClienteEstadoEnum::ACTIVO->value),

                        TextInput::make('iban_impuestos')
                            ->label('IBAN impuestos')
                            ->placeholder('Para impuestos y Seguridad Social')
                            ->rules(['nullable', new ValidIban])
                            ->required(fn () => request()->routeIs('filament.*.edit')),
                    ])
                    ->columns(2),

                /* ---------- Asignación interna (más compacta) ---------- */
                Section::make('Asignación interna')
                    ->schema([
                        Select::make('asesor_id')
                            ->label('Asesor')
                            ->relationship(
                                'asesor',
                                'name',
                                fn ($query) =>
                                    $query->whereHas('roles', fn ($q) => $q->where('name', 'asesor'))
                            )
                            ->searchable(),
                    ])
                    ->visibleOn('edit'),

                /* ---------- Estado y control ---------- */
                Section::make('Estado y control')
                    ->schema([
                        Group::make()
                            ->columns(3)
                            ->schema([
                                Select::make('estado')
                                    ->label('Estado')
                                    ->options(ClienteEstadoEnum::class)
                                    ->required()
                                    ->visibleOn('edit'),

                                DatePicker::make('fecha_alta')
                                    ->label('Fecha de alta')
                                    ->visibleOn('edit'),

                                DatePicker::make('fecha_baja')
                                    ->label('Fecha de baja')
                                    ->visibleOn('edit'),
                            ]),

                        Textarea::make('observaciones')
                            ->label('Observaciones')
                            ->columnSpanFull(),
                    ]),
            ]),
    ]);
}







public static function infolist(Schema $schema): Schema
{
    return $schema
        ->columns(3)
        ->schema([

            /* =========================================================
             | DATOS BÁSICOS
             ========================================================= */
            Section::make('Datos básicos del cliente')
                ->icon('heroicon-o-user')
                ->description(fn ($record) => new HtmlString(/* TU HTML TAL CUAL */))
                ->schema([

                    TextEntry::make('nombre')
                        ->label('Nombre')
                        ->inlineLabel()
                        ->copyable()
                        ->weight('bold')
                        ->color('primary'),

                    TextEntry::make('apellidos')
                        ->label('Apellidos')
                        ->inlineLabel()
                        ->copyable()
                        ->weight('bold')
                        ->color('primary'),

                    TextEntry::make('razon_social')
                        ->label('Razón social')
                        ->inlineLabel()
                        ->copyable()
                        ->weight('bold')
                        ->color('primary')
                        ->columnSpan(2),

                    TextEntry::make('nombre_comercial')
                        ->label('Nombre comercial')
                        ->inlineLabel()
                        ->copyable()
                        ->weight('bold')
                        ->color('primary')
                        ->placeholder('—')
                        ->columnSpan(2),

                    TextEntry::make('email_contacto')
                        ->label('Email')
                        ->inlineLabel()
                        ->copyable()
                        ->weight('bold')
                        ->color('primary')
                        ->columnSpan(2),

                    TextEntry::make('telefono_contacto')
                        ->label('TFN')
                        ->inlineLabel()
                        ->copyable()
                        ->color('warning'),
                ])
                ->columns(2),

            /* =========================================================
             | DIRECCIÓN
             ========================================================= */
            Section::make('Dirección del cliente')
                ->icon('heroicon-m-map-pin')
                ->schema([

                    TextEntry::make('direccion_completa')
                        ->label('Dirección')
                        ->state(fn ($record) => implode(' · ', array_filter([
                            $record->direccion,
                            trim(($record->localidad ?? '') . ($record->provincia ? " ({$record->provincia})" : '')),
                            $record->codigo_postal,
                            $record->comunidad_autonoma,
                        ])))
                        ->copyable()
                        ->weight('bold')
                        ->color('primary')
                        ->columnSpan(3),

                    TextEntry::make('iban_impuestos')
                        ->label('IBAN impuestos (Hacienda / SS)')
                        ->copyable()
                        ->placeholder('No informado')
                        ->color(fn ($state) => filled($state) ? 'primary' : 'warning')
                        ->columnSpan(3),
                ])
                ->columns(3),

            /* =========================================================
             | ESTADO Y ASIGNACIÓN
             ========================================================= */
            Section::make('Estado y asignación')
                ->icon('heroicon-o-shield-check')
                ->schema([

                    TextEntry::make('estado')
                        ->label('Estado')
                        ->badge()
                        ->weight('bold')
                        ->columnSpan(1),

                    TextEntry::make('asesor.name')
                        ->label('Asesor')
                        ->badge()
                        ->getStateUsing(fn ($record) =>
                            $record->asesor?->name ?? '⚠️ Sin asignar'
                        )
                        ->columnSpan(1),

                    TextEntry::make('tarifa_principal_activa_con_precio')
                        ->label('Tarifa')
                        ->badge()
                        ->columnSpan(1),

                    TextEntry::make('created_at')
                        ->label('Creado')
                        ->dateTime('d/m/Y H:i')
                        ->color('info'),

                    TextEntry::make('fecha_alta')
                        ->label('Alta servicio')
                        ->dateTime('d/m/Y H:i')
                        ->color('success'),

                    TextEntry::make('fecha_baja')
                        ->label('Baja servicio')
                        ->dateTime('d/m/Y H:i')
                        ->color('danger'),
                ])
                ->columns(3),

        ]);
}




    public static function table(Table $table): Table
    {
        return $table
        ->striped()
        ->recordUrl(null)   
        ->defaultSort('created_at', 'desc') // Ordenar por defecto
        ->columns([
             TextColumn::make('razon_social')
                ->label('Razón Social')
                ->searchable(isIndividual: true)
                ->sortable()
                ->formatStateUsing(fn ($state) => $state ?: '-'),

            TextColumn::make('dni_cif')
                ->label('DNI o CIF')
                ->searchable(isIndividual: true)
                ->sortable(),
              

             TextColumn::make('nombre')
                ->label('Nombre')
                ->searchable(isIndividual: true)
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('apellidos')
                ->label('Apellidos')
                ->searchable(isIndividual: true)
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),


            TextColumn::make('tipoCliente.nombre')
                ->label('Tipo')
                ->badge()
                ->sortable(),

            TextColumn::make('estado')
                ->label('Estado')
                ->badge()
                ->color(fn (ClienteEstadoEnum $state): string => match ($state) { // <-- CAMBIO AQUÍ
                    ClienteEstadoEnum::PENDIENTE, ClienteEstadoEnum::PENDIENTE_ASIGNACION => 'warning',
                    ClienteEstadoEnum::ACTIVO => 'success',
                    ClienteEstadoEnum::IMPAGADO, ClienteEstadoEnum::RESCINDIDO => 'danger',
                    ClienteEstadoEnum::REQUIERE_ATENCION => 'info',
                    default => 'gray',
                })
                ->sortable(),

              // Nueva columna para la Tarifa Principal Activa (nombre del servicio)
             // Columna Modificada para la Tarifa Principal Activa
           TextColumn::make('tarifa_principal_activa_con_precio')
                ->label('Tarifa Principal')
                ->placeholder('Ninguna')
                ->badge()
                ->color(function ($record): string {
                    // Si el cliente tiene una tarifa principal activa...
                    if ($record->tarifa_principal_activa) {
                        return 'success'; // ...el color es verde.
                    }
                    // Si no la tiene...
                    return 'warning'; // ...el color es naranja/amarillo.
                })
                ->tooltip(function ($record) {
                    if ($record->tarifa_principal_activa && $record->tarifa_principal_activa->servicio) {
                        return $record->tarifa_principal_activa->servicio->nombre;
                    }
                    return null;
                })
                ->searchable(false)
                ->sortable(false),

            TextColumn::make('provincia')
                ->label('Provincia')
                ->sortable(),

            TextColumn::make('localidad')
            ->toggleable(isToggledHiddenByDefault: true)
                ->sortable(),

            TextColumn::make('telefono_contacto')
                ->label('Teléfono')
                ->searchable(isIndividual: true),

            TextColumn::make('email_contacto')
                ->label('Email')
                ->searchable(isIndividual: true),

                TextColumn::make('asesor.name')
                ->label('Asesor')
                ->badge()
                ->getStateUsing(fn ($record) =>
                    $record->asesor
                        ? $record->asesor->name
                        : '⚠️ Sin asignar'
                )
                ->color(fn ($state) => str_contains($state, 'Sin asignar') ? 'warning' : 'success'),

            TextColumn::make('created_at')
                ->label('Creado en App')
                 ->toggleable(isToggledHiddenByDefault: true)
                ->dateTime('d/m/y - H:m')
               
                ->sortable(),

            TextColumn::make('fecha_alta')
                ->label('Fecha de Alta')
                 ->toggleable(isToggledHiddenByDefault: true)
                ->dateTime('d/m/y - H:m')
               
                ->sortable(),    
               
            TextColumn::make('fecha_baja')
                ->label('Fecha de Baja servicio')
                ->dateTime('d/m/y - H:m')
                ->toggleable(isToggledHiddenByDefault: true)
                ->sortable(),        
            ])
            ->recordUrl(null)
        ->filters([
            SelectFilter::make('estado')
                ->label('Estado')
                ->preload()
                ->options(ClienteEstadoEnum::class) // <-- CAMBIO AQUÍ
                ->searchable(),
            SelectFilter::make('tipo_cliente_id')
                ->label('Tipo de cliente')
                ->relationship('tipoCliente', 'nombre')
                ->preload()
                ->searchable(),

            SelectFilter::make('provincia')
                ->label('Provincia')
                ->options(array_keys(config('provincias.provincias')))
                ->searchable(),
                SelectFilter::make('asesor_id')
                ->label('Asesor asignado')
                ->options(
                    User::whereHas('roles', fn ($q) => $q->where('name', 'asesor'))
                        ->where('acceso_app', true)
                        ->get()
                        ->pluck('full_name', 'id')
                )
                ->searchable()
                ->preload(),
            DateRangeFilter::make('fecha_alta')
                ->label('Alta APP')
                ->placeholder('Rango de fechas a buscar'),  
            DateRangeFilter::make('fecha_baja')
                ->label('Baja APP')
                ->placeholder('Rango de fechas a buscar'),      
            Filter::make('sin_asesor')
                ->label('Sin asesor asignado')
                ->query(fn ($query) => $query->whereNull('asesor_id'))
                ->toggle(),      

                ],layout: FiltersLayout::AboveContent)
                ->filtersFormColumns(7)
        ->recordActions([
            ViewAction::make()
            ->label('')
            ->tooltip('Ver cliente'),
            EditAction::make()
            ->label('')
            ->tooltip('Editar cliente'),
            Action::make('cambiarAsesor')
            
                ->label('')
                ->tooltip('Cambiar asesor del cliente')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn ($record) =>
                !empty($record->asesor_id) &&
                auth()->user()?->can('CambiarAsesor:Cliente')
            )
                ->schema([
                    Select::make('asesor_id')
                        ->label('Selecciona nuevo asesor')
                        ->options(
                            User::whereHas('roles', fn ($q) => $q->where('name', 'asesor'))
                                ->where('acceso_app', true)
                                ->pluck('name', 'id')
                        )
                        ->searchable()
                        ->required(),
                ])
                ->action(function ($record, array $data) {
                    $record->asesor_id = $data['asesor_id'];
                    $record->save();

                    Notification::make()
                        ->title('🔄 Asesor actualizado')
                        ->body('El asesor del cliente ha sido cambiado correctamente.')
                        ->success()
                        ->send();
                })
                ->modalHeading('Cambiar asesor del cliente')
                ->modalSubmitActionLabel('Actualizar'),
                
            Action::make('quitarAsesor')
                ->label('')
                ->tooltip('Quitar asesor del cliente')
                ->icon('heroicon-o-user-minus')
                ->color('danger')
                ->visible(fn ($record) =>
                !empty($record->asesor_id) &&
                auth()->user()?->hasPermissionTo('QuitarAsesor:Cliente')
                )
                ->requiresConfirmation()
                ->modalHeading('¿Seguro que quieres quitar el asesor?')
                ->modalDescription('El cliente quedará sin asesor asignado.')
                ->modalSubmitActionLabel('Sí, quitar asesor')
                ->action(function ($record) {
                    $record->asesor_id = null;
                    $record->save();
            
                    Notification::make()
                        ->title('🗑️ Asesor eliminado')
                        ->body('El asesor ha sido desvinculado del cliente correctamente.')
                        ->danger()
                        ->send();
                }),
                
            Action::make('asignarAsesor')
                ->label('')
                ->tooltip('Asignar asesor al cliente')
                ->icon('heroicon-o-user-plus')
                ->color('warning')
                ->visible(fn ($record) =>
                empty($record->asesor_id) &&
                auth()->user()?->hasPermissionTo('AsignarAsesor:Cliente')
                )
                ->schema([
                    Select::make('asesor_id')
                        ->label('Selecciona asesor')
                        ->options(
                            User::whereHas('roles', fn ($q) => $q->where('name', 'asesor'))
                                ->where('acceso_app', true)
                                ->pluck('name', 'id')
                        )
                        ->required()
                        ->searchable()
                        ->preload(),
                ])
                ->modalHeading('Asignar asesor al cliente')
                ->modalSubmitActionLabel('Asignar')
                ->action(function ($record, array $data) {
                    $record->asesor_id = $data['asesor_id'];
                    $record->save();
            
                    Notification::make()
                        ->title('✅ Asesor asignado')
                        ->body('El asesor ha sido asignado correctamente al cliente.')
                        ->success()
                        ->send();
                }),   
              
        ])
        ->toolbarActions([
          

//grupo de asignaciones masivas
            BulkActionGroup::make([
               
                BulkAction::make('asignar_asesor')
                ->icon('heroicon-o-user-group')
                ->label('Asignación masiva asesor')
                ->schema([
                    Select::make('asesor_id')
                        ->label('Seleccionar asesor')
                        ->options(
                            User::where('acceso_app', true)
                            ->whereHas('roles', fn ($q) => $q->where('name', 'asesor'))
                            ->pluck('name', 'id')
                        )
                        ->required()
                        ->preload()
                        ->searchable(),
                ])
                ->action(function (array $data, EloquentCollection $records) {
                    $asesorId = $data['asesor_id'];
                    $ids = $records->pluck('id')->toArray();
    
                    Cliente::whereIn('id', $ids)->update(['asesor_id' => $asesorId]);
                })
                ->requiresConfirmation()
                ->modalHeading('Asignar asesor a los clientes seleccionados')
                ->modalDescription('Estás a punto de asignar masivamente un asesor. ¿Estás seguro?')
                ->modalSubmitActionLabel('Sí, asignar todos')
                ->modalIcon('heroicon-o-user-group')
                ->color('warning')
                ->deselectRecordsAfterCompletion(),

                BulkAction::make('quitarAsesor')
                    ->label('Quitar asesor masivo')
                    ->icon('heroicon-m-user-minus')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Quitar asesor')
                    ->modalDescription('Esto quitará el asesor asignado a los clientes seleccionados. ¿Seguro?')
                    ->modalSubmitActionLabel('Quitar asesor')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (\Illuminate\Support\Collection $records) {
                        foreach ($records as $record) {
                            if (!is_null($record->asesor_id)) {
                                $record->update(['asesor_id' => null]);
                            }
                        }

                        Notification::make()
                            ->title('✅ Asesores eliminados')
                            ->body('Los asesores fueron eliminados correctamente de los clientes seleccionados.')
                            ->success()
                            ->send();
                    }),
                    
                BulkAction::make('cambiarAsesor')
                    ->label('Cambiar asesor')
                    ->icon('heroicon-m-arrow-path-rounded-square')
                    ->color('primary')
                    ->schema([
                        Select::make('asesor_id')
                            ->label('Selecciona nuevo asesor')
                            ->options(
                                User::whereHas('roles', fn ($q) => $q->where('name', 'asesor'))
                                    ->where('acceso_app', true)
                                    ->pluck('name', 'id')
                            )
                            ->required()
                            ->searchable()
                            ->preload(),
                    ])
                    ->modalHeading('Cambiar asesor')
                    ->modalDescription('Este cambio afectará a todos los clientes seleccionados. ¿Deseas continuar?')
                    ->modalSubmitActionLabel('Cambiar asesor')
                    ->requiresConfirmation()
                    ->deselectRecordsAfterCompletion()
                    ->action(function (array $data, \Illuminate\Support\Collection $records) {
                        foreach ($records as $record) {
                            $record->update(['asesor_id' => $data['asesor_id']]);
                        }
                
                        Notification::make()
                            ->title('✅ Asesores actualizados')
                            ->body('Se ha actualizado el asesor de los clientes seleccionados correctamente.')
                            ->success()
                            ->send();
                    }),
            ]) ->label('🧑‍💼 Gestión de Asesores')
            ->visible(fn () => auth()->user()?->hasPermissionTo('AsignacionMasivaAsesor:Cliente')), // 👈 Aplica a todo el grupo


           
            BulkActionGroup::make([
                DeleteBulkAction::make(),
                ExportBulkAction::make('exportar_completo')
        ->label('Exportar seleccionados')
        ->exports([
            ExcelExport::make('clientes')
                //->fromTable() // usa los registros seleccionados
                ->withColumns([
                    Column::make('id'),
                    Column::make('tipocliente.nombre')
                       ->heading('Tipo cliente'),
                    Column::make('razon_social')
                        ->heading('Razón Social'),
                    Column::make('dni_cif')
                        ->heading('DNI o CIF'),
                    Column::make('email_contacto')
                        ->heading('Email'),
                    Column::make('telefono_contacto')
                        ->heading('Teléfono'),
                    Column::make('direccion')
                        ->heading('Dirección'),
                    Column::make('codigo_postal')
                        ->heading('Código Postal'),
                    Column::make('localidad')
                        ->heading('Localidad'),
                    Column::make('provincia')
                        ->heading('Provincia'),
                    Column::make('comunidad_autonoma')
                        ->heading('Comunidad Autónoma'),
                    Column::make('estado')
                        ->heading('Estado'),
                    Column::make('fecha_alta')
                        ->heading('Fecha de Alta')
                        ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('d/m/Y - H:i')),

                    Column::make('fecha_baja')
                        ->heading('Fecha de Baja')
                        ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('d/m/Y - H:i')),

                    Column::make('iban_asesorfy')
                        ->heading('IBAN AsesorFy'),
                    Column::make('iban_impuestos')
                        ->heading('IBAN Impuestos'),
                    Column::make('ccc')
                        ->heading('CCC'),
                    Column::make('asesor.name')
                        ->heading('Asesor')
                        ->getStateUsing(fn ($record) =>
                            $record->asesor
                                ? $record->asesor->name
                                : '⚠️ Sin asignar'
                        ),
                   Column::make('coordinador') // Usamos un nombre genérico
                    ->heading('Coordinador')
                    ->getStateUsing(function (Cliente $record): string {
                        // Seguimos la cadena de relaciones para encontrar el nombre del coordinador
                        $coordinadorName = $record->asesor?->trabajador?->departamento?->coordinador?->name;

                        // Si lo encontramos, lo devolvemos. Si no, 'Sin asignar'.
                        return $coordinadorName ?? '⚠️ Sin asignar';
                    }),
                    Column::make('observaciones')
                        ->heading('Observaciones'),
                    Column::make('created_at')
                        ->heading('Creado en App')
                        ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('d/m/Y - H:i')),
                    Column::make('updated_at')
                        ->heading('Actualizado en App')
                        ->formatStateUsing(fn ($state) => Carbon::parse($state)->format('d/m/Y - H:i')),

                        
                ]),
        ])
        ->icon('icon-excel2')
        ->color('success')
        ->deselectRecordsAfterCompletion()
        ->requiresConfirmation()
        ->modalHeading('Exportar clientes')
        ->modalDescription('Exportarás todos los datos de clientes seleccionados.'),
               
            ])->label('Otras acciones'),
        ]);
    }

    public static function getRelations(): array
{
    return [
        ComentariosRelationManager::class,
       DocumentosRelationManager::class,
              RelationManagers\DocumentosVinculadosRelationManager::class,

       UsuariosRelationManager::class,
       LeadsRelationManager::class,
        SuscripcionesRelationManager::class,
FacturasRelationManager::class,
         
      
        // Puedes añadir más relation managers aquí si es necesario
    ];
}
//RelationManagers\PostsRelationManager::class,
    public static function getPages(): array
    {
        return [
            'index' => ListClientes::route('/'),
            'create' => CreateCliente::route('/create'),
            'view'   => ViewCliente::route('/{record}'),     // ← esta línea
            'edit' => EditCliente::route('/{record}/edit'),
       

        ];
    }
}
