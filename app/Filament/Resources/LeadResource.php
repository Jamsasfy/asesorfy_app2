<?php

namespace App\Filament\Resources;

use App\Enums\LeadEstadoEnum;
use App\Filament\Resources\LeadResource\Pages;
use App\Models\Comentario;
use App\Models\Lead;
use App\Models\MotivoDescarte;
use App\Models\User;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Carbon\Carbon;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Radio;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Form;
use Filament\Forms\Get;

use Filament\Tables\Columns\IconColumn;
use Illuminate\Support\Str;


use App\Models\LeadAutoEmailLog;

use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
use Filament\Tables;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

use Illuminate\Support\Facades\Auth;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;

use Illuminate\Support\HtmlString;

use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section as InfoSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;

use Filament\Infolists\Components\Actions\Action as ActionInfolist;
use Illuminate\Support\Facades\Log; // Para escribir en el log de Laravel

use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Filament\Tables\Filters\TernaryFilter;

use App\Models\Servicio;
use App\Enums\ServicioTipoEnum;
use App\Models\LeadConversionLink;
use App\Mail\LeadConversionLinkMail;
use Illuminate\Support\Facades\Mail;
use Filament\Tables\Actions\Action;
use Filament\Infolists\Components\ViewEntry;
use App\Enums\FacturaEstadoEnum;



//use Filament\Tables\Actions\Action; // Para acciones personalizadas


class LeadResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Lead::class;

    protected static ?string $navigationIcon = 'icon-leads';

    protected static ?string $navigationGroup = 'Gestión LEADS';

    //protected static ?string $navigationLabel = 'Todos los Leads';

    public static function getNavigationLabel(): string
{
    if (auth()->check() && auth()->user()->hasRole('comercial')) {
        return 'Mis Leads';
    }

    return 'Todos los Leads';
}


    protected static ?string $modelLabel = 'Lead';
    protected static ?string $pluralModelLabel = 'Todos los Leads';

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

    public static function getEloquentQuery(): Builder
    {
            $user = auth()->user();

            // Empieza con la consulta base del recurso
            $query = parent::getEloquentQuery()->with(['comentarios.user']);

if ($user && $user->hasRole('comercial') && ! $user->hasRole('super_admin')) {
        $query->where('asignado_id', $user->id);
    }
            return $query;

    }

public static function shouldRegisterNavigation(): bool
{
    // Solo los super_admins verán el recurso “Todos los Leads”
    return auth()->user()?->hasRole(['super_admin', 'comercial']);
}


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Información de Contacto')
                    ->columns(2)
                    ->schema([
                        TextInput::make('nombre')
                            ->label('Nombre Lead / Empresa')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),
                        TextInput::make('tfn')
                            ->label('Teléfono')
                            ->required()
                            ->tel() // Validación básica de teléfono
                            // ->regex('/^(?:\+34|0034|34)?[6789]\d{8}$/') // Puedes mantener tu regex si prefieres
                            ->maxLength(20)
                            ->suffixIcon('heroicon-m-phone')
                            ->columnSpan(1),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->nullable() // Coincide con la migración
                            ->maxLength(255)
                             // Añadiremos validación única más compleja si es necesario,
                             // considerando leads y usuarios, al guardar.
                            ->suffixIcon('heroicon-m-envelope')
                            ->columnSpan(1),
                        Toggle::make('autospam_activo')
                        ->label('Autospam activo')
                        ->helperText('Si lo desactivas, este lead deja de recibir emails automáticos de seguimiento que los manda Boot IA Fy.')
                        ->default(true)
                        ->inline(false)
                    ]),

                Section::make('Origen y Asignación')
                    ->columns(2)
                    ->schema([
                        Select::make('procedencia_id')
                            ->relationship('procedencia', 'procedencia') 
                            ->label('Procedencia')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            
                            ->columnSpan(1),
                            Select::make('asignado_id')
                            ->relationship(
                                name: 'asignado', // Nombre de la relación en el modelo Lead
                                titleAttribute: 'name', // Atributo a mostrar del modelo User (ajusta si usas 'full_name' u otro)
                                // Modificador de la consulta para filtrar por rol:
                                modifyQueryUsing: fn (Builder $query) => $query->whereHas('roles', fn (Builder $q) => $q->where('name', 'comercial'))
                                                                                // Opcional: O añadir al propio usuario logueado aunque no sea comercial? ->orWhere('id', Auth::id())
                            )
                            ->label('Comercial Asignado')
                            ->searchable()
                            ->preload()
                            ->nullable() // Permite que el valor sea null (sin asignar)
                            ->placeholder('Sin Asignar') // Texto que se muestra si está vacío/null
                            // ->default(fn (): ?int => Auth::id()) // <-- Eliminamos esta línea, la lógica está ahora en mutateFormDataBeforeCreate
                            ->columnSpan(1),
                    ]),

                Section::make('Detalles y Estado')
                    ->columns(3) // Ajusta columnas según necesidad
                    ->schema([
                         Textarea::make('demandado')
                            ->label('Necesidad / Demanda del Lead')
                            ->nullable()
                            ->rows(4) // Más espacio que un TextInput
                            ->columnSpanFull(), // Ocupa todo el ancho

                            Select::make('estado')
                            ->options(LeadEstadoEnum::class)
                            ->required()
                            ->live()
                            ->default(LeadEstadoEnum::SIN_GESTIONAR)
                            ->label('Estado del Lead')
                            ->columnSpan(1)
                           ->disabled(function (?Lead $record): bool {
                                // Si $record es null o estado es null, devolvemos false
                                return $record?->estado?->isFinal() ?? false;
                            })
                        ->helperText(fn (?Lead $record): ?string =>
                            (! is_null($record?->cliente_id))
                                ? 'No puedes cambiar el estado, ya se creó el cliente y la venta del mismo.'
                                : null
                        )
                            ->afterStateUpdated(function (Get $get, Set $set, mixed $state, ?Lead $record, string $operation) {
                                $newEnum = $state instanceof LeadEstadoEnum ? $state : LeadEstadoEnum::tryFrom($state);
                        
                                // Lógica para fecha_gestion
                                $fechaGestionForm = $get('fecha_gestion');
                                $fechaGestionOriginal = $operation === 'edit' && $record ? $record->getOriginal('fecha_gestion') : null;
                        
                                if (
                                    $newEnum instanceof LeadEstadoEnum &&
                                    $newEnum !== LeadEstadoEnum::SIN_GESTIONAR &&
                                    !$newEnum->isConvertido() &&
                                    $newEnum !== LeadEstadoEnum::DESCARTADO &&
                                    is_null($fechaGestionForm) &&
                                    is_null($fechaGestionOriginal)
                                ) {
                                    $set('fecha_gestion', now());
                                } elseif ($newEnum === LeadEstadoEnum::SIN_GESTIONAR) {
                                    $set('fecha_gestion', null);
                                }
                        
                                // Lógica para fecha_cierre
                                if ($newEnum?->isFinal()) {
                                    $set('fecha_cierre', now());
                                } else {
                                    $set('fecha_cierre', null);
                                }
                            }),
                       DateTimePicker::make('agenda')
    ->label('Próximo Seguimiento')
    ->native(false)
    ->seconds(false)
    ->nullable()
    // ⬇️ SOLO puede ser requerida en CREATE, nunca en EDIT
    ->required(function (string $operation, Get $get): bool {
        if ($operation !== 'create') {
            return false; // 👈 en edición nunca es obligatoria
        }

        // Si quieres que en creación tampoco sea obligatoria, simplemente devuelve false aquí
        $state = $get('estado');
        $estadoEnum = $state instanceof LeadEstadoEnum
            ? $state
            : LeadEstadoEnum::tryFrom($state);

        return $estadoEnum instanceof LeadEstadoEnum &&
            $estadoEnum !== LeadEstadoEnum::SIN_GESTIONAR &&
            ! $estadoEnum->isConvertido() &&
            $estadoEnum !== LeadEstadoEnum::DESCARTADO;
    })
    ->visible(function (Get $get): bool {
        $state = $get('estado');
        $estadoEnum = $state instanceof LeadEstadoEnum
            ? $state
            : LeadEstadoEnum::tryFrom($state);

        return $estadoEnum instanceof LeadEstadoEnum &&
            $estadoEnum !== LeadEstadoEnum::SIN_GESTIONAR &&
            ! $estadoEnum->isConvertido() &&
            $estadoEnum !== LeadEstadoEnum::DESCARTADO;
    })
    ->default(function (string $operation, ?Lead $record) {
        // En edición, si ya hay agenda, la mostramos tal cual
        if ($operation === 'edit' && $record?->agenda) {
            return $record->agenda;
        }

        // En creación (o sin agenda), proponemos "ahora"
        return now();
    })
   
    ->columnSpan(1),

                        DateTimePicker::make('fecha_gestion')
                            ->label('Inicio Gestión')
                            ->native(false)
                            ->readOnly()
                            ->nullable()
                            ->visible(function (Get $get): bool {
                                $state = $get('estado');
                                $estadoEnum = $state instanceof LeadEstadoEnum ? $state : LeadEstadoEnum::tryFrom($state);
                                return $estadoEnum instanceof LeadEstadoEnum &&
                                    $estadoEnum !== LeadEstadoEnum::SIN_GESTIONAR &&
                                    !$estadoEnum->isConvertido() &&
                                    $estadoEnum !== LeadEstadoEnum::DESCARTADO;
                            })
                            ->helperText('Se actualiza automáticamente')
                            ->columnSpan(1),

                            DateTimePicker::make('fecha_cierre')
                            ->label('Fecha de Cierre')
                            ->native(false)
                            ->readOnly()
                            ->helperText('Se establece automáticamente cuando el lead se convierte o se descarta.')
                            ->visible(fn (Get $get) => filled($get('fecha_cierre')))
                            ->columnSpan(1),

                        Select::make('motivo_descarte_id')
                            ->label('Motivo de Descarte')
                            ->relationship('motivoDescarte', 'nombre', fn (Builder $query) => $query->where('activo', true))
                            ->searchable()
                            ->preload()
                            ->visible(function (Get $get): bool {
                                $estado = $get('estado');
                                $estadoEnum = $estado instanceof LeadEstadoEnum ? $estado : LeadEstadoEnum::tryFrom($estado);
                                return $estadoEnum === LeadEstadoEnum::DESCARTADO;
                            })
                            ->required(function (Get $get): bool {
                                $estado = $get('estado');
                                $estadoEnum = $estado instanceof LeadEstadoEnum ? $estado : LeadEstadoEnum::tryFrom($estado);
                                return $estadoEnum === LeadEstadoEnum::DESCARTADO;
                            })
                            ->columnSpan(1),

                        Textarea::make('observacion_cierre')
                             ->label('Observaciones de Cierre')
                             ->visible(function (Get $get): bool {
                                $state = $get('estado');
                                $estadoEnum = null;
                            
                                if ($state instanceof LeadEstadoEnum) {
                                    $estadoEnum = $state;
                                } elseif (is_string($state)) {
                                    $estadoEnum = LeadEstadoEnum::tryFrom($state);
                                }
                            
                                // Es visible solo si tenemos un Enum válido Y ese Enum es final
                                return !is_null($estadoEnum) && $estadoEnum->isFinal();
                            })
                             ->nullable()
                             ->rows(3)
                             ->columnSpanFull(), // Ocupa todo el ancho

                       
                       


                    ])


            ]);
    }

    

