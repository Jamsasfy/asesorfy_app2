<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
//use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;
use Filament\Schemas\Components\Grid;
use App\Filament\Resources\ProyectoResource\RelationManagers\DocumentosRelationManager;
use App\Filament\Resources\ProyectoResource\Pages\ListProyectos;
use App\Filament\Resources\ProyectoResource\Pages\EditProyecto;
use App\Filament\Resources\ProyectoResource\Pages\ViewProyecto;
use Exception;
use App\Models\User;
use App\Filament\Resources\ProyectoResource\Pages;
use App\Filament\Resources\ProyectoResource\RelationManagers;
use App\Models\Proyecto;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Select; // Importa Select
use Filament\Forms\Components\TextInput; // Importa TextInput
use Filament\Forms\Components\Textarea; // Importa Textarea
use Filament\Forms\Components\DatePicker; // Importa DatePicker
use Filament\Forms\Components\DateTimePicker; // Importa Section
use Filament\Tables\Columns\TextColumn; // Importa TextColumn

use App\Enums\ProyectoEstadoEnum; // Si usas el Enum para estados
use Carbon\Carbon;
use Filament\Forms\Components\Placeholder;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Infolists\Components\RepeatableEntry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString; // <<< ASEGÚRATE DE QUE ESTA LÍNEA ESTÉ AQUÍ
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Log; // Para Log::error
use Filament\Forms\Components\Toggle; // Para el Toggle en los formularios de las acciones
use Filament\Infolists\Components\ViewEntry;
use App\Enums\ServicioTipoEnum;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use App\Enums\ClienteSuscripcionEstadoEnum;
use Filament\Tables\Enums\RecordActionsPosition;


use Livewire\Component as LivewireComponent;





class ProyectoResource extends Resource
{
    protected static ?string $model = Proyecto::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-briefcase'; // Icono de maletín
    protected static string | \UnitEnum | null $navigationGroup = null; // Nuevo grupo de navegación
    protected static ?string $modelLabel = 'Proyecto';
    protected static ?string $pluralModelLabel = 'Proyectos';




     // ** Nuevo método para la agrupación dinámica **
    public static function getNavigationGroup(): ?string
    {
        // Si el usuario es un asesor, asigna el grupo "Mi espacio de trabajo"
        if (auth()->user()?->hasRole('asesor')) {
            return 'Mi espacio de trabajo';

        }

        // Si no es asesor (ej. super_admin), retorna null para que no se agrupe
        // o puedes devolver un nombre de grupo diferente para ellos si lo deseas.
        // Ejemplo: return 'Gestión General';
        return 'Gestión PROYECTOS';
    }
public static function getNavigationLabel(): string
    {
        return auth()->user()?->hasRole('asesor') ? 'Mis Proyectos' : 'Proyectos';
    }

     // ** NUEVO CAMBIO: Método para el contenido del badge **
    public static function getNavigationBadge(): ?string
    {
        // Reutilizamos la misma lógica de consulta que filtra por rol
        $query = static::getEloquentQuery();
        return (string) $query->count(); // Aseguramos que retorne un string
    }

    // ** NUEVO CAMBIO: Método para el color del badge **
    public static function getNavigationBadgeColor(): string|array|null
    {        
        return 'warning';
    }



