<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProyectoResource\Pages;
use App\Filament\Resources\ProyectoResource\RelationManagers;
use App\Models\Proyecto;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Select; // Importa Select
use Filament\Forms\Components\TextInput; // Importa TextInput
use Filament\Forms\Components\Textarea; // Importa Textarea
use Filament\Forms\Components\DatePicker; // Importa DatePicker
use Filament\Forms\Components\DateTimePicker; // Importa DateTimePicker
use Filament\Forms\Components\Section; // Importa Section
use Filament\Tables\Columns\TextColumn; // Importa TextColumn

use App\Enums\ProyectoEstadoEnum; // Si usas el Enum para estados
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Carbon\Carbon;
use Filament\Forms\Components\Placeholder;
use Filament\Infolists\Infolist;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Actions\Action as ActionInfolist;
use Filament\Notifications\Notification;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Actions; // <<< Importa este para el grupo de acciones
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Set; // <<< ASEGÚRATE DE QUE ESTA LÍNEA ESTÉ AQUÍ
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Log; // Para Log::error
use Filament\Forms\Components\Toggle; // Para el Toggle en los formularios de las acciones
use Filament\Infolists\Components\ViewEntry;
use App\Enums\ServicioTipoEnum;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use App\Enums\ClienteSuscripcionEstadoEnum;






class ProyectoResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Proyecto::class;

    protected static ?string $navigationIcon = 'heroicon-o-briefcase'; // Icono de maletín
    protected static ?string $navigationGroup = null; // Nuevo grupo de navegación
    protected static ?string $modelLabel = 'Proyecto';
    protected static ?string $pluralModelLabel = 'Proyectos';



      public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'delete',
            'delete_any',
            'assign_assessor',   // Permiso para asignar/cambiar asesor
            'unassign_assessor', // Permiso para quitar asesor
          
          
        ];
    }

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
        /** @var \App\Models\User|null $user */
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



    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                            ->disabled(fn(Forms\Get $get) => $get('estado') !== ProyectoEstadoEnum::Finalizado->value) // Deshabilitado si no está finalizado
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
                    ->formatStateUsing(fn ($state) => \App\Enums\ProyectoEstadoEnum::tryFrom($state)?->getLabel() ?? $state)
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
                  Tables\Filters\SelectFilter::make('servicio_id')
                ->label('Servicio Único') // He cambiado la etiqueta para más claridad
                ->relationship(
                    name: 'servicio', 
                    titleAttribute: 'nombre',
                    // ▼▼▼ AÑADIMOS ESTA CONDICIÓN ▼▼▼
                    modifyQueryUsing: fn (Builder $query) => $query->where('tipo', ServicioTipoEnum::UNICO)
                )
                ->searchable()
                ->preload(),
                Tables\Filters\SelectFilter::make('cliente_id')
                    ->relationship('cliente', 'dni_cif')
                    ->searchable()
                    ->preload()
                    ->label('Filtrar por Cliente'),
               
                // Filtro por Asesor Asignado
                Tables\Filters\SelectFilter::make('user_id')
                    ->relationship('user', 'name', fn (Builder $query) => 
                        $query->whereHas('roles', fn (Builder $q) => $q->whereIn('name', ['asesor', 'coordinador']))
                    )
                    ->searchable()
                    ->preload()
                    ->label('Filtrar por Asesor'),

                // Filtro por Estado del Proyecto
                Tables\Filters\SelectFilter::make('estado')
                    ->options(ProyectoEstadoEnum::class) // Usa el Enum para las opciones
                    ->native(false)
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
                Tables\Filters\Filter::make('sin_asesor')
                ->label('Sin Asesor Asignado')
                ->query(fn (Builder $query): Builder => $query->whereNull('user_id'))
                ->toggle(),
                     
            ],layout: \Filament\Tables\Enums\FiltersLayout::AboveContent)
            ->filtersFormColumns(8)
         
            ->actions([
                Tables\Actions\ViewAction::make()
                ->label('')
                ->tooltip('Ver Proyecto')
                 ->openUrlInNewTab(),
                Tables\Actions\EditAction::make()
                ->label('')
                ->tooltip('Editar Proyecto')
                ->openUrlInNewTab(),  
                  // <<< AÑADIDO: Acción para Asignar Asesor
               Action::make('assign_assessor')
                    ->label('')
                    ->icon('heroicon-o-user-plus')
                    ->color(fn (Proyecto $record): string => $record->user_id ? 'primary' : 'warning')
                    ->visible(fn ($record) => auth()->user()?->can('assign_assessor_proyecto'))
 ->tooltip(fn (Proyecto $record): string => $record->user_id ? 'Cambiar Asesor Asignado' : 'Asignar Asesor')

                    ->modalHeading('Asignar Asesor al Proyecto')
                    ->modalSubmitActionLabel('Asignar')
                    ->modalWidth('md')
                    ->form([
                        // <<< AÑADIDO: Placeholder para mostrar el asesor del cliente
                        Placeholder::make('asesor_cliente_info')
                            ->label('') // No necesitamos etiqueta visible para este placeholder
                            ->content(function (Proyecto $record): HtmlString {
                                $asesorClienteNombre = $record->cliente->asesor->name ?? 'No asignado'; // Asume relación cliente->asesor->name
                                $color = $record->cliente->asesor ? '#16a34a' : '#f59e0b'; // green-600 (info) o amber-500 (warning)

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
                          // <<< AÑADIDO: Botón para Asignarse a sí mismo
                     Actions::make([
                            FormAction::make('assign_self')
                                ->label('Asignar al mismo asesor')
                                ->icon('heroicon-m-user-circle')
                                ->color('warning')
                                ->outlined()
                                 ->visible(fn (Proyecto $record): bool => (bool)$record->cliente->asesor_id) // Visible solo si el cliente tiene asesor_id
                                // No es de tipo submit, solo rellena el campo
                                ->action(function (Set $set): void { 
                                    $set('user_id', Auth::id()); // Rellena el select con el ID del usuario logueado
                                    // NO intentamos submit() aquí. El usuario tendrá que hacer clic en 'Asignar'.
                                    // Opcional: podrías añadir una notificación aquí para indicar que se ha rellenado
                                    // Notification::make()->title('Asesor seleccionado')->body('Ahora haz clic en "Asignar".')->info()->send();
                                }),
                        ])->fullWidth(), // Ocupa todo el ancho disponible para el botón
                        // FIN AÑADIDO

                        Select::make('user_id')
                            ->label('Seleccionar Asesor para el Proyecto') // Etiqueta más clara
                            ->relationship('user', 'name', fn (Builder $query) => 
                                $query->whereHas('roles', fn (Builder $q) => $q->whereIn('name', ['asesor', 'super_admin']))
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

                     Action::make('unassign_assessor')
                    ->label('')
                     ->tooltip('Desasignar Asesor') // Tooltip estático para desasignar
                    ->icon('heroicon-o-user-minus')
                    ->color('danger') // Color rojo
                   ->visible(fn (Proyecto $record): bool => 
                        (bool)$record->user_id && // Solo visible si ya hay un asesor
                        auth()->user()->can('unassign_assessor_proyecto') // Comprueba el permiso
                    )
                    ->requiresConfirmation() // Preguntar confirmación antes de desasignar                    
                    ->action(function (Proyecto $record): void {
                        $record->user_id = null; // Poner el asesor a null
                        $record->save();

                        Notification::make()
                            ->title('Asesor desasignado correctamente')
                            ->success()
                            ->send();
                    }),


                ])
->bulkActions([
    Tables\Actions\BulkActionGroup::make([
        Tables\Actions\DeleteBulkAction::make(),
          ExportBulkAction::make('exportar_completo')
        ->label('Exportar seleccionados')
        ->exports([
            \pxlrbt\FilamentExcel\Exports\ExcelExport::make('proyectos')
                //->fromTable() // usa los registros seleccionados
                ->withColumns([
                // --- Datos del Proyecto ---
                \pxlrbt\FilamentExcel\Columns\Column::make('id')
                    ->heading('ID Proyecto'),
                \pxlrbt\FilamentExcel\Columns\Column::make('nombre')
                    ->heading('Nombre del Proyecto'),
                \pxlrbt\FilamentExcel\Columns\Column::make('estado')
                    ->heading('Estado')
                    ->formatStateUsing(fn ($state) => $state instanceof \App\Enums\ProyectoEstadoEnum ? $state->getLabel() : $state), // Muestra la etiqueta del Enum   
                // --- Datos del Cliente Asociado ---
                \pxlrbt\FilamentExcel\Columns\Column::make('cliente.razon_social')
                    ->heading('Cliente'),
                \pxlrbt\FilamentExcel\Columns\Column::make('cliente.dni_cif')
                    ->heading('DNI/CIF Cliente'),
                // --- Datos de Asignación ---
                \pxlrbt\FilamentExcel\Columns\Column::make('user.name')
                    ->heading('Asesor Asignado al Proyecto'),
                // --- Datos de la Venta de Origen ---
                \pxlrbt\FilamentExcel\Columns\Column::make('venta.id')
                    ->heading('ID Venta Origen'),
                \pxlrbt\FilamentExcel\Columns\Column::make('venta.comercial.name')
                    ->heading('Comercial (Venta)'),
                \pxlrbt\FilamentExcel\Columns\Column::make('servicio.nombre')
                    ->heading('Servicio Activador'),
                     // ▼▼▼ AÑADIR ESTA NUEVA COLUMNA ▼▼▼
                \pxlrbt\FilamentExcel\Columns\Column::make('suscripciones_pendientes')
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
                \pxlrbt\FilamentExcel\Columns\Column::make('created_at')
                    ->heading('Fecha Creación')
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y H:i') : ''),
                \pxlrbt\FilamentExcel\Columns\Column::make('agenda')
                    ->heading('Próximo Seguimiento')
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y H:i') : ''),
                \pxlrbt\FilamentExcel\Columns\Column::make('fecha_finalizacion')
                    ->heading('Fecha Finalización')
                    ->formatStateUsing(fn ($state) => $state ? \Carbon\Carbon::parse($state)->format('d/m/Y H:i') : ''),
            ]),
        ])
        ->icon('icon-excel2')
        ->color('success')
        ->deselectRecordsAfterCompletion()
        ->requiresConfirmation()
        ->modalHeading('Exportar Proyectos Seleccionados')
        ->modalDescription('Exportarás todos los datos de los Proyectos seleccionados.'),

      
    ]),
])


;
    }

     // <<< AÑADIDO: Método infolist para la página de vista detallada
public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                
                // 1. SECCIÓN: DETALLES DEL ENCARGO (Volcado del contrato)
                InfoSection::make('📋 Detalles del Encargo / Memoria')
                    ->description('Información volcada automáticamente desde la contratación.')
                    ->schema([
                        TextEntry::make('descripcion')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->html()
                            ->state(function ($record) {
                                $texto = $record->descripcion ?? 'No hay descripción detallada disponible.';
                                $safeText = e($texto); // Escapar para seguridad
                                
                                // Div con estilos Tailwind para Light/Dark mode
                                return <<<HTML
                                    <div class="whitespace-pre-wrap font-mono text-sm p-4 rounded-lg border
                                                bg-gray-50 border-gray-200 text-gray-800
                                                dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300">
                                        {$safeText}
                                    </div>
                                HTML;
                            }),
                    ])
                    ->collapsible(),

                // 2. GRID PRINCIPAL
                Grid::make(3)->schema([
                    
                    // Columna 1: Info Básica
                    InfoSection::make(fn (Proyecto $record): string => 'Proyecto para ' . ($record->cliente->razon_social ?? 'Cliente Desconocido'))
                        ->schema([
                            TextEntry::make('nombre')  
                                ->label(new HtmlString('<span class="font-semibold">Nombre del Proyecto</span>'))
                                ->copyable()
                                ->weight('bold')
                                ->color('primary')                                        
                                ->columnSpan(2),
                            
                            TextEntry::make('cliente.telefono_contacto')      
                                ->label('Teléfono Cliente')
                                ->weight('bold')
                                ->color('primary')        
                                ->copyable(),
                            
                            TextEntry::make('cliente.email_contacto')      
                                ->label('Email Cliente')
                                ->weight('bold')
                                ->color('primary')        
                                ->copyable()
                                ->columnSpanFull(),

                            TextEntry::make('acceso_perfil_cliente')
                                ->label(new HtmlString('<span class="font-semibold">Acceso al Perfil del Cliente</span>')) 
                                ->state(fn (Proyecto $record) => $record->cliente->razon_social ?? 'Cliente no disponible')
                                ->url(fn (Proyecto $record): ?string => 
                                    $record->cliente_id ? ClienteResource::getUrl('view', ['record' => $record->cliente_id]) : null
                                )
                                ->openUrlInNewTab()
                                ->color('warning')
                                ->weight('bold')
                                ->icon('heroicon-m-arrow-top-right-on-square')                  
                                ->columnSpanFull(),

                            TextEntry::make('venta.lead.demandado')
                                ->label(new HtmlString('<span class="font-semibold">Demandado del Lead</span>'))
                                ->copyable()
                                ->weight('bold')
                                ->color('primary')
                                ->placeholder('No informado')
                                ->columnSpanFull(),              
                            
                            TextEntry::make('venta.lead.procedencia.procedencia')
                                ->label(new HtmlString('<span class="font-semibold">Tipo de Lead</span>'))
                                ->badge()
                                ->color('success')
                                ->placeholder('No especificado')
                                ->columnSpanFull(),
                        ])
                        ->columns(3)
                        ->columnSpan(1),

                    // Columna 2: Estado y Asignación
                    InfoSection::make('Estado & Asignación')
                        ->schema([
                            TextEntry::make('venta.comercial.name')
                                ->label('Comercial')
                                ->badge()
                                ->color('primary'),

                            TextEntry::make('created_at')
                                ->label('Proyecto creado')
                                ->dateTime('d/m/y H:i'),

                            TextEntry::make('venta.id')
                                ->label(new HtmlString('<span class="font-semibold">Venta de Origen</span>'))
                                ->badge()
                                ->formatStateUsing(fn ($state) => $state ? 'Venta #' . $state : 'No asociada')
                                ->url(fn (Proyecto $record) => $record->venta_id ? VentaResource::getUrl('edit', ['record' => $record->venta_id]) : null)
                                ->openUrlInNewTab()
                                ->color(fn ($record) => $record->venta_id ? 'warning' : 'secondary')
                                ->icon(fn ($record) => $record->venta_id ? 'heroicon-m-link' : null),

                            // --- NUEVO: Lead de Origen ---
                            TextEntry::make('lead.id')
                                ->label(new HtmlString('<span class="font-semibold">Lead de Origen</span>'))
                                ->badge()
                                ->formatStateUsing(fn ($state) => $state ? 'Lead #' . $state : 'Sin Lead')
                                ->color(fn ($state) => $state ? 'warning' : 'gray')
                                ->icon(fn ($state) => $state ? 'heroicon-m-link' : null)
                                ->url(fn (Proyecto $record) => $record->lead_id 
                                    ? \App\Filament\Resources\LeadResource::getUrl('edit', ['record' => $record->lead_id]) 
                                    : null
                                )
                                ->openUrlInNewTab(),

                            TextEntry::make('user.name')
                                ->label('Asesor Asignado')
                                ->badge()
                                ->getStateUsing(fn (Proyecto $record) => $record->user?->name ?? '⚠️ Sin asignar')
                                ->color(fn (string $state) => str_contains($state, 'Sin asignar') ? 'warning' : 'info'),

                            TextEntry::make('estado')
                                ->label(new HtmlString('<span class="font-semibold">Estado Actual</span>'))
                                ->badge()
                                ->columnSpan(2)
                                ->color(fn (\App\Enums\ProyectoEstadoEnum $state) => match ($state->value) {
                                    'pendiente' => 'primary',
                                    'en_progreso' => 'warning',
                                    'finalizado' => 'success',
                                    'cancelado' => 'danger',
                                    default => 'gray',
                                })
                                ->suffixAction(
                                    ActionInfolist::make('cambiar_estado_proyecto')
                                        ->label('')
                                        ->icon('heroicon-m-arrow-path')
                                        ->color('primary')
                                        ->modalHeading('Cambiar Estado del Proyecto')
                                        ->form([
                                            Select::make('estado')
                                                ->options(\App\Enums\ProyectoEstadoEnum::class)
                                                ->native(false)
                                                ->required()
                                                ->default(fn (?Proyecto $record) => $record?->estado?->value),
                                            Textarea::make('comentario_estado')
                                                ->rows(3)
                                                ->maxLength(500),
                                        ])
                                        ->action(function (array $data, Proyecto $record) {
                                            $nuevoEstado = \App\Enums\ProyectoEstadoEnum::tryFrom($data['estado']);
                                            if (!$nuevoEstado) return;
                                            $record->estado = $nuevoEstado;
                                            $record->save();
                                            
                                            $comentario = 'Cambio de estado a: ' . $nuevoEstado->getLabel();
                                            if (!empty($data['comentario_estado'])) $comentario .= "\n---\nObservación: " . $data['comentario_estado'];
                                            
                                            $record->comentarios()->create(['user_id' => Auth::id(), 'contenido' => $comentario]);
                                            Notification::make()->title('Estado actualizado')->success()->send();
                                        })
                                        ->visible(fn (Proyecto $record) => $record->user_id !== null && !$record->estado->isFinal())
                                ),

                            InfoSection::make('Proyectos o servicios dependientes de la misma venta')
                                ->description('Otros servicios de la misma venta.')
                                ->schema([
                                    ViewEntry::make('resumen_venta_pendientes')
                                        ->view('filament.infolists.components.resumen-venta-pendientes'),
                                ])
                                ->columnSpanFull(),
                        ])
                        ->columns(3)
                        ->columnSpan(1),

                    // Columna 3: Agenda y Gestión
                    InfoSection::make('Agenda & Gestión')
                        ->schema([
                            TextEntry::make('agenda')
                                ->label(new HtmlString('<span class="font-semibold">📆 Próxima cita</span>'))
                                ->dateTime('d/m/y H:i')
                                ->placeholder('Sin agendar')
                                ->suffixAction(
                                    ActionInfolist::make('reagendar')
                                        ->icon('heroicon-o-calendar-days')
                                        ->form([
                                            DateTimePicker::make('agenda')->native(false)->minutesStep(30),
                                        ])
                                        ->action(function (array $data, $record) {
                                            $record->agenda = $data['agenda'];
                                            $record->save();
                                            $record->comentarios()->create(['user_id' => auth()->id(), 'contenido' => '📅 Nueva agenda: ' . \Carbon\Carbon::parse($data['agenda'])->format('d/m/Y H:i')]);
                                            Notification::make()->title('Agenda actualizada')->success()->send();
                                        })
                                ),

                            TextEntry::make('updated_at')->label('Última Act.')->color('warning')->weight('bold')->dateTime('d/m/y H:i'),
                            TextEntry::make('fecha_finalizacion')->color('success')->weight('bold')->placeholder('En curso')->dateTime('d/m/y H:i'),

                            // --- INTERACCIONES (CORREGIDO) ---
                            InfoSection::make('Interacciones')
                                ->schema([
                                    
                                    // LLAMADAS
                                    TextEntry::make('llamadas')
                                        ->label('📞 Llamadas')
                                        ->size('xl')->weight('bold')->alignment(Alignment::Center)
                                        ->suffixAction(
                                            ActionInfolist::make('add_llamada')
                                                ->icon('heroicon-m-phone-arrow-up-right')->color('primary')
                                                ->form([
                                                    Toggle::make('respuesta')->label('Contestado')->live(),
                                                    Textarea::make('comentario')->visible(fn(Forms\Get $get)=>$get('respuesta'))->required(fn(Forms\Get $get)=>$get('respuesta')),
                                                    Toggle::make('agendar')->label('Agendar seguimiento')->live(),
                                                    DateTimePicker::make('agenda')->visible(fn(Forms\Get $get)=>$get('agendar'))->minDate(now())
                                                ])
                                                ->action(function(array $data, Proyecto $record){
                                                    self::registrarInteraccion($record, 'llamadas', $data['comentario']??'', $data['respuesta']??false, $data['agendar']??false, isset($data['agenda'])?\Carbon\Carbon::parse($data['agenda']):null);
                                                })
                                        ),

                                    // EMAILS
                                    TextEntry::make('emails')
                                        ->label('📧 Emails')
                                        ->size('xl')->weight('bold')->alignment(Alignment::Center)
                                        ->suffixAction(
                                            ActionInfolist::make('add_email')
                                                ->icon('heroicon-m-envelope-open')->color('warning')
                                                ->form([
                                                    Textarea::make('comentario')->label('Resumen'),
                                                    Toggle::make('agendar')->label('Agendar seguimiento')->live(),
                                                    DateTimePicker::make('agenda')->visible(fn(Forms\Get $get)=>$get('agendar'))->minDate(now())
                                                ])
                                                ->action(function(array $data, Proyecto $record){
                                                    self::registrarInteraccion($record, 'emails', $data['comentario']??'', true, $data['agendar']??false, isset($data['agenda'])?\Carbon\Carbon::parse($data['agenda']):null);
                                                })
                                        ),

                                    // CHATS
                                    TextEntry::make('chats')
                                        ->label('💬 Chats')
                                        ->size('xl')->weight('bold')->alignment(Alignment::Center)
                                        ->suffixAction(
                                            ActionInfolist::make('add_chat')
                                                ->icon('heroicon-m-chat-bubble-left-right')->color('success')
                                                ->form([
                                                    Textarea::make('comentario')->label('Resumen'),
                                                    Toggle::make('agendar')->label('Agendar seguimiento')->live(),
                                                    DateTimePicker::make('agenda')->visible(fn(Forms\Get $get)=>$get('agendar'))->minDate(now())
                                                ])
                                                ->action(function(array $data, Proyecto $record){
                                                    self::registrarInteraccion($record, 'chats', $data['comentario']??'', true, $data['agendar']??false, isset($data['agenda'])?\Carbon\Carbon::parse($data['agenda']):null);
                                                })
                                        ),

                                    // OTROS
                                    TextEntry::make('otros_acciones')
                                        ->label('📎 Otros')
                                        ->size('xl')->weight('bold')->alignment(Alignment::Center)
                                        ->suffixAction(
                                            ActionInfolist::make('add_otro')
                                                ->icon('heroicon-m-paper-airplane')->color('gray')
                                                ->form([
                                                    Textarea::make('comentario')->label('Descripción')->required(),
                                                    Toggle::make('agendar')->label('Agendar seguimiento')->live(),
                                                    DateTimePicker::make('agenda')->visible(fn(Forms\Get $get)=>$get('agendar'))->minDate(now())
                                                ])
                                                ->action(function(array $data, Proyecto $record){
                                                    self::registrarInteraccion($record, 'otros_acciones', $data['comentario']??'', true, $data['agendar']??false, isset($data['agenda'])?\Carbon\Carbon::parse($data['agenda']):null);
                                                })
                                        ),
                                    
                                    // TOTAL
                                    TextEntry::make('total_interacciones')
                                        ->label('🔥 Total')
                                        ->size('xl')->weight('extrabold')->color('warning')->alignment(Alignment::Center)
                                        ->getStateUsing(fn (Proyecto $record) => $record->total_interacciones),
                                ])
                                ->columns(5)->columnSpan(3),
                        ])
                        ->columns(3)
                        ->columnSpan(1),
                ]),

                // 3. COMENTARIOS (Estilo Burbuja Light/Dark)
                InfoSection::make('🗨️ Comentarios')
                    ->headerActions([
                        ActionInfolist::make('anadir_comentario')
                            ->label('Añadir comentario')
                            ->icon('heroicon-o-plus-circle')
                            ->form([Textarea::make('contenido')->required()])
                            ->action(function (array $data, Proyecto $record) {
                                $record->comentarios()->create(['user_id' => auth()->id(), 'contenido' => $data['contenido']]);
                                Notification::make()->title('Comentario guardado')->success()->send();
                            }),
                    ])
                    ->schema([
                        RepeatableEntry::make('comentarios')
                            ->label(false)->contained(false)
                            ->schema([
                                TextEntry::make('contenido')
                                    ->html()
                                    ->label(false)
                                    ->state(function ($record) {
                                        $usuario = e($record->user?->name ?? 'Usuario');
                                        $contenido = nl2br(e($record->contenido));
                                        $fecha = $record->created_at?->format('d/m H:i') ?? '';

                                        return <<<HTML
                                            <div class="flex flex-wrap items-center gap-3 px-4 py-3 rounded-xl my-2 text-sm shadow-sm 
                                                        bg-blue-50 text-blue-900 border border-blue-100
                                                        dark:bg-blue-900/30 dark:text-blue-100 dark:border-blue-800">
                                                <div class="flex items-center gap-2 font-bold whitespace-nowrap">
                                                    <span>🧑‍💼</span><span>{$usuario}</span>
                                                </div>
                                                <div class="flex-1 break-words">{$contenido}</div>
                                                <div class="text-xs opacity-70 whitespace-nowrap ml-auto">🕓 {$fecha}</div>
                                            </div>
                                        HTML;
                                    }),
                            ]),
                    ]),
            ]);
    }


    public static function getRelations(): array
    {
        return [
            // Aquí vamos a añadir el RelationManager para comentarios
                  RelationManagers\DocumentosRelationManager::class,

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProyectos::route('/'),
          //LOS PROYECTOS SE CREAN DE FORMA AUTOMATICA DESDE LA VENTA POR EL TIPO DE SERVICIO  'create' => Pages\CreateProyecto::route('/create'),
            'edit' => Pages\EditProyecto::route('/{record}/edit'),
            'view' => Pages\ViewProyecto::route('/{record}'), // Añadida ruta para la página de vista

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
        \App\Models\Proyecto $record,
        string $tipo_accion,
        string $comentario_modal_texto,
        bool $contestada_o_enviado = false, // Para llamadas, email, chat
        bool $agendar_seguimiento = false,
        ?\Carbon\Carbon $agenda_fecha_modal = null
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
            $comentarioTextoInicial .= "\n---\nObservación: " . $comentario_modal_texto;
        }

        // 2. Actualizar la agenda y construir la parte final del comentario
        $comentarioTextoFinal = $comentarioTextoInicial;
        if ($agendar_seguimiento && $agenda_fecha_modal) {
            try {
                $record->agenda = $agenda_fecha_modal; // Actualiza el campo agenda del proyecto
                $record->save(); // Guarda el proyecto (con el contador incrementado y la agenda)

                $textoRelativo = $agenda_fecha_modal->diffForHumans();
                $fechaFormateada = $agenda_fecha_modal->isoFormat('dddd D [de] MMMM, HH:mm');
                $comentarioTextoFinal .= "\nPróximo seguimiento agendado: {$textoRelativo} (el {$fechaFormateada}).";
                $notificacionBody .= "\nPróximo seguimiento: " . $agenda_fecha_modal->format('d/m/Y H:i');
            } catch (\Exception $e) {
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
        } catch (\Exception $e) {
            Log::error('Error al guardar comentario de interacción para Proyecto ID ' . $record->id . ': ' . $e->getMessage());
            Notification::make()->title('Error interno')->body('No se pudo guardar el comentario asociado.')->warning()->send();
            $notificacionTipo = "warning"; // Notificación de error si falla el comentario
        }

        // 4. Enviar Notificación final
        Notification::make()->title($notificacionTitulo)->body($notificacionBody)->{$notificacionTipo}()->send();
    }
}