public static function infolist(Infolist $infolist): Infolist
{
    return $infolist->schema([

        Grid::make(3)->schema([
            // Info básica
            InfoSection::make('Información del Lead')
                ->schema([
                    TextEntry::make('nombre')
                        ->label(new HtmlString('<span class="font-semibold">👤 Nombre</span>'))
                        ->columnSpan(2),

                    TextEntry::make('tfn')
                        ->label(new HtmlString('<span class="font-semibold">📞 Teléfono</span>'))
                        ->copyable(),

                    TextEntry::make('email')
                        ->label(new HtmlString('<span class="font-semibold">✉️ Email</span>'))
                        ->copyable()
                        ->html()
                        ->getStateUsing(fn (Lead $record) => new HtmlString(
                            '<span class="whitespace-normal break-all">' . e($record->email) . '</span>'
                        ))
                        ->columnSpanFull(),

                    TextEntry::make('demandado')
                        ->label(new HtmlString('<span class="font-semibold">Demandado</span>'))
                        ->color('info')
                        ->copyable()
                        ->columnSpan(3),
                ])
                ->columns(3)
                ->columnSpan(1),

            // Estado y asignación
            InfoSection::make('Estado & Asignación')
                ->schema([
                    TextEntry::make('creador.full_name')
                        ->badge()
                        ->color('gray')
                        ->label(new HtmlString('<span class="font-semibold">🧑‍💻 Creado por</span>')),

                    TextEntry::make('created_at')
                        ->label(new HtmlString('<span class="font-semibold">🕒📅 Fecha de creación</span>'))
                        ->dateTime('d/m/y H:i'),

                    TextEntry::make('fecha_gestion')
                        ->label(new HtmlString('<span class="font-semibold">🔛📅 Comienzo gestión</span>'))
                        ->dateTime('d/m/y H:i'),

                    TextEntry::make('asignado_display')
                        ->label(new HtmlString('<span class="font-semibold">📌 Asignado a</span>'))
                        ->badge()
                        ->getStateUsing(function (Lead $record): string {
                            return $record->asignado?->full_name ?? '⚠️ Sin Asignar';
                        })
                        ->color(function (string $state): string {
                            return $state === '⚠️ Sin Asignar' ? 'warning' : 'info';
                        }),


                        ///////////////////////////////////////////
          TextEntry::make('estado')
                ->label(new HtmlString('<span class="font-semibold">Estado Actual</span>'))
                ->badge()
                ->color(fn (?LeadEstadoEnum $state): string => match ($state) {
                    LeadEstadoEnum::SIN_GESTIONAR           => 'gray',
                    LeadEstadoEnum::INTENTO_CONTACTO        => 'warning',
                    LeadEstadoEnum::CONTACTADO              => 'info',
                    LeadEstadoEnum::ANALISIS_NECESIDADES    => 'primary',
                    LeadEstadoEnum::ESPERANDO_INFORMACION   => 'warning',
                    LeadEstadoEnum::PROPUESTA_ENVIADA       => 'info',
                    LeadEstadoEnum::EN_NEGOCIACION          => 'primary',

                    // Estados internos convertidos (se muestran con color si llegan por lógica externa)
                    LeadEstadoEnum::CONVERTIDO              => 'primary',
                    LeadEstadoEnum::CONVERTIDO_ESPERA_DATOS => 'warning',
                    LeadEstadoEnum::CONVERTIDO_ESPERA_FIRMA => 'warning',
                    LeadEstadoEnum::CONVERTIDO_FIRMADO      => 'success',

                    LeadEstadoEnum::DESCARTADO              => 'danger',
                    default                                  => 'gray',
                })
                ->formatStateUsing(fn (?LeadEstadoEnum $state): string => $state?->getLabel() ?? 'Desconocido')
                ->suffixAction(
                    ActionInfolist::make('cambiar_estado')
    ->label('')
    ->icon('heroicon-m-arrow-path')
    ->color('primary')
    ->tooltip(fn (Lead $record): ?string => ($record->asignado_id !== null && ! $record->estado?->isFinal()) ? 'Cambiar estado' : null)
    ->visible(fn (Lead $record): bool => $record->asignado_id !== null && ! $record->estado?->isFinal())
    ->modalHeading(fn(?Lead $record): string => "Cambiar Estado de " . ($record?->nombre ?? 'este lead'))
    ->modalSubmitActionLabel('Guardar y Procesar')
    ->modalWidth('3xl') // Un poco más ancho para ver bien los totales
    ->form([
        // 1. Estado Nuevo
        Select::make('estado_nuevo')
            ->label('Nuevo estado')
            ->options(function () {
                $ocultos = [LeadEstadoEnum::CONVERTIDO_ESPERA_DATOS, LeadEstadoEnum::CONVERTIDO_ESPERA_FIRMA, LeadEstadoEnum::CONVERTIDO_FIRMADO];
                return collect(LeadEstadoEnum::cases())
                    ->reject(fn ($e) => in_array($e, $ocultos, true))
                    ->mapWithKeys(fn ($e) => [$e->value => $e->getLabel()])
                    ->all();
            })
            ->required()
            ->live()
            ->native(false)
            ->default(fn (?Lead $record): ?string => $record?->estado?->value)
            ->columnSpanFull(),

        // 2. Modo (Solo si es Convertido)
        Radio::make('modo_convertido')
            ->label('¿Cómo quieres convertir este lead?')
            ->options([
                'manual'     => 'Manual (Crear Cliente/Venta a mano)',
                'automatico' => 'Automático (Enviar formulario y contrato)',
            ])
            ->inline(false)
            ->required(fn (Get $get) => LeadEstadoEnum::tryFrom($get('estado_nuevo') ?? '') === LeadEstadoEnum::CONVERTIDO)
            ->visible(fn (Get $get) => LeadEstadoEnum::tryFrom($get('estado_nuevo') ?? '') === LeadEstadoEnum::CONVERTIDO)
            ->live(),

        // 3. REPEATER DE SERVICIOS
        \Filament\Forms\Components\Repeater::make('servicios_venta')
            ->label('Configuración de la Venta')
            ->helperText('Precios oficiales según catálogo. Para aplicar descuentos, usa el modo Manual.')
            ->addActionLabel('Añadir servicio')
            ->visible(fn (Get $get) =>
                LeadEstadoEnum::tryFrom($get('estado_nuevo') ?? '') === LeadEstadoEnum::CONVERTIDO &&
                $get('modo_convertido') === 'automatico'
            )
            ->required(fn (Get $get) =>
                LeadEstadoEnum::tryFrom($get('estado_nuevo') ?? '') === LeadEstadoEnum::CONVERTIDO &&
                $get('modo_convertido') === 'automatico'
            )
            ->columns(4) // 4 Columnas para: Servicio | Unidades | Precio | Subtotal (visual)
            ->live() // ¡IMPORTANTE! Para que los totales de abajo se actualicen al tocar algo
            ->schema([
                Select::make('servicio_id')
                    ->label('Servicio')
                    ->options(Servicio::query()->where('activo', true)->pluck('nombre', 'id'))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        if ($state) {
                            $svc = Servicio::find($state);
                            if ($svc) {
                                $set('precio', $svc->precio_base);
                                // Guardamos el TIPO en un campo oculto para facilitar la suma final
                                $set('tipo_hidden', $svc->tipo->value ?? 'recurrente'); 
                            }
                        } else {
                            $set('precio', 0);
                            $set('tipo_hidden', 'recurrente');
                        }
                    })
                    ->columnSpan(2), // Ocupa 2 columnas

                // Campo UNIDADES
                TextInput::make('unidades')
                    ->label('Uds.')
                    ->numeric()
                    ->default(1)
                    ->minValue(1)
                    ->required()
                    ->live(onBlur: true) // Actualiza al salir del campo
                    ->columnSpan(1),

                TextInput::make('precio')
                    ->label('Precio/Ud')
                    ->numeric()
                    ->prefix('€')
                    ->readOnly() // Precio bloqueado
                    ->dehydrated()
                    ->columnSpan(1),
                
                // Campo Oculto para saber si es recurrente o único sin consultar DB
                Hidden::make('tipo_hidden')->default('recurrente'),
            ]),

        // 4. SECCIÓN DE TOTALES (Cálculo en tiempo real)
        Section::make()
            ->schema([
                Placeholder::make('total_resumen')
                    ->label('')
                    ->content(function (Get $get) {
                        $items = collect($get('servicios_venta') ?? []);
                        
                        // Calculamos totales separando por tipo
                        $totalRecurrente = $items->sum(fn ($i) => 
                            ($i['tipo_hidden'] ?? 'recurrente') === 'recurrente' 
                                ? ($i['precio'] * ($i['unidades'] ?? 1)) 
                                : 0
                        );

                        $totalUnico = $items->sum(fn ($i) => 
                            ($i['tipo_hidden'] ?? '') === 'unico' 
                                ? ($i['precio'] * ($i['unidades'] ?? 1)) 
                                : 0
                        );

                        return new \Illuminate\Support\HtmlString("
                            <div style='display:flex; justify-content:space-between; align-items:center; gap:20px;'>
                                <div style='text-align:right; flex:1;'>
                                    <div style='font-size:0.85rem; color:#6b7280; text-transform:uppercase;'>Total Mensual (Recurrente)</div>
                                    <div style='font-size:1.5rem; font-weight:800; color:#0ea5e9;'>".number_format($totalRecurrente, 2, ',', '.')." €</div>
                                </div>
                                <div style='width:1px; height:40px; background:#e5e7eb;'></div>
                                <div style='text-align:right; flex:1;'>
                                    <div style='font-size:0.85rem; color:#6b7280; text-transform:uppercase;'>Total Pago Único</div>
                                    <div style='font-size:1.5rem; font-weight:800; color:#16a34a;'>".number_format($totalUnico, 2, ',', '.')." €</div>
                                </div>
                            </div>
                        ");
                    }),
            ])
            ->visible(fn (Get $get) =>
                LeadEstadoEnum::tryFrom($get('estado_nuevo') ?? '') === LeadEstadoEnum::CONVERTIDO &&
                $get('modo_convertido') === 'automatico'
            ),

        // ... Resto de campos (Confirmación, Descarte, Obs) siguen igual ...
       // 4. Confirmación (OBLIGATORIA)
        Toggle::make('confirmar_convertido')
            ->label('He revisado las opciones y servicios que el cliente necesita.')
            ->helperText('Al guardar y procesar, el cliente recibirá un email para que rellene los datos de su ficha y firme el contrato, por lo que estos servicios se activarán automáticamente.')
            ->inline(false)
            ->onColor('success') // Se pone verde al aceptar
            // 👇 ESTA ES LA CLAVE: Regla 'accepted' obliga a que esté en ON
            ->rule('accepted') 
            // Validación de visibilidad (igual que antes)
            ->visible(fn (Get $get) =>
                LeadEstadoEnum::tryFrom($get('estado_nuevo') ?? '') === LeadEstadoEnum::CONVERTIDO &&
                $get('modo_convertido') === 'automatico'
            ),

        Select::make('motivo_descarte_id')
            ->label('Motivo de Descarte')
            ->relationship('motivoDescarte', 'nombre', fn (Builder $q) => $q->where('activo', true))
            ->visible(fn (Get $get) => LeadEstadoEnum::tryFrom($get('estado_nuevo') ?? '') === LeadEstadoEnum::DESCARTADO)
            ->required(fn (Get $get) => LeadEstadoEnum::tryFrom($get('estado_nuevo') ?? '') === LeadEstadoEnum::DESCARTADO)
            ->columnSpanFull(),

        Textarea::make('observacion_cierre')
            ->label('Observaciones')
            ->visible(fn (Get $get) => LeadEstadoEnum::tryFrom($get('estado_nuevo') ?? '')?->isFinal())
            ->required(fn (Get $get) => LeadEstadoEnum::tryFrom($get('estado_nuevo') ?? '') === LeadEstadoEnum::DESCARTADO)
            ->columnSpanFull(),
    ])
 ->action(function (array $data, Lead $record) {
        $nuevo = LeadEstadoEnum::tryFrom($data['estado_nuevo'] ?? '');
        if (!$nuevo) return;

        // --- CASO 1: CONVERTIDO ---
        if ($nuevo === LeadEstadoEnum::CONVERTIDO) {

            // A) MODO AUTOMÁTICO
            if (($data['modo_convertido'] ?? '') === 'automatico') {
                
                $formType = 'alta_autonomo_fiscal_recurrente'; // Tipo por defecto
                
                // 1. Procesar servicios, obtener precios reales y DETECTAR TIPO DE FORMULARIO
                $itemsServicios = collect($data['servicios_venta'] ?? [])->map(function ($item) use (&$formType) {
                    // Buscamos el servicio fresco de la BD (Precio seguro)
                    $svc = \App\Models\Servicio::find($item['servicio_id']);
                    
                    // Valores por defecto si no existe
                    $precioReal = $svc ? $svc->precio_base : 0;
                    $nombreSvc  = $svc ? strtolower($svc->nombre) : '';
                    $unidades   = intval($item['unidades'] ?? 1);

                    // --- LÓGICA DE DETECCIÓN INTELIGENTE ---
                    // Prioridad 1: Constitución de Sociedad (La más compleja, manda sobre todo)
                    if (str_contains($nombreSvc, 'sociedad') || str_contains($nombreSvc, 'sl') || str_contains($nombreSvc, 'mercantil') || str_contains($nombreSvc, 'constitución')) {
                        $formType = 'creacion_sociedad';
                    }
                    // Prioridad 2: Capitalización (Si no hemos detectado ya sociedad)
                    elseif ($formType !== 'creacion_sociedad' && (str_contains($nombreSvc, 'capitaliza') || str_contains($nombreSvc, 'pago único'))) {
                        $formType = 'capitalizacion';
                    }
                    // Prioridad 3: Alta Autónomo (Si es específico de alta y no es lo anterior)
                    elseif ($formType === 'alta_autonomo_fiscal_recurrente' && str_contains($nombreSvc, 'alta') && str_contains($nombreSvc, 'autónomo')) {
                        $formType = 'alta_autonomo';
                    }

                    return [
                        'servicio_id'         => $svc->id ?? $item['servicio_id'],
                        'nombre'              => $svc->nombre ?? 'Servicio',
                        'tipo'                => $svc->tipo->value ?? 'recurrente',
                        'precio_base'         => $precioReal, // Precio blindado de la BD
                        'unidades'            => $unidades,
                        'total_linea'         => $precioReal * $unidades,
                        'es_tarifa_principal' => ($svc->tipo->value ?? '') === 'recurrente',
                        'es_alta_autonomo'    => false,
                    ];
                })->toArray();

                if (empty($itemsServicios)) {
                    Notification::make()->title('Error')->body('Debes añadir al menos un servicio.')->danger()->send();
                    return;
                }

                // 2. Crear o Actualizar Link con el form_type detectado
                $link = LeadConversionLink::active()->where('lead_id', $record->id)->first();
                
                if (!$link) {
                    // Si es nuevo, lo creamos con el tipo detectado
                    $link = LeadConversionLink::createForLead($record, $formType);
                } else {
                    // Si ya existía, actualizamos el tipo
                    $meta = $link->meta ?? [];
                    $meta['form_type'] = $formType; // <--- Actualizamos el tipo
                    $link->meta = $meta;
                    $link->save();
                }

                // 3. Guardar el Blueprint (Qué se vende) en el Link
                $meta = $link->meta ?? [];
                $meta['sale_blueprint'] = [
                    'modo'      => 'automatico',
                    'servicios' => $itemsServicios,
                ];
                $link->meta = $meta;
                $link->save();

                // 4. Actualizar Lead y Enviar
                $record->estado = LeadEstadoEnum::CONVERTIDO_ESPERA_DATOS;
                $record->fecha_cierre = null;
                $record->save();

                try {
                    // Enviar Email
                    \Illuminate\Support\Facades\Mail::to($record->email)
                        ->send(new LeadConversionLinkMail($record, $link));
                    
                    // A) Log Técnico
                    \App\Models\LeadAutoEmailLog::create([
                        'lead_id'             => $record->id,
                        'estado'              => $record->estado->value,
                        'intento'             => 1,
                        'template_identifier' => 'conversion_link_auto',
                        'subject'             => 'Completa tu alta con AsesorFy',
                        'body_preview'        => 'Enlace al formulario de alta (Automático)...',
                        'scheduled_at'        => now(),
                        'sent_at'             => now(),
                        'status'              => 'sent',
                        'mail_driver'         => config('mail.default'),
                        'triggered_by_user_id'=> auth()->id(),
                        'trigger_source'      => 'manual_action_filament',
                    ]);

                    // B) Comentario en el Muro
                    $record->comentarios()->create([
                        'user_id'   => 9999,
                        'contenido' => "🚀 🔗 Enlace de alta (Automático) enviado correctamente a {$record->email}.",
                    ]);
                    
                    Notification::make()
                        ->title('Proceso Automático Iniciado')
                        ->body("Formulario enviado tipo: " . strtoupper(str_replace('_', ' ', $formType)))
                        ->success()
                        ->send();
                } catch (\Exception $e) {
                    Notification::make()->title('Error envío email')->body($e->getMessage())->danger()->send();
                }
                return;
            }

            // B) MODO MANUAL
            return redirect($record->cliente_id 
                ? \App\Filament\Resources\VentaResource::getUrl('create', ['cliente_id' => $record->cliente_id, 'lead_id' => $record->id])
                : \App\Filament\Resources\ClienteResource::getUrl('create', ['lead_id' => $record->id, 'razon_social' => $record->nombre, 'email' => $record->email, 'telefono' => $record->tfn, 'next' => 'sale']));
        }

        // --- CASO 2: OTROS ESTADOS ---
        $record->estado = $nuevo;
        $comentarioBase = 'Cambio de estado a: ' . $nuevo->getLabel();

        if ($nuevo->isFinal()) {
            $record->fecha_cierre = now();
            if ($nuevo === LeadEstadoEnum::DESCARTADO) {
                $record->motivo_descarte_id = $data['motivo_descarte_id'] ?? null;
                $record->observacion_cierre = $data['observacion_cierre'] ?? null;
                
                // Añadimos info extra al comentario si es descarte
                if (!empty($data['motivo_descarte_id'])) {
                    $motivo = \App\Models\MotivoDescarte::find($data['motivo_descarte_id']);
                    if ($motivo) $comentarioBase .= ' - Motivo: ' . $motivo->nombre;
                }
            }
        } else {
            $record->fecha_cierre = null;
        }

        $record->save();
        
        // Guardar comentario de historial
        $record->comentarios()->create([
            'user_id' => auth()->id(),
            'contenido' => $comentarioBase . ($data['observacion_cierre'] ? "\nObs: ".$data['observacion_cierre'] : ''),
        ]);

        Notification::make()->title('Estado actualizado')->success()->send();
    }),
                    ),



        //////////////////////////////////////////////////////////////////////////////////////////////////////
           TextEntry::make('venta_asociada')
                        ->label(new HtmlString('<span class="font-semibold">Venta Asociada</span>'))
                        ->getStateUsing(fn (Lead $record): string =>
                            '🔗 Ver venta #' . $record->ventas->first()->id
                        )
                        ->visible(fn (Lead $record): bool =>
                            $record->ventas->isNotEmpty()
                        )
                        ->url(fn (Lead $record): string =>
                            VentaResource::getUrl('edit', ['record' => $record->ventas->first()->id])
                        )
                        ->openUrlInNewTab()
                        ->badge()
                        ->color('warning'),
                ])

                ->columns(3)
                ->columnSpan(1),

            // Agenda
            InfoSection::make('Agenda & Gestión')
                ->schema([
                    TextEntry::make('updated_at')
                        ->label(new HtmlString('<span class="font-semibold">🔄📅 Lead Actualizado</span>'))
                        ->dateTime('d/m/y H:i'),

                    TextEntry::make('agenda')
                        ->label(new HtmlString('<span class="font-semibold">📆 Próxima cita</span>'))
                        ->dateTime('d/m/y H:i')
                        ->suffixAction(
                            ActionInfolist::make('reagendar')
                                ->icon('heroicon-m-calendar-days')
                                ->form([
                                    DateTimePicker::make('agenda')
                                        ->label('Nueva fecha de agenda')
                                        ->displayFormat('d/m/Y H:i')
                                        ->native(false)
                                        ->default(fn (Lead $record) => $record->agenda ?? now())
                                        ->minutesStep(30),
                                ])
                                ->action(function (array $data, Lead $record) {
                                    $record->agenda = $data['agenda'];
                                    $record->save();

                                    $fechaFormateada = \Carbon\Carbon::parse($data['agenda'])->format('d/m/Y H:i');

                                    $record->comentarios()->create([
                                        'user_id' => auth()->id(),
                                        'contenido' => '📅 Nueva agenda fijada para: ' . $fechaFormateada,
                                    ]);

                                    Notification::make()
                                        ->title('✅ Agenda actualizada')
                                        ->body('Se ha registrado la nueva fecha de agenda correctamente.')
                                        ->success()
                                        ->send();
                                })
                        ),

                    TextEntry::make('autospam_activo')
                        ->label('🤖 Autospam IA Boot Fy')
                        ->badge()
                        ->formatStateUsing(fn (?bool $state): string => $state ? 'Activo' : 'Desactivado')
                        ->color(fn (?bool $state): string => $state ? 'success' : 'gray')
                        ->icon(fn (?bool $state): ?string => $state ? 'heroicon-m-bug-ant' : 'heroicon-m-bell-slash')
                        ->suffixAction(
                            ActionInfolist::make('toggleAutospam')
                                ->icon(fn (Lead $record): string => $record->autospam_activo
                                    ? 'heroicon-m-no-symbol'
                                    : 'heroicon-m-check'
                                )
                                ->color(fn (Lead $record): string => $record->autospam_activo ? 'danger' : 'success')
                                ->tooltip(fn (Lead $record): string => $record->autospam_activo
                                    ? 'Desactivar autospam'
                                    : 'Activar autospam'
                                )
                                ->action(function (Lead $record): void {
                                    $record->update([
                                        'autospam_activo' => ! $record->autospam_activo,
                                    ]);
                                })
                        ),

                    // --- Interacciones ---
                    InfoSection::make('Interacciones')
                        ->schema([

                            // Llamadas
                            TextEntry::make('llamadas')
                                ->label('📞 Llamadas')
                                ->size('xl')
                                ->weight('bold')
                                ->alignment(Alignment::Center)
                                ->suffixAction(
                                    ActionInfolist::make('add_llamada')
                                        ->icon('heroicon-m-phone-arrow-up-right')
                                        ->color('primary')
                                        ->form([
                                            Toggle::make('respuesta')
                                                ->label('Contestado')
                                                ->default(false)
                                                ->helperText('Marca si el lead ha contestado la llamada.')
                                                ->live(),

                                            Textarea::make('comentario')
                                                ->label('Comentario')
                                                ->rows(3)
                                                ->hint('Describe brevemente la llamada.')
                                                ->visible(fn (Get $get) => $get('respuesta') === true)
                                                ->required(fn (Get $get) => $get('respuesta') === true)
                                                ->maxLength(500),

                                            Toggle::make('cambiar_a_intento_contacto')
                                                ->label('Cambiar estado a "Intento de contacto" y arrancar secuencia IA Boot Fy, se envía email automático')
                                                ->helperText('Solo se aplica si el lead está SIN GESTIONAR y no ha contestado.')
                                                ->default(true)
                                                ->visible(function (Get $get, ?Lead $record): bool {
                                                    return $record?->estado === LeadEstadoEnum::SIN_GESTIONAR
                                                        && $get('respuesta') === false;
                                                })
                                                ->live(),

                                            Select::make('nuevo_estado')
                                                ->label('Nuevo estado del lead')
                                                ->options(LeadEstadoEnum::class)
                                                ->visible(function (Get $get, ?Lead $record): bool {
                                                    return $record?->estado === LeadEstadoEnum::SIN_GESTIONAR
                                                        && $get('respuesta') === true;
                                                })
                                                ->required(function (Get $get, ?Lead $record): bool {
                                                    return $record?->estado === LeadEstadoEnum::SIN_GESTIONAR
                                                        && $get('respuesta') === true;
                                                })
                                                ->live(),

                                            Toggle::make('agendar')
                                                ->label('Agendar nueva llamada')
                                                ->default(false)
                                                ->helperText('Programa una nueva cita de seguimiento.')
                                                ->live(),

                                            DateTimePicker::make('agenda')
                                                ->label('Fecha y hora de la nueva llamada')
                                                ->minutesStep(30)
                                                ->seconds(false)
                                                ->native(false)
                                                ->visible(fn (Get $get) => $get('agendar') === true)
                                                ->after(now()),
                                        ])
                                        ->modalHeading('Registrar llamada')
                                        ->modalSubmitActionLabel('Registrar llamada')
                                        ->modalWidth('lg')
                                        ->action(function (array $data, Lead $record) {
                                            $currentUser = Auth::user();
                                            $userName = $currentUser?->name ?? 'Usuario';

                                            $record->increment('llamadas');

                                            $comentarioTextoInicial = "Llamada registrada por {$userName}.";
                                            if (($data['respuesta'] ?? false) === true) {
                                                $comentarioTextoInicial .= " [Contestada]";
                                                if (!empty($data['comentario'])) {
                                                    $comentarioTextoInicial .= " - Observación: " . $data['comentario'];
                                                }
                                            } else {
                                                $comentarioTextoInicial .= " [📞Sin respuesta]";
                                            }

                                            $agendaActualizada = false;
                                            $nuevaAgendaEstablecida = false;
                                            if (isset($data['agendar']) && $data['agendar'] === true) {
                                                if (isset($data['agenda']) && filled($data['agenda'])) {
                                                    try {
                                                        $nuevaFechaAgenda = Carbon::parse($data['agenda']);
                                                        $record->agenda = $nuevaFechaAgenda;
                                                        $record->save();
                                                        $agendaActualizada = true;
                                                        $nuevaAgendaEstablecida = true;
                                                    } catch (\Exception $e) {
                                                        Log::error('Error al procesar fecha de agenda en llamada para Lead ID '.$record->id.': '.$e->getMessage());
                                                        Notification::make()->title('Error al procesar fecha')->body('La fecha de agenda proporcionada no es válida.')->danger()->send();
                                                    }
                                                }
                                            }

                                            $comentarioTextoFinal = $comentarioTextoInicial;
                                            if ($nuevaAgendaEstablecida && $record->agenda instanceof Carbon) {
                                                $textoRelativo   = $record->agenda->diffForHumans();
                                                $fechaFormateada = $record->agenda->isoFormat('dddd D [de] MMMM, HH:mm');
                                                $comentarioTextoFinal .= "\n---\nPróximo seguimiento agendado: {$textoRelativo} (el {$fechaFormateada}).";
                                            }

                                            try {
                                                $record->comentarios()->create([
                                                    'user_id'   => $currentUser->id,
                                                    'contenido' => $comentarioTextoFinal,
                                                ]);
                                            } catch (\Exception $e) {
                                                Log::error('Error al guardar comentario (acción Llamada): ' . $e->getMessage());
                                                Notification::make()->title('Error interno')->body('No se pudo guardar el comentario asociado.')->warning()->send();
                                            }

                                            $estadoOriginal = $record->estado;

                                            if ($estadoOriginal === LeadEstadoEnum::SIN_GESTIONAR) {
                                                if (($data['respuesta'] ?? false) === false && ($data['cambiar_a_intento_contacto'] ?? false) === true) {
                                                    $record->estado = LeadEstadoEnum::INTENTO_CONTACTO;
                                                    $record->save();
                                                }

                                                if (($data['respuesta'] ?? false) === true && !empty($data['nuevo_estado'])) {
                                                    $nuevoEnum = $data['nuevo_estado'] instanceof LeadEstadoEnum
                                                        ? $data['nuevo_estado']
                                                        : LeadEstadoEnum::tryFrom($data['nuevo_estado']);

                                                    if ($nuevoEnum) {
                                                        $record->estado = $nuevoEnum;
                                                        $record->save();
                                                    }
                                                }
                                            }

                                            Notification::make()
                                                ->title('Llamada registrada')
                                                ->success()
                                                ->send();

                                            if ($agendaActualizada) {
                                                Notification::make()
                                                    ->title('Agenda actualizada')
                                                    ->body('El próximo seguimiento ha sido modificado.')
                                                    ->info()
                                                    ->send();
                                            }
                                        }),
                                ),

                            // Emails
                            TextEntry::make('emails')
                                ->label('📧 Emails')
                                ->size('xl')
                                ->weight('bold')
                                ->alignment(Alignment::Center)
                                ->suffixAction(
                                    ActionInfolist::make('add_email')
                                        ->icon('heroicon-m-envelope-open')
                                        ->color('warning')
                                        ->form([
                                            Textarea::make('comentario')
                                                ->label('Comentario (opcional)')
                                                ->rows(3)
                                                ->hint('Describe el contenido del email enviado.')
                                                ->maxLength(500),

                                            Toggle::make('agendar')
                                                ->label('Agendar seguimiento')
                                                ->default(false)
                                                ->live(),

                                            DateTimePicker::make('agenda')
                                                ->label('Fecha de seguimiento')
                                                ->minutesStep(30)
                                                ->seconds(false)
                                                ->native(false)
                                                ->visible(fn (Get $get) => $get('agendar') === true)
                                                ->after(now()),

                                            Select::make('nuevo_estado_email')
                                                ->label('Nuevo estado del lead tras este email')
                                                ->options(LeadEstadoEnum::class)
                                                ->visible(fn (?Lead $record): bool =>
                                                    $record?->estado === LeadEstadoEnum::SIN_GESTIONAR
                                                )
                                                ->required(fn (?Lead $record): bool =>
                                                    $record?->estado === LeadEstadoEnum::SIN_GESTIONAR
                                                )
                                                ->live(),

                                            Radio::make('modo_envio')
                                                ->label('¿Quién envía este email?')
                                                ->options([
                                                    'manual' => 'Lo envío yo (email ya enviado)',
                                                    'boot'   => 'Que lo envíe Boot IA automáticamente',
                                                ])
                                                ->inline()
                                                ->required()
                                                ->visible(function (Get $get): bool {
                                                    $valor = $get('nuevo_estado_email');
                                                    if (! $valor) return false;

                                                    $enum = $valor instanceof LeadEstadoEnum
                                                        ? $valor
                                                        : LeadEstadoEnum::tryFrom($valor);

                                                    return $enum && in_array($enum, [
                                                        LeadEstadoEnum::INTENTO_CONTACTO,
                                                        LeadEstadoEnum::ESPERANDO_INFORMACION,
                                                    ], true);
                                                })
                                                ->live(),
                                        ])
                                        ->modalHeading('Registrar Email')
                                        ->modalSubmitActionLabel('Registrar Email')
                                        ->modalWidth('lg')
                                        ->action(function (array $data, Lead $record) {
                                            $comentarioTexto = '📧 Email enviado: ' . ($data['comentario'] ?? 'Sin comentario');
                                            $agenda = isset($data['agenda']) && filled($data['agenda'])
                                                ? Carbon::parse($data['agenda'])
                                                : null;

                                            $modoEnvio = $data['modo_envio'] ?? 'manual';
                                            $enviarConBoot = $modoEnvio === 'boot';

                                            $estadoOriginal = $record->getOriginal('estado');
                                            $estadoOriginalEnum = $estadoOriginal instanceof LeadEstadoEnum
                                                ? $estadoOriginal
                                                : LeadEstadoEnum::tryFrom($estadoOriginal);

                                            if ($estadoOriginalEnum === LeadEstadoEnum::SIN_GESTIONAR && !empty($data['nuevo_estado_email'])) {
                                                $nuevoEnum = $data['nuevo_estado_email'] instanceof LeadEstadoEnum
                                                    ? $data['nuevo_estado_email']
                                                    : LeadEstadoEnum::tryFrom($data['nuevo_estado_email']);

                                                if ($nuevoEnum) {
                                                    $record->estado = $nuevoEnum;
                                                    $record->saveQuietly();
                                                }
                                            }

                                            $estadoFinalEnum = $record->estado instanceof LeadEstadoEnum
                                                ? $record->estado
                                                : LeadEstadoEnum::tryFrom($record->estado);

                                            if (! $enviarConBoot) {
                                                LeadResource::registrarInteraccion($record, 'emails', $comentarioTexto, $agenda);
                                                $record->marcarInteraccionManual();

                                                if ($estadoFinalEnum && in_array($estadoFinalEnum, [
                                                    LeadEstadoEnum::INTENTO_CONTACTO,
                                                    LeadEstadoEnum::ESPERANDO_INFORMACION,
                                                ], true)) {
                                                    $record->registrarEnvioEmailEstado();
                                                }
                                            } else {
                                                if ($agenda) {
                                                    $record->agenda = $agenda;
                                                    $record->saveQuietly();

                                                    try {
                                                        $fechaFormateada = $agenda->isoFormat('dddd D [de] MMMM, HH:mm');
                                                        $record->comentarios()->create([
                                                            'user_id'   => auth()->id(),
                                                            'contenido' => "📅 Seguimiento agendado tras lanzar email IA: el {$fechaFormateada}.",
                                                        ]);
                                                    } catch (\Throwable $e) {
                                                        Log::error('Error al registrar comentario de agenda (modo Boot IA) para lead '.$record->id.': '.$e->getMessage());
                                                    }
                                                }

                                                if ($estadoFinalEnum && in_array($estadoFinalEnum, [
                                                    LeadEstadoEnum::INTENTO_CONTACTO,
                                                    LeadEstadoEnum::ESPERANDO_INFORMACION,
                                                ], true)) {
                                                    try {
                                                        \App\Jobs\SendLeadEstadoChangedEmailJob::dispatch(
                                                            $record->id,
                                                            $estadoFinalEnum->value
                                                        );
                                                    } catch (\Throwable $e) {
                                                        Log::error("Error al despachar SendLeadEstadoChangedEmailJob desde acción email para lead {$record->id}: ".$e->getMessage());

                                                        Notification::make()
                                                            ->title('Error enviando email IA')
                                                            ->body('Se ha registrado la acción, pero no se pudo lanzar el email automático.')
                                                            ->danger()
                                                            ->send();

                                                        return;
                                                    }
                                                }
                                            }

                                            Notification::make()
                                                ->title($enviarConBoot ? 'Email IA en cola de envío' : 'Email registrado')
                                                ->success()
                                                ->send();
                                        })
                                ),

                            // Chats
                            TextEntry::make('chats')
                                ->label('💬 Chats')
                                ->size('xl')
                                ->weight('bold')
                                ->alignment(Alignment::Center)
                                ->suffixAction(
                                    ActionInfolist::make('add_chat')
                                        ->icon('icon-whatsapp')
                                        ->color('success')
                                        ->form([
                                            Textarea::make('comentario')
                                                ->label('Comentario (opcional)')
                                                ->rows(3)
                                                ->hint('Describe el chat realizado.')
                                                ->maxLength(500),

                                            Toggle::make('agendar')
                                                ->label('Agendar seguimiento')
                                                ->default(false)
                                                ->live(),

                                            DateTimePicker::make('agenda')
                                                ->label('Fecha de seguimiento')
                                                ->minutesStep(30)
                                                ->seconds(false)
                                                ->native(false)
                                                ->visible(fn (Get $get) => $get('agendar') === true)
                                                ->after(now()),
                                        ])
                                        ->modalHeading('Registrar Chat')
                                        ->modalSubmitActionLabel('Registrar Chat')
                                        ->modalWidth('lg')
                                        ->action(function (array $data, Lead $record) {
                                            $comentarioTexto = '💬 Chat enviado: ' . ($data['comentario'] ?? 'Sin comentario.');
                                            $agenda = isset($data['agenda']) ? Carbon::parse($data['agenda']) : null;

                                            LeadResource::registrarInteraccion($record, 'chats', $comentarioTexto, $agenda);
                                        })
                                ),

                            // Otros
                            TextEntry::make('otros_acciones')
                                ->label('📎 Otros')
                                ->size('xl')
                                ->weight('bold')
                                ->alignment(Alignment::Center)
                                ->suffixAction(
                                    ActionInfolist::make('add_otro')
                                        ->icon('heroicon-m-paper-airplane')
                                        ->color('gray')
                                        ->form([
                                            Textarea::make('comentario')
                                                ->label('Comentario obligatorio en esta acción')
                                                ->rows(3)
                                                ->required()
                                                ->hint('Describe la acción realizada.')
                                                ->maxLength(500),

                                            Toggle::make('agendar')
                                                ->label('Agendar seguimiento')
                                                ->default(false)
                                                ->live(),

                                            DateTimePicker::make('agenda')
                                                ->label('Fecha de seguimiento')
                                                ->minutesStep(30)
                                                ->seconds(false)
                                                ->native(false)
                                                ->visible(fn (Get $get) => $get('agendar') === true)
                                                ->after(now()),
                                        ])
                                        ->modalHeading('Registrar Otra Acción')
                                        ->modalSubmitActionLabel('Registrar Acción')
                                        ->modalWidth('lg')
                                        ->action(function (array $data, Lead $record) {
                                            $comentarioTexto = '📎 Otra acción realizada: ' . ($data['comentario'] ?? 'Sin comentario.');
                                            $agenda = isset($data['agenda']) ? Carbon::parse($data['agenda']) : null;

                                            LeadResource::registrarInteraccion($record, 'otros_acciones', $comentarioTexto, $agenda);
                                        })
                                ),

                            TextEntry::make('total')
                                ->label('🔥 Total')
                                ->state(fn (Lead $record) => $record->llamadas + $record->emails + $record->chats + $record->otros_acciones)
                                ->size('xl')
                                ->weight('extrabold')
                                ->color('warning')
                                ->alignment(Alignment::Center),
                        ])
                        ->columns(5)
                        ->columnSpan(3),
                ])
                ->columns(3)
                ->columnSpan(1),
        ]),

        InfoSection::make('🤖 Autospam IA Boot Fy')
            ->description('Últimos envíos automáticos asociados a este lead (🤖IA / autospam).')
            ->headerActions([
                ActionInfolist::make('enviar_primer_email_ia')
                    ->label('Enviar primer email IA ahora')
                    ->visible(fn (Lead $record): bool => $record->puedeSugerirPrimerEmailIa())
                    ->icon('heroicon-m-sparkles')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Enviar primer email IA automático')
                    ->modalSubheading('Se enviará el primer email de la secuencia según el estado actual del lead.')
                    ->action(function (Lead $record): void {
                        \App\Jobs\SendLeadEstadoChangedEmailJob::dispatch(
                            $record->id,
                            $record->estado instanceof \App\Enums\LeadEstadoEnum
                                ? $record->estado->value
                                : (string) $record->estado
                        );

                        Notification::make()
                            ->title('Primer email IA en cola de envío')
                            ->body('Se ha lanzado el envío del primer email automático para este lead.')
                            ->success()
                            ->send();
                    }),
            ])
            ->schema([
                TextEntry::make('autospam_sugerencia')
                    ->label(false)
                    ->visible(fn (Lead $record): bool => $record->puedeSugerirPrimerEmailIa())
                    ->state(function (Lead $record): string {
                        return '
                            <div style="
                                background-color:#fef3c7;
                                border:1px solid #fbbf24;
                                color:#78350f;
                                padding:0.75rem 1rem;
                                border-radius:0.75rem;
                                display:flex;
                                align-items:center;
                                justify-content:space-between;
                                gap:1rem;
                                font-size:0.9rem;
                            ">
                                <div>
                                    <strong>Este lead nunca ha recibido un email automático IA.</strong><br>
                                    Tiene email y autospam activo, y como acabas de actualizar su email y antes no tenia, puedes iniciar la secuencia con el botón de arriba "Enviar primer email IA ahora" y que IA Boot Fy haga su magia :).
                                </div>
                            </div>
                        ';
                    })
                    ->html(),

                RepeatableEntry::make('autoEmailLogs')
                    ->label(false)
                    ->schema([
                        TextEntry::make('linea')
                            ->label(false)
                            ->html()
                            ->state(function (LeadAutoEmailLog $log): string {

                                $fecha = $log->sent_at?->format('d/m H:i')
                                    ?? $log->created_at?->format('d/m H:i')
                                    ?? '-';

                                $intento = $log->intento ?? 1;

                                $icono = match ($log->status) {
                                    'sent'         => '✅',
                                    'failed'       => '❌',
                                    'rate_limited' => '⏱️',
                                    'pending'      => '⏳',
                                    'skipped'      => '⏭️',
                                    default        => '✉️',
                                };

                                $estadoTexto = match ($log->status) {
                                    'sent'         => 'Enviado',
                                    'failed'       => 'Fallido',
                                    'rate_limited' => 'Rate limited',
                                    'pending'      => 'Pendiente',
                                    'skipped'      => 'Omitido',
                                    default        => ucfirst($log->status ?? 'Desconocido'),
                                };

                                $estadoColor = match ($log->status) {
                                    'sent'         => '#16a34a',
                                    'failed'       => '#dc2626',
                                    'rate_limited' => '#0ea5e9',
                                    'pending'      => '#d97706',
                                    'skipped'      => '#6b7280',
                                    default        => '#6b7280',
                                };

                                $asuntoCompleto = e($log->subject ?: '(sin asunto)');
                                $url = \App\Filament\Resources\LeadAutoEmailLogResource::getUrl('view', [
                                    'record' => $log->id,
                                ]);

                                return "
                                <div style='
                                    display:flex;
                                    align-items:center;
                                    gap:12px;
                                    padding:6px 12px;
                                    border-radius:8px;
                                    border:1px solid rgba(148,163,184,0.35);
                                    background-color:rgba(15,23,42,0.04);
                                    font-size:14px;
                                '>
                                    <span style='color:#6b7280;'>{$icono}</span>

                                    <span style='color:#6b7280;'>
                                        {$fecha}
                                    </span>

                                    <span style=\"
                                        background-color:rgba(59,130,246,0.10);
                                        color:#1d4ed8;
                                        padding:2px 7px;
                                        border-radius:999px;
                                        font-size:12px;
                                        font-weight:600;
                                    \">
                                        #{$intento}
                                    </span>

                                    <span style=\"
                                        background-color:{$estadoColor}20;
                                        color:{$estadoColor};
                                        padding:2px 7px;
                                        border-radius:999px;
                                        font-size:12px;
                                        font-weight:600;
                                    \">
                                        {$estadoTexto}
                                    </span>

                                    <a href=\"{$url}\" target=\"_blank\" style=\"
                                        margin-left:auto;
                                        color:#2563eb;
                                        text-decoration:underline;
                                        font-weight:500;
                                        white-space:normal;
                                    \">
                                        {$asuntoCompleto}
                                    </a>
                                </div>
                                ";
                            }),
                    ])
                    ->contained(false),

                TextEntry::make('ver_mas_logs')
                    ->label(false)
                    ->visible(fn (Lead $lead) => $lead->autoEmailLogs()->count() > 10)
                    ->html()
                    ->state(function (Lead $lead): string {
                        $url = \App\Filament\Resources\LeadAutoEmailLogResource::getUrl('index');
                        $total = $lead->autoEmailLogs()->count();

                        return "
                        <div style='margin-top:8px;font-size:13px;color:#4b5563;'>
                            Hay <strong>{$total}</strong> envíos automáticos para este lead.
                            <a href=\"{$url}\" target=\"_blank\" style=\"color:#2563eb;text-decoration:underline;\">
                                Ver todos en el log global
                            </a>
                        </div>
                        ";
                    }),
            ])
            ->visible(fn (Lead $record) =>
                $record->autoEmailLogs()->exists() || $record->puedeSugerirPrimerEmailIa()
            )
            ->collapsible()
            ->collapsed(),

//facturas

            // ...
        


InfoSection::make('Facturación y Pagos')
    ->icon('heroicon-o-currency-euro')
    ->description('Facturas generadas a partir de las ventas de este lead.')
    ->visible(fn (Lead $record) => $record->facturas()->exists())
    ->schema([
        RepeatableEntry::make('facturas') // relación Lead->facturas()
            ->label(false)
            ->contained(false)
            ->schema([
                Grid::make(6)->schema([
                    // 1. Número y enlace PDF
                    TextEntry::make('numero_factura')
                        ->label('Nº Factura')
                        ->icon('heroicon-m-document-text')
                        ->weight('bold')
                        ->color('primary')
                        ->url(fn ($record) => route('facturas.generar-pdf', $record)) // $record = Factura
                        ->openUrlInNewTab(),

                    // 2. Concepto (primer item) + tooltip con todos
                    TextEntry::make('items.0.descripcion')
                        ->label('Concepto')
                        ->limit(30)
                        ->tooltip(fn ($record) => $record->items
                            ? $record->items->pluck('descripcion')->filter()->implode(', ')
                            : null
                        ),

                    // 3. Importe total
                    TextEntry::make('total_factura')
                        ->label('Total')
                        ->money('EUR')
                        ->weight('bold'),

                    // 4. Estado (badge con label de tu enum)
                    TextEntry::make('estado')
                        ->label('Estado pago')
                        ->badge()
                        ->formatStateUsing(fn (FacturaEstadoEnum $state) => $state->getLabel())
                        ->color(fn (FacturaEstadoEnum $state) => match ($state) {
                            FacturaEstadoEnum::PAGADA         => 'success',
                            FacturaEstadoEnum::PENDIENTE_PAGO => 'warning',
                            FacturaEstadoEnum::IMPAGADA       => 'danger',
                            FacturaEstadoEnum::ANULADA        => 'gray',
                        }),

                    // 5. Fecha de emisión
                    TextEntry::make('fecha_emision')
                        ->label('Fecha')
                        ->date('d/m/Y')
                        ->color('gray'),

                    // 6. Acciones de pago (bonitas)
                    ViewEntry::make('acciones_pago')
                        ->label('Pago')
                        ->view('filament.resources.leads.partials.acciones-pago'),
                ]),
            ]),
    ])
    ->collapsible(),

    

// ...

            // SECCIÓN FACTURAS ASOCIADAS
      /*       InfoSection::make('Facturación y Pagos')
                ->icon('heroicon-o-currency-euro')
                ->description('Facturas generadas a partir de las ventas de este lead.')
                ->visible(fn (Lead $record) => $record->facturas()->exists()) // Solo si hay facturas
                ->schema([
                    RepeatableEntry::make('facturas')
                        ->label(false)
                        ->contained(false) // Para que quede limpio sin bordes extra
                        ->schema([
                            Grid::make(5)->schema([
                                // 1. Número y Enlace PDF
                                TextEntry::make('numero_factura')
                                    ->label('Nº Factura')
                                    ->icon('heroicon-m-document-text')
                                    ->weight('bold')
                                    ->color('primary')
                                    ->url(fn ($record) => route('facturas.generar-pdf', $record)) // Enlace directo al PDF
                                    ->openUrlInNewTab(),

                                // 2. Concepto (Resumen rápido)
                                TextEntry::make('items.0.descripcion') // Cogemos la primera línea como resumen
                                    ->label('Concepto')
                                    ->limit(30)
                                    ->tooltip(fn ($record) => $record->items->pluck('descripcion')->implode(', ')),

                                // 3. Importe
                                TextEntry::make('total_factura')
                                    ->label('Total')
                                    ->money('EUR')
                                    ->weight('bold'),

                                // 4. Estado (El semáforo)
                                TextEntry::make('estado')
                                    ->badge()
                                    ->label('Estado Pago')
                                    ->color(fn (\App\Enums\FacturaEstadoEnum $state) => match ($state) {
                                        \App\Enums\FacturaEstadoEnum::PAGADA         => 'success',
                                        \App\Enums\FacturaEstadoEnum::PENDIENTE_PAGO => 'danger', // Rojo para que llame la atención
                                        \App\Enums\FacturaEstadoEnum::ANULADA        => 'gray',
                                        \App\Enums\FacturaEstadoEnum::RECTIFICATIVA  => 'warning',
                                        default                                      => 'gray',
                                    }),

                                // 5. Fecha
                                TextEntry::make('fecha_emision')
                                    ->label('Fecha')
                                    ->date('d/m/Y')
                                    ->color('gray'),
                            ]),
                        ]),
                ])
                ->collapsible(), */



        InfoSection::make('🗨️ Comentarios')
            ->headerActions([
                ActionInfolist::make('anadir_comentario')
                    ->label('📝 Añadir comentario nuevo')
                    ->icon('heroicon-o-plus-circle')
                    ->color('warning')
                    ->modalHeading('Nuevo comentario')
                    ->modalSubmitActionLabel('Guardar comentario')
                    ->form([
                        Textarea::make('contenido')
                            ->label('Escribe el comentario')
                            ->required()
                            ->rows(4)
                            ->placeholder('Escribe aquí tu comentario...')
                    ])
                    ->action(function (array $data, Lead $record) {
                        $record->comentarios()->create([
                            'user_id' => auth()->id(),
                            'contenido' => $data['contenido'],
                        ]);

                        Notification::make()
                            ->title('Comentario guardado')
                            ->success()
                            ->send();
                    }),

                ActionInfolist::make('crear_cliente')
                    ->label('👤 Crear Cliente')
                    ->icon('heroicon-m-user-plus')
                    ->color('primary')
                    ->visible(fn (Lead $record) => $record->estado === LeadEstadoEnum::CONVERTIDO->value && ! $record->cliente)
                    ->url(fn (Lead $record) => ClienteResource::getUrl('create', [
                        'razon_social' => $record->nombre,
                        'email'        => $record->email,
                        'telefono'     => $record->tfn,
                        'lead_id'      => $record->id,
                        'comercial_id' => auth()->id(),
                    ])),
            ])
            ->schema([
                RepeatableEntry::make('comentarios')
                    ->label(false)
                    ->contained(false)
                    ->schema([
                        TextEntry::make('contenido')
                            ->html()
                            ->label(false)
                            ->state(function ($record) {
                                $usuario = $record->user?->name ?? 'Usuario';
                                $contenido = $record->contenido;
                                $fecha = $record->created_at?->format('d/m/Y H:i') ?? '';

                                // 🤖 DETECCIÓN DE BOT
                                // Si el ID es 9999 o el nombre contiene "Boot", cambiamos la cara
                                $esBot = $record->user_id === 9999 || str_contains(strtolower($usuario), 'boot');
                                $icono = $esBot ? '🤖' : '🧑‍💼';

                                // También podemos cambiar el color de fondo si es el Bot para diferenciarlo más
                                $fondo = $esBot ? '#e0f2fe' : '#dcfce7'; // Azulito para Bot, Verde para Humanos

                                return "
                                <div style='
                                    display: flex;
                                    align-items: center;
                                    gap: 1rem;
                                    background-color: {$fondo};
                                    color: #1f2937;
                                    padding: 0.75rem 1rem;
                                    border-radius: 1rem;
                                    margin: 0.5rem 0;
                                    font-size: 0.95rem;
                                    line-height: 1.4;
                                    flex-wrap: wrap;
                                '>
                                    <span style='font-weight: 600;'>{$icono} " . e($usuario) . "</span>
                                    <span>" . e($contenido) . "</span>
                                    <span style='font-size: 0.8rem; color: #6b7280; margin-left:auto;'>🕓 " . e($fecha) . "</span>
                                </div>
                                ";
                            })
                    ])
                    ->visible(fn (Lead $record) => $record->comentarios->isNotEmpty()),
            ]),
    ]);
}


      