  public static function getEloquentQuery(): Builder
    {
        /** @var User|null $user */
        $user = Auth::user();
        $query = parent::getEloquentQuery()                
                ->with(['cliente']); 
        
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // <<< CAMBIO AQUI: super_admin O coordinador ven todos los proyectos
        if ($user->hasRole('super_admin') || $user->hasRole('coordinador')) {
            return $query; // Super admin Y coordinador ven todos los registros
        }

        // <<< CAMBIO AQUI: 'asesor' solo ve los proyectos asignados a él
        if ($user->hasRole('asesor')) {
            return $query->where('user_id', $user->id); 
        }
        
        // Por defecto: cualquier otro rol o no autenticado no ve nada
        return $query->whereRaw('1 = 0');
    }



    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del Proyecto')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre del Proyecto')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        Select::make('cliente_id')
                            ->label('Cliente')
                            ->relationship('cliente', 'razon_social')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),

                        Select::make('user_id')
                            ->label('Asesor Asignado')
                            ->relationship('user', 'name', fn (Builder $query) => 
                                // Asume que solo los 'comercial' y 'super_admin' pueden ser asesores asignados a proyectos
                                $query->whereHas('roles', fn (Builder $q) => $q->whereIn('name', ['comercial', 'super_admin']))
                            )
                            ->searchable()
                            ->preload()
                            ->nullable() // Puede no estar asignado inicialmente
                            ->columnSpan(1),

                        Select::make('estado')
                            ->label('Estado')
                            ->options(ProyectoEstadoEnum::class) // Usa el Enum para las opciones
                            ->native(false) // Para una mejor UI en el Select
                            ->required()
                            ->default(ProyectoEstadoEnum::Pendiente->value) // Estado por defecto
                            ->columnSpan(1),

                        DateTimePicker::make('fecha_finalizacion')
                            ->label('Fecha de Finalización Real')
                            ->nullable()
                            ->native(false)
                            ->disabled(fn(Get $get) => $get('estado') !== ProyectoEstadoEnum::Finalizado->value) // Deshabilitado si no está finalizado
                            ->helperText('Se establece automáticamente al marcar el estado como "Finalizado".')
                            ->columnSpan(1),

                        // Campos opcionales para vincular a Venta/Servicio/VentaItem
                        Select::make('venta_id')
                            ->label('Venta de Origen')
                            ->relationship('venta', 'id') // Asume que ID es suficiente, o puedes usar un accesor
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->columnSpan(1),

                        Select::make('venta_item_id')
                            ->label('Item de Venta Recurrente')
                            ->relationship('ventaItem', 'id') // Asume que ID es suficiente
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Item de venta recurrente cuya suscripción se activa al finalizar este proyecto.')
                            ->columnSpan(1),

                        // Puedes añadir Select::make('servicio_id') si es necesario

                        // AÑADIDO: Campo agenda
                            DateTimePicker::make('agenda')
                                ->label('Próximo Seguimiento')
                                ->native(false)
                                ->nullable()
                                ->minutesStep(30) // O el intervalo que prefieras
                                ->columnSpan(1),

                        Textarea::make('descripcion')
                            ->label('Descripción del Proyecto')
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
       //  ->striped()
        ->recordUrl(null)   
        ->defaultSort('created_at', 'desc') // Ordenar por defecto
       
            ->columns([
                TextColumn::make('nombre')
                    ->label('Proyecto')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('cliente.razon_social')
                    ->label('Cliente')
                    // <<< CAMBIO AQUI: Convertir a enlace y colorear
                    ->url(fn (Proyecto $record): ?string => 
                        $record->cliente_id
                            ? ClienteResource::getUrl('view', ['record' => $record->cliente_id])
                            : null
                    )
                    ->color('warning') // Color amarillo para el enlace
                    ->openUrlInNewTab() // Abrir en nueva pestaña
                    // FIN CAMBIO AQUI
                    ->searchable()
                    ->sortable(),

               TextColumn::make('user.name')
                    ->label('Asesor')
                    ->searchable()
                    ->badge()
                    ->sortable()
                     // <<< CAMBIO CLAVE AQUI: Usar getStateUsing para controlar el valor base
                    ->getStateUsing(function (Proyecto $record): ?string {
                        // Si no hay user_id (null en DB), devuelve 'Sin asignar' como el estado
                        if (is_null($record->user_id)) {
                            return 'Sin asignar';
                        }
                        // Si hay user_id, devuelve el nombre del usuario
                        // Asegúrate de que la relación 'user' esté cargada si es necesaria
                        return $record->user->name ?? null; // Devuelve el nombre o null si la relación user es null por alguna razón
                    })
                    // Ahora, formatStateUsing ya no necesita la condición is_null($record->user_id)
                    // porque getStateUsing ya ha forzado 'Sin asignar' si es null.
                    ->formatStateUsing(function ($state): string {
                        // $state ya será 'Sin asignar' o el nombre del usuario
                        return $state;
                    })
                    // Color del badge: 'info' (azul) si asignado, 'warning' (amarillo) si no
                    ->color(function ($state): string {
                        // $state ya será 'Sin asignar' o el nombre del usuario
                        if ($state === 'Sin asignar') {
                            return 'warning'; // Amarillo para 'Sin asignar'
                        }
                        return 'info'; // Azul para el nombre del asesor
                    }),

                TextColumn::make('venta_id')
                    ->label('ID Venta')
                    ->url(fn (Proyecto $record): ?string => 
                        $record->venta_id ? VentaResource::getUrl('edit', ['record' => $record->venta_id]) : null
                    )
                    ->color(fn (Proyecto $record): string => $record->venta_id ? 'primary' : 'secondary')
                    ->openUrlInNewTab() // Abrir en nueva pestaña
                    ->searchable()
                    ->sortable(),
                
                // <<< AÑADIDO: ID de Item de Venta (para auditoría específica)
                TextColumn::make('venta_item_id')
                    ->label('ID Item Venta')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true), // Oculto por defecto
        // <<< AÑADIDO: Servicio Asociado (el que disparó el proyecto)
                TextColumn::make('servicio.nombre')
                    ->label('Servicio Activador')
                    ->searchable()
                    ->sortable(),

               TextColumn::make('estado')
                    ->label('Estado')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->estado?->value ?? $record->estado) // <--- esto saca el string del Enum
                    ->colors([
                        'primary' => 'pendiente',
                        'warning' => 'en_progreso',
                        'success' => 'finalizado',
                        'danger'  => 'cancelado',
                    ])
                    ->formatStateUsing(fn ($state) => ProyectoEstadoEnum::tryFrom($state)?->getLabel() ?? $state)
                    ->sortable(),
                      TextColumn::make('agenda')
                    ->label('Próx. Seguimiento')
                    ->dateTime('d/m/y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false) // Visible por defecto
                    ->placeholder('Sin agendar'), // Texto si es null
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                   
                TextColumn::make('fecha_finalizacion')
                    ->label('Finalizado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

            ])
            
            ->filters([
                // Filtro por Cliente
                  SelectFilter::make('servicio_id')
                ->label('Servicio Único') // He cambiado la etiqueta para más claridad
                ->relationship(
                    name: 'servicio', 
                    titleAttribute: 'nombre',
                    // ▼▼▼ AÑADIMOS ESTA CONDICIÓN ▼▼▼
                    modifyQueryUsing: fn (Builder $query) => $query->where('tipo', ServicioTipoEnum::UNICO)
                )
                ->searchable()
                ->preload(),
                SelectFilter::make('cliente_id')
                    ->relationship('cliente', 'dni_cif')
                    ->searchable()
                    ->preload()
                    ->label('Filtrar por Cliente'),
               
                // Filtro por Asesor Asignado
                SelectFilter::make('user_id')
                    ->relationship('user', 'name', fn (Builder $query) => 
                        $query->whereHas('roles', fn (Builder $q) => $q->whereIn('name', ['asesor', 'coordinador']))
                    )
                    ->searchable()
                    ->preload()
                    ->label('Filtrar por Asesor'),

                // Filtro por Estado del Proyecto
                SelectFilter::make('estado')
                    ->options(ProyectoEstadoEnum::class) // Usa el Enum para las opciones
                    ->native(false)
                    ->multiple()
                    ->label('Filtrar por Estado'),
                 DateRangeFilter::make('created_at')
                   
                    ->label('Fecha Creación'),
                // Filtro por Fecha de Finalización Real
                DateRangeFilter::make('fecha_finalizacion')                   
                    ->label('Fecha Finalización'),
                DateRangeFilter::make('agenda')                   
                    ->label('Próximo seguimiento')
                     ->ranges([
                    // --- PASADO ---
                    'Ayer' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
                    'Semana Pasada' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
                    'Mes Pasado' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
                    'Año Pasado' => [now()->subYear()->startOfYear(), now()->subYear()->endOfYear()],
            
                    // --- PRESENTE ---
                    'Hoy' => [now()->startOfDay(), now()->endOfDay()],
            
                    // --- PERIODOS ACTUALES (Incluyen presente y futuro cercano) ---
                    'Esta Semana' => [now()->startOfWeek(), now()->endOfWeek()],
                    'Este Mes' => [now()->startOfMonth(), now()->endOfMonth()],
                    'Este Año' => [now()->startOfYear(), now()->endOfYear()],
            
                    // --- FUTURO ---
                    'Próxima Semana' => [now()->addWeek()->startOfWeek(), now()->addWeek()->endOfWeek()],
                    'Próximo Mes' => [now()->addMonthNoOverflow()->startOfMonth(), now()->addMonthNoOverflow()->endOfMonth()],
                    'Próximo Año' => [now()->addYear()->startOfYear(), now()->addYear()->endOfYear()],
                ]),
                Filter::make('sin_asesor')
                ->label('Sin Asesor Asignado')
                ->query(fn (Builder $query): Builder => $query->whereNull('user_id'))
                ->toggle(),
                     
            ],layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(8)
         
            ->recordActions([
                ViewAction::make()
                ->label('')
                ->tooltip('Ver Proyecto')
                 ->openUrlInNewTab(),
                EditAction::make()
                ->label('')
                ->tooltip('Editar Proyecto')
                ->openUrlInNewTab(),  
                  // <<< AÑADIDO: Acción para Asignar Asesor
          Action::make('assign_assessor')
    ->label('')
    ->icon('heroicon-o-user-plus')
    ->color(fn (Proyecto $record): string => $record->user_id ? 'primary' : 'warning')
   ->visible(fn (Proyecto $record) => auth()->user()?->can('assignAssessor', $record))

    ->tooltip(fn (Proyecto $record): string =>
        $record->user_id ? 'Cambiar Asesor Asignado' : 'Asignar Asesor'
    )
    ->modalHeading('Asignar Asesor al Proyecto')
    ->modalSubmitActionLabel('Asignar')
    ->modalWidth('md')
    ->schema([

        /*----------------------------------------------
        | INFO ASESOR DEL CLIENTE
        ----------------------------------------------*/
        Placeholder::make('asesor_cliente_info')
            ->label('')
            ->content(function (Proyecto $record): HtmlString {
                $asesorClienteNombre = $record->cliente->asesor->name ?? 'No asignado';
                $color = $record->cliente->asesor ? '#16a34a' : '#f59e0b';

                return new HtmlString("
                    <div style='
                        background-color: {$color};
                        color: white;
                        padding: 0.75rem;
                        border-radius: 0.375rem;
                        font-weight: bold;
                        font-size: 0.9rem;
                        text-align: center;
                        margin-bottom: 1rem;
                    '>
                        Asesor del Cliente: {$asesorClienteNombre}
                    </div>
                ");
            }),

        Placeholder::make('asesor_cliente_info')
    ->label('')
    ->content(function (Proyecto $record): HtmlString {
        $asesorClienteNombre = $record->cliente->asesor->name ?? null;
        
        if ($asesorClienteNombre) {
            return new HtmlString("
                <div style='
                    background-color: #854d0e;
                    color: #fef9c3;
                    padding: 0.75rem;
                    border-radius: 0.375rem;
                    font-size: 0.9rem;
                    text-align: center;
                    margin-bottom: 1rem;
                '>
                    ⚠️ Este cliente ya tiene asesor asignado: <strong>{$asesorClienteNombre}</strong><br>
                    <span style='font-size:0.8rem;'>Si quieres asignarle el mismo al proyecto, selecciónalo en el desplegable de abajo.</span>
                </div>
            ");
        }

        return new HtmlString("
            <div style='
                background-color: #f59e0b;
                color: white;
                padding: 0.75rem;
                border-radius: 0.375rem;
                font-size: 0.9rem;
                text-align: center;
                margin-bottom: 1rem;
            '>
                ⚠️ Este cliente no tiene asesor asignado todavía.
            </div>
        ");
    }),


        /*----------------------------------------------
        | SELECT DE ASESOR
        ----------------------------------------------*/
        Select::make('user_id')
            ->label('Seleccionar Asesor para el Proyecto')
            ->relationship(
                'user',
                'name',
                fn (Builder $query) =>
                    $query->whereHas('roles', fn (Builder $q) =>
                        $q->whereIn('name', ['asesor', 'super_admin'])
                    )
            )
            ->searchable()
            ->preload()
            ->required()
            ->default(fn (?Proyecto $record): ?int => $record?->user_id),
    ])
    ->action(function (array $data, Proyecto $record): void {
        $record->user_id = $data['user_id'];
        $record->save();

        Notification::make()
            ->title('Asesor asignado correctamente')
            ->success()
            ->send();
    }),

/*--------------------------------------------------
| DESASIGNAR ASESOR
--------------------------------------------------*/
Action::make('unassign_assessor')
    ->label('')
    ->tooltip('Desasignar Asesor')
    ->icon('heroicon-o-user-minus')
    ->color('danger')
    ->visible(fn (Proyecto $record) => auth()->user()?->can('unassignAssessor', $record))
    ->requiresConfirmation()
    ->action(function (Proyecto $record): void {
        $record->user_id = null;
        $record->save();

        Notification::make()
            ->title('Asesor desasignado correctamente')
            ->success()
            ->send();
        }),                
    ])
    ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
->toolbarActions([
    BulkActionGroup::make([
        DeleteBulkAction::make(),
          ExportBulkAction::make('exportar_completo')
        ->label('Exportar seleccionados')
        ->exports([
            ExcelExport::make('proyectos')
                //->fromTable() // usa los registros seleccionados
                ->withColumns([
                // --- Datos del Proyecto ---
                Column::make('id')
                    ->heading('ID Proyecto'),
                Column::make('nombre')
                    ->heading('Nombre del Proyecto'),
                Column::make('estado')
                    ->heading('Estado')
                    ->formatStateUsing(fn ($state) => $state instanceof ProyectoEstadoEnum ? $state->getLabel() : $state), // Muestra la etiqueta del Enum   
                // --- Datos del Cliente Asociado ---
                Column::make('cliente.razon_social')
                    ->heading('Cliente'),
                Column::make('cliente.dni_cif')
                    ->heading('DNI/CIF Cliente'),
                // --- Datos de Asignación ---
                Column::make('user.name')
                    ->heading('Asesor Asignado al Proyecto'),
                // --- Datos de la Venta de Origen ---
                Column::make('venta.id')
                    ->heading('ID Venta Origen'),
                Column::make('venta.comercial.name')
                    ->heading('Comercial (Venta)'),
                Column::make('servicio.nombre')
                    ->heading('Servicio Activador'),
                     // ▼▼▼ AÑADIR ESTA NUEVA COLUMNA ▼▼▼
                Column::make('suscripciones_pendientes')
                    ->heading('Suscripciones Dependientes')
                    ->getStateUsing(function (Proyecto $record): int {
                        // Si el proyecto no tiene una venta asociada, no hay dependencias.
                        if (!$record->venta) {
                            return 0;
                        }

                        // Contamos las suscripciones de la misma venta que están pendientes.
                        return $record->venta->suscripciones()
                            ->where('estado', ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION)
                            ->count();
                    }),
                // --- Fechas Clave ---
                Column::make('created_at')
                    ->heading('Fecha Creación')
                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('d/m/Y H:i') : ''),
                Column::make('agenda')
                    ->heading('Próximo Seguimiento')
                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('d/m/Y H:i') : ''),
                Column::make('fecha_finalizacion')
                    ->heading('Fecha Finalización')
                    ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('d/m/Y H:i') : ''),
            ]),
        ])
        ->icon('icon-excel2')
        ->color('success')
        ->deselectRecordsAfterCompletion()
        ->requiresConfirmation()
        ->modalHeading('Exportar Proyectos Seleccionados')
        ->modalDescription('Exportarás todos los datos de los Proyectos seleccionados.'),      
            ]),
        ]);
    }
     // <<< AÑADIDO: Método infolist para la página de vista detallada
public static function infolist(Schema $schema): Schema
{
    return $schema->components([

        /*
        |--------------------------------------------------------------------------
        | 1. DETALLES DEL ENCARGO / MEMORIA (FULL WIDTH)
        |--------------------------------------------------------------------------
        */
        Section::make('📋 Detalles del Encargo / Memoria')
            ->description('Información volcada automáticamente desde la contratación.')
            ->schema([
                TextEntry::make('descripcion')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->html()
                    ->state(function ($record) {
                        $texto = $record->descripcion ?? 'No hay descripción detallada disponible.';
                        $safeText = e($texto);

                        return <<<HTML
                            <div class="whitespace-pre-wrap font-mono text-sm p-4 rounded-lg border
                                        bg-gray-50 border-gray-200 text-gray-800
                                        dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                                {$safeText}
                            </div>
                        HTML;
                    }),
            ])
            ->collapsible()
            ->columnSpanFull(),

        /*
        |--------------------------------------------------------------------------
        | 2. FILA PRINCIPAL (4 COLUMNAS)
        |--------------------------------------------------------------------------
        */
        Section::make()
            ->schema([

                /* --------------------------------------------
                 | PROYECTO (2 columnas)
                 -------------------------------------------- */
                Section::make(fn (Proyecto $record) =>
                    'Proyecto para ' . ($record->cliente->razon_social ?? 'Cliente Desconocido')
                )
                    ->schema([
                        TextEntry::make('nombre')
                            ->label('Nombre del Proyecto')
                            ->copyable()
                            ->weight('bold')
                            ->color('primary')
                            ->columnSpan(2),

                        TextEntry::make('cliente.telefono_contacto')
                            ->label('Teléfono Cliente')
                            ->copyable()
                            ->weight('bold')
                            ->color('primary'),

                        TextEntry::make('cliente.email_contacto')
                            ->label('Email Cliente')
                            ->copyable()
                            ->weight('bold')
                            ->color('primary'),

                        TextEntry::make('acceso_perfil_cliente')
                            ->label('Cliente')
                            ->state(fn ($record) => $record->cliente->razon_social ?? 'Cliente no disponible')
                            ->url(fn ($record) =>
                                $record->cliente_id
                                    ? ClienteResource::getUrl('view', ['record' => $record->cliente_id])
                                    : null
                            )
                            ->openUrlInNewTab()
                            ->icon('heroicon-m-arrow-top-right-on-square')
                            ->color('warning')
                            ->weight('bold'),

                        TextEntry::make('venta.lead.procedencia.procedencia')
                            ->label('Tipo de Lead')
                            ->badge()
                            ->color('success')
                            ->placeholder('No especificado'),

                        TextEntry::make('venta.lead.demandado')
                            ->label('Demandado del Lead')
                            ->copyable()
                            ->placeholder('No informado')
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpan(1),

                /* --------------------------------------------
                 | ESTADO & ASIGNACIÓN (2 columnas)
                 -------------------------------------------- */
                Section::make('Estado & Asignación')
                    ->schema([
                        TextEntry::make('venta.comercial.name')
                            ->label('Comercial')
                            ->badge()
                            ->color('primary'),

                        TextEntry::make('created_at')
                            ->label('Proyecto creado')
                            ->dateTime('d/m/y H:i'),

                        TextEntry::make('venta.id')
                            ->label('Venta de Origen')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? 'Venta #' . $state : 'No asociada')
                            ->url(fn ($record) =>
                                $record->venta_id
                                    ? VentaResource::getUrl('edit', ['record' => $record->venta_id])
                                    : null
                            )
                            ->openUrlInNewTab()
                            ->color(fn ($record) => $record->venta_id ? 'warning' : 'secondary'),

                        TextEntry::make('lead.id')
                            ->label('Lead de Origen')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? 'Lead #' . $state : 'Sin Lead')
                            ->url(fn ($record) =>
                                $record->lead_id
                                    ? LeadResource::getUrl('edit', ['record' => $record->lead_id])
                                    : null
                            )
                            ->openUrlInNewTab()
                            ->color('warning'),

                        TextEntry::make('user.name')
                            ->label('Asesor Asignado')
                            ->badge()
                            ->getStateUsing(fn ($record) => $record->user?->name ?? '⚠️ Sin asignar')
                            ->color(fn ($state) =>
                                str_contains($state, 'Sin asignar') ? 'warning' : 'info'
                            ),

                        TextEntry::make('estado')
                            ->label('Estado Actual')
                            ->badge()
                            ->color(fn ($state) => match ($state->value) {
                                'pendiente' => 'primary',
                                'en_progreso' => 'warning',
                                'finalizado' => 'success',
                                'cancelado' => 'danger',
                                default => 'gray',
                            })
                            ->suffixActions([
                                Action::make('cambiar_estado_proyecto')
                                    ->icon('heroicon-m-arrow-path')
                                    ->iconButton()
                                    ->schema([
                                        Select::make('estado')
                                            ->options(ProyectoEstadoEnum::class)
                                            ->required(),
                                        Textarea::make('comentario_estado')->rows(3),
                                    ])
                                    ->action(function (array $data, Proyecto $record) {
                                        $nuevoEstado = $data['estado'];

                                        if (! $nuevoEstado instanceof ProyectoEstadoEnum) {
                                            $nuevoEstado = ProyectoEstadoEnum::tryFrom($nuevoEstado);
                                        }

                                        if (! $nuevoEstado) {
                                            return;
                                        }

                                        $record->estado = $nuevoEstado;
                                        $record->save();

                                        $comentario = 'Cambio de estado a: ' . $nuevoEstado->getLabel();

                                        if (! empty($data['comentario_estado'])) {
                                            $comentario .= "\n---\nObservación: " . $data['comentario_estado'];
                                        }

                                        $record->comentarios()->create([
                                            'user_id'  => auth()->id(),
                                            'contenido'=> $comentario,
                                        ]);

                                        Notification::make()
                                            ->title('Estado actualizado')
                                            ->success()
                                            ->send();
                                    }),
                            ]),

                        Section::make('Proyectos o servicios dependientes de la misma venta')
                            ->schema([
                                ViewEntry::make('resumen_venta_pendientes')
                                    ->view('filament.infolists.components.resumen-venta-pendientes'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->columnSpan(2),

                /* --------------------------------------------
                 | AGENDA & GESTIÓN + INTERACCIONES (1 columna)
                 -------------------------------------------- */
                Section::make('Agenda & Gestión')
                    ->schema([

                        TextEntry::make('agenda')
    ->label('📆 Próxima cita')
    ->state(fn ($record) => $record->agenda ?? '—')
    ->formatStateUsing(fn ($state) =>
        $state && $state !== '—'
            ? Carbon::parse($state)->format('d/m/Y H:i')
            : 'Sin agendar'
    )
    ->color(fn ($state) => $state && $state !== '—' ? 'primary' : 'gray')
    ->suffixActions([
        Action::make('reagendar')
            ->icon('heroicon-o-calendar-days')
            ->iconButton()
            ->tooltip('Agendar / cambiar cita')
            ->schema([
                DateTimePicker::make('agenda')
                    ->label('Nueva fecha y hora')
                    ->native(false)
                    ->minutesStep(30)
                    ->required(),
            ])
            ->action(function (array $data, Proyecto $record, $livewire) {
                $record->agenda = $data['agenda'];
                $record->save();

                $record->comentarios()->create([
                    'user_id'   => auth()->id(),
                    'contenido' => '📅 Nueva agenda: ' .
                        Carbon::parse($data['agenda'])->format('d/m/Y H:i'),
                ]);

                Notification::make()
                    ->title('Agenda actualizada')
                    ->success()
                    ->send();

                // 🔥 refresco v4
                $record->refresh();
                $livewire->dispatch('$refresh');
            }),
        ]),


                        TextEntry::make('updated_at')
                            ->label('Última Act.')
                            ->dateTime('d/m/y H:i')
                            ->color('warning'),

                        TextEntry::make('fecha_finalizacion')
                            ->label('Fecha Finalización')
                            ->dateTime('d/m/y H:i')
                            ->placeholder('En curso')
                            ->color('success'),

                        /* -------- INTERACCIONES -------- */
                        Section::make('Interacciones')
                            ->schema([
                                TextEntry::make('llamadas')
                                    //->label('')
                                    ->hiddenLabel()
                                    ->size('xl')
                                    ->weight('bold')
                                    ->alignment(Alignment::Center)
                                    ->suffixActions([
                                        Action::make('add_llamada')
                                            ->icon('heroicon-m-phone-arrow-up-right')
                                            ->iconButton()
                                            ->color('primary')
                                            ->schema([
                                                Toggle::make('respuesta')->label('Contestado')->live(),
                                                Textarea::make('comentario')
                                                    ->visible(fn (Get $get) => $get('respuesta'))
                                                    ->required(fn (Get $get) => $get('respuesta')),
                                                Toggle::make('agendar')->label('Agendar seguimiento')->live(),
                                                DateTimePicker::make('agenda')
                                                    ->visible(fn (Get $get) => $get('agendar'))
                                                    ->minDate(now()),
                                            ])
                                            ->action(function (array $data, Proyecto $record, $livewire) {
    self::registrarInteraccion(
        $record,
        'llamadas',
        $data['comentario'] ?? '',
        $data['respuesta'] ?? false,
        $data['agendar'] ?? false,
        isset($data['agenda']) ? Carbon::parse($data['agenda']) : null
    );

    // 🔥 CLAVE FILAMENT V4
    $record->refresh();
    $livewire->dispatch('$refresh');
})
                                    ]),

                                TextEntry::make('emails')
                                   // ->label('📧 Emails')
                                    ->hiddenLabel()
                                    ->size('xl')
                                    ->weight('bold')
                                    ->alignment(Alignment::Center)
                                    ->suffixActions([
                                        Action::make('add_email')
                                            ->icon('heroicon-m-envelope-open')
                                            ->iconButton()
                                            ->color('warning')
                                            ->schema([
                                                Textarea::make('comentario')->label('Resumen'),
                                                Toggle::make('agendar')->label('Agendar seguimiento')->live(),
                                                DateTimePicker::make('agenda')
                                                    ->visible(fn (Get $get) => $get('agendar'))
                                                    ->minDate(now()),
                                            ])
                                            ->action(function (array $data, Proyecto $record, $livewire) {
                self::registrarInteraccion(
                    $record,
                    'emails',
                    $data['comentario'] ?? '',
                    true,
                    $data['agendar'] ?? false,
                    isset($data['agenda']) ? Carbon::parse($data['agenda']) : null
                );

                // 🔥 REFRESCO OBLIGATORIO FILAMENT V4
                $record->refresh();
                $livewire->dispatch('$refresh');
            })
                                    ]),

                                TextEntry::make('chats')
                                 //   ->label('💬 Chats')
                                  ->hiddenLabel()
                                    ->size('xl')
                                    ->weight('bold')
                                    ->alignment(Alignment::Center)
                                    ->suffixActions([
                                        Action::make('add_chat')
                                            ->icon('heroicon-m-chat-bubble-left-right')
                                            ->iconButton()
                                            ->color('success')
                                            ->schema([
                                                Textarea::make('comentario')->label('Resumen'),
                                                Toggle::make('agendar')->label('Agendar seguimiento')->live(),
                                                DateTimePicker::make('agenda')
                                                    ->visible(fn (Get $get) => $get('agendar'))
                                                    ->minDate(now()),
                                            ])
                                           ->action(function (array $data, Proyecto $record, $livewire) {
                self::registrarInteraccion(
                    $record,
                    'chats',
                    $data['comentario'] ?? '',
                    true,
                    $data['agendar'] ?? false,
                    isset($data['agenda']) ? Carbon::parse($data['agenda']) : null
                );

                // 🔥 REFRESCO OBLIGATORIO FILAMENT V4
                $record->refresh();
                $livewire->dispatch('$refresh');
            })
                                    ]),

                                TextEntry::make('total_interacciones')
                                    ->label('Total')
                                    ->inlineLabel()
                                    ->size('xl')
                                    ->weight('extrabold')
                                    ->color('warning')
                                    ->alignment(Alignment::Center)
                                    ->getStateUsing(fn (Proyecto $record) => $record->total_interacciones),
                            ])
                            ->columns(4),
                    ])
                    ->columnSpan(1),

            ])
            ->columns(4)
            ->columnSpanFull(),

       
       
    ]);
}


    public static function getRelations(): array
    {
        return [
            // Aquí vamos a añadir el RelationManager para comentarios
                  
                          \App\Filament\Resources\ClienteResource\RelationManagers\ComentariosRelationManager::class,
                          DocumentosRelationManager::class,


        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProyectos::route('/'),
          //LOS PROYECTOS SE CREAN DE FORMA AUTOMATICA DESDE LA VENTA POR EL TIPO DE SERVICIO  'create' => Pages\CreateProyecto::route('/create'),
            'edit' => EditProyecto::route('/{record}/edit'),
            'view' => ViewProyecto::route('/{record}'), // Añadida ruta para la página de vista

        ];
    }

    public static function canCreate(): bool
    {
        // Solo permitir la creación directa a Super Admins si es necesario para casos excepcionales
        // O false para deshabilitarlo completamente para todos
        // return auth()->user()->hasRole('super_admin'); 
        return false; // Deshabilita el botón de crear para todos los roles
    }

     public static function registrarInteraccion(
        Proyecto $record,
        string $tipo_accion,
        string $comentario_modal_texto,
        bool $contestada_o_enviado = false, // Para llamadas, email, chat
        bool $agendar_seguimiento = false,
        ?Carbon $agenda_fecha_modal = null
    ): void {
        $currentUser = Auth::user();
        $userName = $currentUser?->name ?? 'Usuario';

        // 1. Construir el texto inicial del comentario
        $comentarioTextoInicial = "";
        $notificacionTitulo = "";
        $notificacionBody = "";
        $notificacionTipo = "success"; // Por defecto

        switch ($tipo_accion) {
            case 'llamadas':
                $record->increment('llamadas');
                $notificacionTitulo = 'Llamada registrada';
                $comentarioTextoInicial = "Llamada registrada por {$userName}.";
                if ($contestada_o_enviado) { // Si es llamada 'contestada'
                    $comentarioTextoInicial .= " [Contestada]";
                    $notificacionBody = "Se ha registrado una llamada contestada.";
                } else { // Si es llamada 'sin respuesta'
                    $comentarioTextoInicial .= " [📞Sin respuesta]";
                    $notificacionBody = "Se ha registrado una llamada sin respuesta.";
                }
                break;
            case 'emails':
                $record->increment('emails');
                $notificacionTitulo = 'Email registrado';
                $comentarioTextoInicial = "📧 Email registrado por {$userName}.";
                $notificacionBody = "Se ha registrado el envío de un email.";
                break;
            case 'chats':
                $record->increment('chats');
                $notificacionTitulo = 'Chat registrado';
                $comentarioTextoInicial = "💬 Chat registrado por {$userName}.";
                $notificacionBody = "Se ha registrado una conversación por chat.";
                break;
            case 'otros_acciones':
                $record->increment('otros_acciones');
                $notificacionTitulo = 'Acción registrada';
                $comentarioTextoInicial = "📎 Otra acción registrada por {$userName}.";
                $notificacionBody = "Se ha registrado una acción general.";
                break;
        }

        // Añadir comentario del modal al texto inicial si existe
        if (!empty($comentario_modal_texto)) {
            $comentarioTextoInicial .= "  ---  Observación: " . $comentario_modal_texto;
        }

        // 2. Actualizar la agenda y construir la parte final del comentario
        $comentarioTextoFinal = $comentarioTextoInicial;
        if ($agendar_seguimiento && $agenda_fecha_modal) {
            try {
                $record->agenda = $agenda_fecha_modal; // Actualiza el campo agenda del proyecto
                $record->save(); // Guarda el proyecto (con el contador incrementado y la agenda)

                $textoRelativo = $agenda_fecha_modal->diffForHumans();
                $fechaFormateada = $agenda_fecha_modal->isoFormat('dddd D [de] MMMM, HH:mm');
                $comentarioTextoFinal .= " -- Próximo seguimiento agendado: {$textoRelativo} (el {$fechaFormateada}).";
                $notificacionBody .= " -- Próximo seguimiento: " . $agenda_fecha_modal->format('d/m/Y H:i');
            } catch (Exception $e) {
                Log::error('Error al procesar o guardar fecha de agenda en acción ' . $tipo_accion . ' para Proyecto ID ' . $record->id . ': ' . $e->getMessage());
                Notification::make()->title('Error al procesar fecha')->body('La fecha de agenda proporcionada no es válida o no se pudo guardar.')->danger()->send();
                $notificacionTipo = "warning"; // Notificación de error si falla la agenda
            }
        } else {
            // Si no se agendó, guardar el proyecto solo con el contador incrementado
            $record->save(); 
        }

        // 3. Crear el comentario polimórfico
        try {
            $record->comentarios()->create([
                'user_id' => $currentUser->id,
                'contenido' => $comentarioTextoFinal,
            ]);
        } catch (Exception $e) {
            Log::error('Error al guardar comentario de interacción para Proyecto ID ' . $record->id . ': ' . $e->getMessage());
            Notification::make()->title('Error interno')->body('No se pudo guardar el comentario asociado.')->warning()->send();
            $notificacionTipo = "warning"; // Notificación de error si falla el comentario
        }

        // 4. Enviar Notificación final
        Notification::make()->title($notificacionTitulo)->body($notificacionBody)->{$notificacionTipo}()->send();
         
    }
}