// Método helper COMPLETO Y CORREGIDO para añadir info de agenda al comentario SOLO SI SE AGENDA

protected static function registrarInteraccion(Lead $record, string $campoContador, string $comentarioTextoInicial, ?Carbon $agenda = null): void
{
    // 1. Incrementamos el contador
    try {
        $record->increment($campoContador);
    } catch (\Exception $e) {
        Log::error("Error al incrementar contador '{$campoContador}' para Lead ID {$record->id}: " . $e->getMessage());
        Notification::make()->title('Error Interno')->body('No se pudo registrar la interacción.')->danger()->send();
        return;
    }

    // 2. Si nos pasan una nueva fecha de agenda, la actualizamos
    $agendaActualizada = false;
    $nuevaAgendaEstablecida = false; // Bandera para saber si se AGENDÓ algo en este paso
    if ($agenda) { // <-- Comprobamos si se PASÓ una fecha de agenda a este método
        try {
             $record->agenda = $agenda; // Usamos el objeto Carbon directamente
             $record->save(); // Guardamos el Lead para actualizar la agenda en la BD y en el objeto $record
             $agendaActualizada = true;
             $nuevaAgendaEstablecida = true; // Se estableció una nueva agenda en esta interacción
        } catch (\Exception $e) {
            Log::error('Error al actualizar fecha de agenda en registrarInteraccion para Lead ID '.$record->id.': '.$e->getMessage());
            Notification::make()->title('Error')->body('Fecha de agenda proporcionada no válida.')->danger()->send();
            // Continuamos, pero sin marcar como agendado si hubo error
            $agendaActualizada = false;
            $nuevaAgendaEstablecida = false;
        }
    }

    // --- 3. Construimos el texto FINAL del comentario (añadiendo info de agenda SOLO SI SE AGENDA) ---
    $comentarioTextoFinal = $comentarioTextoInicial; // Empezamos con el texto base de la acción

    // Añadimos información sobre la agenda SOLO si se estableció una nueva fecha en esta interacción
    if ($nuevaAgendaEstablecida) { // <-- Usamos la bandera
        $comentarioTextoFinal .= "\n---"; // Separador

         // Ahora $record->agenda ya tiene la fecha actualizada si el paso 2 tuvo éxito
         if ($record->agenda instanceof Carbon) { // Verificamos si ahora hay una fecha de agenda válida en el lead
             $textoRelativo = $record->agenda->diffForHumans();
             $fechaFormateada = $record->agenda->isoFormat('dddd D [de] MMMM, [a las] HH:mm');
             $comentarioTextoFinal .= "\nPróximo seguimiento agendado: {$textoRelativo} (el {$fechaFormateada}).";
         }
         // Si $nuevaAgendaEstablecida es true pero $record->agenda no es Carbon, es un caso de error ya notificado.
         // No añadimos texto de agenda en este caso.
    }
    // Si $nuevaAgendaEstablecida es false, simplemente no añadimos nada sobre la agenda.

    // --- 4. Creamos el comentario usando el texto final ---
    try {
        $comentario = new Comentario();
        $comentario->user_id = Auth::id();
        $comentario->contenido = $comentarioTextoFinal; // Usamos el texto FINAL
        $record->comentarios()->save($comentario);
    } catch (\Exception $e) {
        Log::error('Error al guardar comentario (helper): ' . $e->getMessage(), [
            'lead_id' => $record->id, 'user_id' => Auth::id(), 'contenido_length' => strlen($comentarioTextoFinal ?? '')
        ]);
        Notification::make()->title('Error interno')->body('No se pudo guardar el comentario asociado.')->warning()->send();
    }
    // --- Fin creación comentario ---



}


    public static function table(Table $table): Table
    {
       
        return $table
        ->paginated([25, 50, 100, 'all']) // Ajusta opciones si quieres
        ->striped()
        ->recordUrl(null)    // Esto quita la navegación al hacer clic en la fila
        ->poll(60) // Actualizar cada 60 segundos
        ->defaultSort('created_at', 'desc') // Ordenar por defecto
        ->columns([
            IconColumn::make('autospam_activo')
            ->label('IA')
            ->boolean()
            ->trueIcon('heroicon-o-bug-ant')
            ->falseIcon('heroicon-o-bug-ant'),
            // Columna Total Interacciones (Adaptada)
            TextColumn::make('total_interactions')
                ->label('Acciones') // Etiqueta corta
                ->tooltip('Total Interacciones (Llamadas + Emails + Chats + Otras)')
                ->state(function (Lead $record): int {
                     // Suma los contadores
                     return $record->llamadas + $record->emails + $record->chats + $record->otros_acciones;
                })
                ->numeric()
                ->size('2xl')
                ->weight('extrabold')
                ->color('warning')
                ->alignment(Alignment::Center),
            TextColumn::make('creador.full_name')
                ->label('Creado por')
                ->sortable()
                ->badge() 
                ->color('gray')
                   ->toggleable(isToggledHiddenByDefault: true),

            


            // Datos del Lead
            TextColumn::make('nombre')
             ->searchable(isIndividual: true)
                //->copyable()
                //->copyMessage('Nombre Copiado')
                 // Si existe cliente asociado, convierte el nombre en enlace a su ficha
                ->url(fn (Lead $record): ?string => 
                $record->cliente_id
                    ? ClienteResource::getUrl('view', ['record' => $record->cliente_id])
                    : null
                )
                // Color amarillo (warning) si es enlace, gris si no
                ->color(fn (Lead $record): ?string =>
                    $record->cliente_id
                        ? 'warning'
                        : null
                )
                // Abre en pestaña nueva solo cuando haya URL
                ->openUrlInNewTab(),
                
            TextColumn::make('email')
                ->searchable(isIndividual: true)
                ->copyable()
                ->copyMessage('Email Copiado'),
              
            TextColumn::make('tfn')
                ->label('Teléfono')
                ->searchable(isIndividual: true)
                ->copyable()
                ->copyMessage('Teléfono Copiado')
                ->icon('heroicon-m-phone'),
            TextColumn::make('procedencia.procedencia')
                ->label('Procedencia')
                ->badge()      
                ->sortable()
                ->searchable(),
            // Estado (Adaptado con Enum)
            TextColumn::make('estado')
                ->badge()
                ->formatStateUsing(fn (?LeadEstadoEnum $state): string => $state?->getLabel() ?? '-')
                ->color(fn (?LeadEstadoEnum $state): string => match ($state) {
                    LeadEstadoEnum::SIN_GESTIONAR => 'gray',
                    LeadEstadoEnum::INTENTO_CONTACTO => 'warning',
                    LeadEstadoEnum::CONTACTADO => 'info',
                    LeadEstadoEnum::ANALISIS_NECESIDADES => 'primary',
                    LeadEstadoEnum::ESPERANDO_INFORMACION => 'warning',
                    LeadEstadoEnum::PROPUESTA_ENVIADA => 'info',
                    LeadEstadoEnum::EN_NEGOCIACION => 'primary',
                    LeadEstadoEnum::CONVERTIDO => 'success',
                    LeadEstadoEnum::DESCARTADO => 'danger',
                    default => 'gray'
                })
                ->searchable() // Buscar por el valor string del estado
                ->sortable(),

            // Asignado y Procedencia
             // --- COLUMNA ASIGNADO (VERSIÓN DEBUG) ---
             TextColumn::make('asignado_display') // Usamos un nombre diferente para evitar conflictos con la relación
             ->label('Comercial asignado')
             ->badge() 
             ->getStateUsing(function (Lead $record): string {
                 // ***** ¡¡IMPORTANTE!! Cambia 'name' si tu atributo en User es 'full_name' u otro *****
                 return $record->asignado // Comprueba si la relación está cargada (si hay un usuario asignado)
                     ? $record->asignado->name // Si sí, devuelve el nombre
                     : '⚠️ Sin Asignar'; // Si no, devuelve el texto fijo (con emoji si quieres)
             })
             ->color(fn ($state) => str_contains($state, 'Sin Asignar') ? 'warning' : 'info')
             ->searchable(/* 
                query: function (Builder $query, string $search): Builder {
                    // Le decimos que busque Leads DONDE la relación 'asignado' EXISTA Y CUMPLA una condición:
                    // Que el campo 'name' (¡o 'full_name'!) de ese usuario asignado contenga el texto buscado.
                    // ***** ¡¡IMPORTANTE!! Cambia 'name' aquí si tu atributo en User es otro *****
                    return $query->orWhereHas('asignado', function (Builder $q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
                },
                isIndividual: true // Mantenemos la búsqueda individual para esta columna */
            )
            // --- FIN CORRECCIÓN ---
             ->sortable(['asignado.name'])         
             ->toggleable(isToggledHiddenByDefault: false),

           

            // Fechas Clave
             TextColumn::make('agenda')
                ->label('Agendado')
                ->dateTime('d/m/y H:i') // Quitar segundos si no son necesarios
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: false),

            TextColumn::make('fecha_gestion') // Campo renombrado
                 ->label('Gestionado el lead')
                 ->dateTime('d/m/y H:i')
                 ->sortable()
                 ->toggleable(isToggledHiddenByDefault: true),

             TextColumn::make('updated_at')
                ->label('Actualizado el lead')
                ->since() // Mostrar relativo (ej: 'hace 5 minutos')
                //->dateTime('d/m/y H:i') // O formato fijo
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: false),

             TextColumn::make('created_at')
                ->label('Creado en app')
                ->dateTime('d/m/y H:i')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true), // Oculta por defecto

            // Creador (Opcional)
           

        ])
        ->filters([
             // Filtros adaptados
          
          TernaryFilter::make('autospam_activo')
                ->label('Autospam')
                ->trueLabel('Activos')
                ->falseLabel('Desactivados')
                ->placeholder('Todos')
                ->indicateUsing(function (array $state): ?string {
                    $value = $state['value'] ?? null;

                    return match (true) {
                        $value === true,
                        $value === 1,
                        $value === '1'  => '🔔 Autospam activo',

                        $value === false,
                        $value === 0,
                        $value === '0'  => '🔕 Autospam desactivado',

                        default => null, // "Todos"
                    };
                }),
            SelectFilter::make('estado')
                 ->options(LeadEstadoEnum::class) // Usa el Enum (asegúrate que Enum tiene HasLabel)
                 ->multiple()
                 ->label('Estado del Lead'),

             SelectFilter::make('asignado_id')
                 ->label('Comercial Asignado')
                 ->relationship(
                     'asignado',
                     'name', // Ajusta a 'full_name' si es necesario
                     fn (Builder $query) => $query->whereHas('roles', fn (Builder $q) => $q->where('name', 'comercial'))
                 )
                 ->searchable()
                 ->preload()
                 ->multiple(),

             SelectFilter::make('procedencia_id')
                ->label('Procedencia del Lead')
                ->relationship('procedencia', 'procedencia') // Usa 'procedencia'
                ->multiple()
                ->preload()
                ->searchable(),

             DateRangeFilter::make('created_at')
                ->label('Fecha Creación'),

             DateRangeFilter::make('agenda')
                ->label('Fecha Agendada')
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
                // ->ranges([...]) // Puedes mantener tus rangos predefinidos

        ], layout: FiltersLayout::AboveContent) // Mantener layout
        ->filtersFormColumns(6) // Mantener columnas

        ->actions([ // Acciones de Fila
           

            Tables\Actions\ViewAction::make()
                ->label('') // Sin etiqueta, solo icono
                ->openUrlInNewTab() // Abrir en nueva pestaña
                ->tooltip('Ver Detalles'), // Texto al pasar el ratón

            Tables\Actions\EditAction::make()
                 ->label('')
                 ->tooltip('Editar Lead'),
            Tables\Actions\Action::make('llamar')
                 ->icon('heroicon-o-phone-arrow-up-right')
                 ->label('')
                 ->tooltip('Registrar Llamada y Opcionalmente Reagendar')
                 ->color('primary')
                 // --- Usamos form() directamente, sin Wizard ---
                 ->form([
                    // Placeholder mejorado con negrita
                    Placeholder::make('accion_info')
                        ->label('')
                        ->content('Vas a registrar una llamada realizada a este LEADS. Debes indicar cuando es la proxima agenda del mismo.'),
                
                    Toggle::make('actualizar_agenda')
                        ->label('Nuevo seguimiento')
                        ->helperText('Activa esto para establecer una nueva fecha y hora.')
                        ->onIcon('heroicon-m-calendar-days')
                        ->offIcon('heroicon-o-calendar')
                        ->live()
                        ->default(false)                    

                                        // --- HINT CON FORMATO HUMANO + FECHA ---
                        ->hint(function (?Lead $record): ?string {
                            if ($record?->agenda) {
                              // Calcula la diferencia legible para humanos
                                $humanDiff = $record->agenda->diffForHumans(); // Ej: "en 2 días", "hace 1 hora"

                                // Formatea la fecha/hora absoluta (usa isoFormat para nombres de mes/día en español)
                                $formattedDate = $record->agenda->isoFormat('dddd D [de] MMMM, H:mm'); // Ej: "sábado 19 de abril, 10:30"
                                // Alternativa más simple si no necesitas nombres: $record->agenda->format('d/m/Y H:i')
                                // Combina ambos en el texto del hint
                                return "Actualmente agendado para llamar {$humanDiff} (el {$formattedDate})";
                            }
                            return 'No hay seguimiento agendado.'; // Texto si no hay fecha
                        })
                        ->hintIcon('heroicon-m-information-circle'), // Opcional: icono para el hint
                        // --- FIN AÑADIDO ---
                    
                    DateTimePicker::make('agenda_nueva')
                        // ... (como estaba, con visible(), required(), after(), etc.) ...
                        ->label('Nueva Fecha de Seguimiento')
                        ->minutesStep(30)
                        ->seconds(false)
                        ->prefixIcon('heroicon-o-clock')
                        ->native(false)
                        ->required(fn (Get $get): bool => $get('actualizar_agenda') === true)
                        ->after('now')
                        ->visible(fn (Get $get): bool => $get('actualizar_agenda') === true),
                ])
                ->modalHeading(fn (?Lead $record): string => "Registrar Llamada a " . ($record?->nombre ?? 'este lead')) // <-- Añadir esta versión dinámica
                ->modalSubmitActionLabel('Registrar llamada')
                ->modalWidth('xl') // Prueba con 'large' o 'xl' si prefieres más ancho
             
                 // La lógica de la acción al pulsar "Registrar" sigue siendo la misma
                 ->action(function (array $data, Lead $record) {
                    $currentUser = Auth::user(); // Obtenemos el usuario actual una vez
                    $userName = $currentUser?->name ?? 'Usuario'; // Ajusta 'name' o 'full_name'
                
                    // 1. Incrementar contador
                    $record->increment('llamadas');
                
                    // 2. Determinar y ACTUALIZAR la agenda ANTES de crear el comentario
                    $agendaActualizada = false;
                    $fechaAgendaFinal = $record->agenda; // Empezamos con la fecha que ya tenía el lead
                
                    // Comprobamos si el usuario marcó actualizar y si hay una nueva fecha válida
                    if (isset($data['actualizar_agenda']) && $data['actualizar_agenda'] === true && !empty($data['agenda_nueva'])) {
                        try {
                            // Intentamos convertir la fecha del formulario a objeto Carbon
                            $nuevaFechaAgenda = Carbon::parse($data['agenda_nueva']);
                
                            // Actualizamos el campo agenda en el objeto $record
                            $record->agenda = $nuevaFechaAgenda;
                
                            // Guardamos el cambio en la BD AHORA MISMO
                            $record->save();
                
                            // Actualizamos la variable que usaremos para el comentario
                            $fechaAgendaFinal = $nuevaFechaAgenda;
                            $agendaActualizada = true; // Marcamos que sí se actualizó
                
                        } catch (\Exception $e) {
                            // Si la fecha del formulario no es válida, notificamos y salimos
                            Notification::make()->title('Error al procesar fecha')->body('La fecha de agenda proporcionada no es válida.')->danger()->send();
                            return; // Detenemos la acción aquí
                        }
                    }
                
                    // 3. Construir el texto del comentario
                    $textoComentario = "Llamada registrada por {$userName}.";
                
                    // Añadimos información de la agenda si existe una fecha final
                    if ($fechaAgendaFinal instanceof Carbon) { // Comprobamos que sea un objeto Carbon válido
                        // Asegúrate que Carbon/Laravel tiene el locale 'es' configurado para diffForHumans
                        $textoRelativo = $fechaAgendaFinal->diffForHumans(); // Ej: "en 2 días", "hace 1 hora"
                        $textoComentario .= " Próximo seguimiento: {$textoRelativo}.";
                    } else {
                        $textoComentario .= " No hay próximo seguimiento agendado.";
                    }
                
                    // 4. Crear el comentario polimórfico
                    $record->comentarios()->create([
                        'user_id' => $currentUser->id,
                        'contenido' => $textoComentario // Usamos el texto construido
                    ]);
                
                    // 5. Enviar Notificación final
                    if ($agendaActualizada) {
                        Notification::make()->title('Llamada registrada y agenda actualizada')->success()->send();
                    } else {
                        Notification::make()->title('Llamada registrada')->success()->send();
                    }
                
                    // Ya no hace falta $record->save() aquí si no se modificó la agenda,
                    // porque el increment() guarda directo y la agenda se guardó antes si cambió.
                }),
                
        
               // --- Acción Enviar Email ---
               Tables\Actions\Action::make('enviarEmail')
                ->icon('heroicon-o-envelope') // Icono cambiado
                ->label('')
                ->tooltip('Registrar Email Enviado y Opcionalmente Reagendar') // Texto cambiado
                ->color('warning') // Color cambiado (ejemplo)
                ->form([
                    Placeholder::make('accion_info')
                        ->label('')
                        ->content(new HtmlString('<strong>Registrar Email:</strong> Confirma la acción y, si lo necesitas, indica la nueva fecha para el próximo seguimiento.')), // Texto cambiado

                    Toggle::make('actualizar_agenda')
                        ->label('Reagendar Próximo Seguimiento')
                        ->helperText('Activa esto para establecer una nueva fecha y hora.')
                        ->onIcon('heroicon-m-calendar-days')
                        ->offIcon('heroicon-o-calendar')
                        ->live()
                        ->default(false)
                        ->hint(function (?Lead $record): ?string { // Lógica del Hint idéntica
                            if ($record?->agenda) {
                                $humanDiff = $record->agenda->diffForHumans();
                                $formattedDate = $record->agenda->isoFormat('dddd D [de] MMMM, H:mm');
                                return "Actualmente agendado {$humanDiff} (el {$formattedDate})"; // Texto ligeramente adaptado
                            }
                            return 'No hay seguimiento agendado.';
                        })
                        ->hintIcon('heroicon-m-information-circle'),

                    DateTimePicker::make('agenda_nueva')
                        ->label('Nueva Fecha de Seguimiento')
                        ->minutesStep(30)
                        ->seconds(false)
                        ->prefixIcon('heroicon-o-clock')
                        ->native(false)
                        ->required(fn (Get $get): bool => $get('actualizar_agenda') === true)
                        ->after('now')
                        ->visible(fn (Get $get): bool => $get('actualizar_agenda') === true),
                ])
                ->modalHeading(fn (?Lead $record): string => "Registrar Email a " . ($record?->nombre ?? 'este lead')) // Título dinámico cambiado
                ->modalSubmitActionLabel('Registrar email') // Botón cambiado
                ->modalWidth('xl')
               ->action(function (array $data, Lead $record) { // Lógica de acción adaptada
                        $currentUser = Auth::user();
                        $userName = $currentUser?->name ?? 'Usuario'; // Ajusta 'name'

                        // 1. Incrementar contador específico
                        $record->increment('emails'); // <-- Cambiado a 'emails'

                        // 2. Determinar y actualizar agenda (lógica idéntica)
                        $agendaActualizada = false;
                        $fechaAgendaFinal = $record->agenda;

                        if (
                            isset($data['actualizar_agenda']) &&
                            $data['actualizar_agenda'] === true &&
                            ! empty($data['agenda_nueva'])
                        ) {
                            try {
                                $nuevaFechaAgenda = Carbon::parse($data['agenda_nueva']);
                                $record->agenda = $nuevaFechaAgenda;
                                $record->save();

                                $fechaAgendaFinal   = $nuevaFechaAgenda;
                                $agendaActualizada  = true;
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Error al procesar fecha')
                                    ->danger()
                                    ->send();

                                return;
                            }
                        }

                        // 3. Construir comentario
                        $textoComentario = "Email enviado por {$userName}.";

                        if ($fechaAgendaFinal instanceof Carbon) {
                            $textoRelativo   = $fechaAgendaFinal->diffForHumans();
                            $textoComentario .= " Próximo seguimiento: {$textoRelativo}.";
                        } else {
                            $textoComentario .= " No hay próximo seguimiento agendado.";
                        }

                        // Guardar comentario
                        $record->comentarios()->create([
                            'user_id'   => $currentUser->id,
                            'contenido' => $textoComentario,
                        ]);

                        // ===============================
                        //   CONTAR INTENTO PARA AUTOSPAM
                        // ===============================
                        $estadoActual = $record->estado instanceof \App\Enums\LeadEstadoEnum
                            ? $record->estado->value
                            : (string) $record->estado;

                        if (in_array($estadoActual, [
                            \App\Enums\LeadEstadoEnum::INTENTO_CONTACTO->value,
                            \App\Enums\LeadEstadoEnum::ESPERANDO_INFORMACION->value,
                        ], true)) {
                            $record->registrarEnvioEmailEstado();
                        }

                        // 👇 marcar interacción manual SOLO aquí para el autospam
                        $record->marcarInteraccionManual();

                        // 4. Notificación
                        if ($agendaActualizada) {
                            Notification::make()
                                ->title('Email registrado y agenda actualizada')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Email registrado')
                                ->success()
                                ->send();
                        }
                    }),


                // --- Acción Chat ---
                Tables\Actions\Action::make('chat')
                ->icon('heroicon-o-chat-bubble-bottom-center-text')
                ->label('')
                ->tooltip('Registrar Chat y Opcionalmente Reagendar') // Texto cambiado
                ->color('success') // Color cambiado (ejemplo)
                ->form([ // Lógica del formulario idéntica a 'llamar', solo cambia el texto del placeholder
                    Placeholder::make('accion_info')
                        ->label('')
                        ->content(new HtmlString('<strong>Registrar Chat:</strong> Confirma la acción y, si lo necesitas, indica la nueva fecha para el próximo seguimiento.')), // Texto cambiado
                    Toggle::make('actualizar_agenda') // Resto del form idéntico...
                        ->label('Reagendar Próximo Seguimiento')
                        ->helperText('Activa esto para establecer una nueva fecha y hora.')
                        ->onIcon('heroicon-m-calendar-days')
                        ->offIcon('heroicon-o-calendar')
                        ->live()
                        ->default(false)
                        ->hint(function (?Lead $record): ?string { /* ... misma lógica hint ... */ if ($record?->agenda) { $humanDiff = $record->agenda->diffForHumans(); $formattedDate = $record->agenda->isoFormat('dddd D [de] MMMM, H:mm'); return "Actualmente agendado {$humanDiff} (el {$formattedDate})"; } return 'No hay seguimiento agendado.'; })
                        ->hintIcon('heroicon-m-information-circle'),
                    DateTimePicker::make('agenda_nueva') // Resto del form idéntico...
                        ->label('Nueva Fecha de Seguimiento')
                        ->minutesStep(30)
                        ->seconds(false)
                        ->prefixIcon('heroicon-o-clock')
                        ->native(false)
                        ->required(fn (Get $get): bool => $get('actualizar_agenda') === true)
                        ->after('now')
                        ->visible(fn (Get $get): bool => $get('actualizar_agenda') === true),
                ])
                ->modalHeading(fn (?Lead $record): string => "Registrar Chat con " . ($record?->nombre ?? 'este lead')) // Título dinámico cambiado
                ->modalSubmitActionLabel('Registrar chat') // Botón cambiado
                ->modalWidth('xl')
                ->action(function (array $data, Lead $record) { // Lógica de acción adaptada
                    $currentUser = Auth::user();
                    $userName = $currentUser?->name ?? 'Usuario'; // Ajusta 'name'

                    // 1. Incrementar contador específico
                    $record->increment('chats'); // <-- Cambiado a 'chats'

                    // 2. Determinar y actualizar agenda (lógica idéntica)
                    $agendaActualizada = false;
                    $fechaAgendaFinal = $record->agenda;
                    if (isset($data['actualizar_agenda']) && $data['actualizar_agenda'] === true && !empty($data['agenda_nueva'])) {
                        try { $nuevaFechaAgenda = Carbon::parse($data['agenda_nueva']); $record->agenda = $nuevaFechaAgenda; $record->save(); $fechaAgendaFinal = $nuevaFechaAgenda; $agendaActualizada = true; } catch (\Exception $e) { Notification::make()->title('Error al procesar fecha')->danger()->send(); return; }
                    }

                    // 3. Construir y crear el comentario (texto adaptado)
                    $textoComentario = "Chat registrado por {$userName}."; // <-- Texto cambiado
                    if ($fechaAgendaFinal instanceof Carbon) { $textoRelativo = $fechaAgendaFinal->diffForHumans(); $textoComentario .= " Próximo seguimiento: {$textoRelativo}."; } else { $textoComentario .= " No hay próximo seguimiento agendado."; }
                    $record->comentarios()->create([ 'user_id' => $currentUser->id, 'contenido' => $textoComentario ]);

                    // 4. Enviar Notificación (texto adaptado)
                    if ($agendaActualizada) { Notification::make()->title('Chat registrado y agenda actualizada')->success()->send(); } else { Notification::make()->title('Chat registrado')->success()->send(); } // <-- Texto cambiado
                }),


                // --- Acción Otros ---
                Tables\Actions\Action::make('otros')
                ->icon('heroicon-o-paper-airplane')
                ->label('')
                ->tooltip('Registrar Otra Acción y Opcionalmente Reagendar') // Texto cambiado
                ->color('gray') // Color cambiado (ejemplo)
                ->form([ // Lógica del formulario idéntica a 'llamar', solo cambia el texto del placeholder
                    Placeholder::make('accion_info')
                        ->label('')
                        ->content(new HtmlString('<strong>Registrar Otra Acción:</strong> Confirma la acción y, si lo necesitas, indica la nueva fecha para el próximo seguimiento.')), // Texto cambiado
                    Toggle::make('actualizar_agenda') // Resto del form idéntico...
                        ->label('Reagendar Próximo Seguimiento')
                        ->helperText('Activa esto para establecer una nueva fecha y hora.')
                        ->onIcon('heroicon-m-calendar-days')
                        ->offIcon('heroicon-o-calendar')
                        ->live()
                        ->default(false)
                        ->hint(function (?Lead $record): ?string { /* ... misma lógica hint ... */ if ($record?->agenda) { $humanDiff = $record->agenda->diffForHumans(); $formattedDate = $record->agenda->isoFormat('dddd D [de] MMMM, H:mm'); return "Actualmente agendado {$humanDiff} (el {$formattedDate})"; } return 'No hay seguimiento agendado.'; })
                        ->hintIcon('heroicon-m-information-circle'),
                    DateTimePicker::make('agenda_nueva') // Resto del form idéntico...
                        ->label('Nueva Fecha de Seguimiento')
                        ->minutesStep(30)
                        ->seconds(false)
                        ->prefixIcon('heroicon-o-clock')
                        ->native(false)
                        ->required(fn (Get $get): bool => $get('actualizar_agenda') === true)
                        ->after('now')
                        ->visible(fn (Get $get): bool => $get('actualizar_agenda') === true),
                ])
                ->modalHeading(fn (?Lead $record): string => "Registrar Otra Acción para " . ($record?->nombre ?? 'este lead')) // Título dinámico cambiado
                ->modalSubmitActionLabel('Registrar acción') // Botón cambiado
                ->modalWidth('xl')
                ->action(function (array $data, Lead $record) { // Lógica de acción adaptada
                    $currentUser = Auth::user();
                    $userName = $currentUser?->name ?? 'Usuario'; // Ajusta 'name'

                    // 1. Incrementar contador específico
                    $record->increment('otros_acciones'); // <-- Cambiado a 'otros_acciones'

                    // 2. Determinar y actualizar agenda (lógica idéntica)
                    $agendaActualizada = false;
                    $fechaAgendaFinal = $record->agenda;
                    if (isset($data['actualizar_agenda']) && $data['actualizar_agenda'] === true && !empty($data['agenda_nueva'])) {
                        try { $nuevaFechaAgenda = Carbon::parse($data['agenda_nueva']); $record->agenda = $nuevaFechaAgenda; $record->save(); $fechaAgendaFinal = $nuevaFechaAgenda; $agendaActualizada = true; } catch (\Exception $e) { Notification::make()->title('Error al procesar fecha')->danger()->send(); return; }
                    }

                    // 3. Construir y crear el comentario (texto adaptado)
                    $textoComentario = "Otra acción registrada por {$userName}."; // <-- Texto cambiado
                    if ($fechaAgendaFinal instanceof Carbon) { $textoRelativo = $fechaAgendaFinal->diffForHumans(); $textoComentario .= " Próximo seguimiento: {$textoRelativo}."; } else { $textoComentario .= " No hay próximo seguimiento agendado."; }
                    $record->comentarios()->create([ 'user_id' => $currentUser->id, 'contenido' => $textoComentario ]);

                    // 4. Enviar Notificación (texto adaptado)
                    if ($agendaActualizada) { Notification::make()->title('Otra acción registrada y agenda actualizada')->success()->send(); } else { Notification::make()->title('Otra acción registrada')->success()->send(); } // <-- Texto cambiado
                }),

        ])
        ->actionsPosition(\Filament\Tables\Enums\ActionsPosition::BeforeColumns)
        ->bulkActions([ // Acciones Masivas
            Tables\Actions\BulkActionGroup::make([
                ExportBulkAction::make('exportar_completo')
        ->label('Exportar seleccionados')
        ->exports([
            \pxlrbt\FilamentExcel\Exports\ExcelExport::make('leads')
                //->fromTable() // usa los registros seleccionados
                ->withColumns([
                    \pxlrbt\FilamentExcel\Columns\Column::make('id'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('nombre')
                       ->heading('Nombre'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('email')
                        ->heading('Email'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('tfn')
                        ->heading('Teléfono'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('procedencia.procedencia')
                        ->heading('Procedencia'),                       
                    \pxlrbt\FilamentExcel\Columns\Column::make('creador.name')
                        ->heading('Creador'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('asignado.name')
                        ->heading('Asignado'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('estado')
                        ->heading('Estado'),    
                    \pxlrbt\FilamentExcel\Columns\Column::make('demandado')
                        ->heading('Demandado'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('fecha_gestion')
                        ->heading('Fecha de gestión')
                        ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d/m/Y - H:i')),
                    \pxlrbt\FilamentExcel\Columns\Column::make('agenda')
                        ->heading('Agendado')
                        ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d/m/Y - H:i')),
                    \pxlrbt\FilamentExcel\Columns\Column::make('fecha_cierre')
                        ->heading('Fecha de cierre')
                        ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d/m/Y - H:i')),
                    \pxlrbt\FilamentExcel\Columns\Column::make('observacion_cierre')
                        ->heading('Observaciones cierre'),
                       
                    \pxlrbt\FilamentExcel\Columns\Column::make('motivoDescarte.motivo')
                        ->heading('Motivo de descarte'),  
                    \pxlrbt\FilamentExcel\Columns\Column::make('cliente.nombre')
                        ->heading('Cliente'),                     
                    \pxlrbt\FilamentExcel\Columns\Column::make('llamadas')
                        ->heading('Llamadas'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('emails')
                        ->heading('Emails'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('chats')
                        ->heading('Chats'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('otros_acciones')
                        ->heading('Otras acciones'),                       
                    \pxlrbt\FilamentExcel\Columns\Column::make('observaciones')
                        ->heading('Observaciones'),
                    \pxlrbt\FilamentExcel\Columns\Column::make('created_at')
                        ->heading('Creado en App')
                        ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d/m/Y - H:i')),
                    \pxlrbt\FilamentExcel\Columns\Column::make('updated_at')
                        ->heading('Actualizado en App')
                        ->formatStateUsing(fn ($state) => \Carbon\Carbon::parse($state)->format('d/m/Y - H:i')),

                        
                ]),
        ])
        ->icon('icon-excel2')
        ->color('success')
        ->deselectRecordsAfterCompletion()
        ->requiresConfirmation()
        ->modalHeading('Exportar Leads Seleccionados')
        ->modalDescription('Exportarás todos los datos de los Leads seleccionados.'),
                Tables\Actions\DeleteBulkAction::make(),
                // ExportBulkAction::make(), // Si usas exportación

                // Acción Masiva: Asignar (Movida aquí y adaptada)
                BulkAction::make('asignarComercial')
                    ->label('Asignar Comercial')
                    ->icon('heroicon-o-users')
                    ->form([
                            Select::make('asignado_id_masivo')
                                ->label('Asignar a')
                                ->options(function () {
                                    return User::query()
                                        ->whereHas('roles', function (EloquentBuilder $q) {
                                            $q->whereIn('name', ['comercial', 'super_admin']);
                                        })
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->toArray();
                                })
                                ->required()
                                ->searchable()
                                ->preload(),
                        ])
                    ->action(function (array $data, EloquentCollection $records){
                        $userID = $data['asignado_id_masivo'];
                        $records->each->update(['asignado_id' => $userID]); // Actualiza cada registro

                        // Notificar al usuario asignado (opcional, puede ser pesado si son muchos leads)
                        $assignedUser = User::find($userID);
                        if($assignedUser) {
                             Notification::make()
                                ->title('Nuevos Leads Asignados')
                                ->icon('heroicon-o-user-group')
                                ->info()
                                ->body("Te han asignado {$records->count()} lead(s).")
                                ->sendToDatabase($assignedUser); // Enviar al usuario objeto
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Asignar Leads Seleccionados')
                    ->modalDescription('Selecciona el comercial al que quieres asignar estos leads.')
                    ->modalSubmitActionLabel('Asignar')
                    ->deselectRecordsAfterCompletion(),
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
            'index' => Pages\ListLeads::route('/'),
            'create' => Pages\CreateLead::route('/create'),
            'view' => Pages\ViewLead::route('/{record}'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }
}
