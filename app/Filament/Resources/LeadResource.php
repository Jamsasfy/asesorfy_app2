<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Grid;
use Filament\Actions\Action;
use Exception;
use App\Jobs\SendLeadEstadoChangedEmailJob;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Actions\BulkActionGroup;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkAction;
use App\Filament\Resources\LeadResource\Pages\ListLeads;
use App\Filament\Resources\LeadResource\Pages\CreateLead;
use App\Filament\Resources\LeadResource\Pages\ViewLead;
use App\Filament\Resources\LeadResource\Pages\EditLead;
use App\Enums\LeadEstadoEnum;
use App\Enums\VentaEstadoEnum;
use App\Models\Comentario;
use App\Models\Lead;
use App\Models\User;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Radio;
use Filament\Tables\Columns\IconColumn;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\Alignment;
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
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Facades\Log; // Para escribir en el log de Laravel
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Filament\Tables\Filters\TernaryFilter;
// <--- AÑADE ESTA LÍNEA
use App\Filament\Resources\LeadResource\Pages\GestionarConversion;
use Filament\Infolists\Components\RepeatableEntry;
use App\Models\LeadAutoEmailLog;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use App\Mail\ContractCopyMail;
use App\Mail\RecordatorioPagoMail;
use App\Models\Venta;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Checkbox;
use App\Models\Factura as FacturaModel; // ✅ IMPORTANTE (modelo real)




//use Filament\Tables\Actions\Action; // Para acciones personalizadas

class LeadResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Lead::class;

    protected static string | \BackedEnum | null $navigationIcon = 'icon-leads';

    protected static string | \UnitEnum | null $navigationGroup = 'Gestión LEADS';

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
            'convertir',
        ];
    }

    public static function getEloquentQuery(): EloquentBuilder
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


    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                                modifyQueryUsing: fn(EloquentBuilder $query) => $query->whereHas('roles', fn(EloquentBuilder $q) => $q->where('name', 'comercial'))
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
                            ->helperText(
                                fn(?Lead $record): ?string => (! is_null($record?->cliente_id))
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
                            ->visible(fn(Get $get) => filled($get('fecha_cierre')))
                            ->columnSpan(1),

                        Select::make('motivo_descarte_id')
                            ->label('Motivo de Descarte')
                            ->relationship('motivoDescarte', 'nombre', fn(EloquentBuilder $query) => $query->where('activo', true))
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



    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(1)
                ->columnSpanFull()->schema([

                    // ===============================
                    // INFORMACIÓN PRINCIPAL DEL LEAD
                    // ===============================
                    Section::make('Información de este Lead')
                        ->schema([
                            Grid::make(3)->schema([

                                // ===============================
                                // 1. DATOS DE CONTACTO
                                // ===============================
                                Section::make('Datos de contacto - Consulta')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextEntry::make('nombre')
                                                ->label('👤 Nombre')
                                                ->weight('bold')
                                                ->copyable(),

                                            TextEntry::make('tfn')
                                                ->label('📞 Teléfono')
                                                ->weight('bold')
                                                ->copyable(),

                                            TextEntry::make('email')
                                                ->label('✉️ Email')
                                                ->weight('bold')
                                                ->copyable()
                                                ->wrap(),

                                            TextEntry::make('demandado')
                                                ->label('Demandado')
                                                ->color('info')
                                                ->weight('bold')
                                                ->copyable()
                                                ->columnSpanFull(),
                                        ]),
                                    ]),

                                // ===============================
                                // 2. ESTADO & ASIGNACIÓN
                                // ===============================
                                Section::make('Estado & Asignación')
                                    ->schema([
                                        Grid::make(3)->schema([

                                            TextEntry::make('creador.full_name')
                                                ->label('🧑‍💻 Creado por')
                                                ->badge()
                                                ->color('gray'),

                                            TextEntry::make('created_at')
                                                ->label('🕒 Creación')
                                                ->dateTime('d/m/Y H:i'),

                                            TextEntry::make('fecha_gestion')
                                                ->label('🔛 Inicio gestión')
                                                ->dateTime('d/m/Y H:i'),

                                            TextEntry::make('asignado_display')
                                                ->label('📌 Asignado a')
                                                ->badge()
                                                ->getStateUsing(
                                                    fn(Lead $record) =>
                                                    $record->asignado?->full_name ?? 'Sin asignar'
                                                )
                                                ->color(
                                                    fn(string $state) =>
                                                    $state === 'Sin asignar' ? 'warning' : 'info'
                                                ),

                                           TextEntry::make('estado')
    ->label('Estado actual')
    ->badge()
    ->weight('bold')
    ->formatStateUsing(fn (?LeadEstadoEnum $state) => $state?->getLabel() ?? '—')
    ->color(fn (?LeadEstadoEnum $state) => match ($state) {
        LeadEstadoEnum::SIN_GESTIONAR          => 'gray',
        LeadEstadoEnum::INTENTO_CONTACTO      => 'warning',
        LeadEstadoEnum::CONTACTADO            => 'info',
        LeadEstadoEnum::ANALISIS_NECESIDADES  => 'primary',
        LeadEstadoEnum::ESPERANDO_INFORMACION => 'warning',
        LeadEstadoEnum::PROPUESTA_ENVIADA     => 'info',
        LeadEstadoEnum::EN_NEGOCIACION        => 'primary',
        LeadEstadoEnum::CONVERTIDO            => 'success',
        LeadEstadoEnum::DESCARTADO            => 'danger',
        default                               => 'gray',
    })
    ->suffixAction(
        Action::make('cambiar_estado')
            ->icon('heroicon-m-arrow-path')
            ->tooltip('Cambiar estado')
            ->visible(fn (Lead $record) => $record->asignado_id && ! $record->estado?->isFinal())
            ->modalHeading(fn (Lead $record) => 'Cambiar estado de ' . ($record->nombre ?? 'este lead'))
            ->modalSubmitActionLabel('Guardar')
            ->schema([
                Select::make('estado_nuevo')
                    ->label('Nuevo estado')
                    ->options(
                        collect(LeadEstadoEnum::cases())
                            ->reject(fn ($e) => str_starts_with($e->value, 'convertido_')) // dejamos CONVERTIDO, quitamos convertido_*
                            ->mapWithKeys(fn ($e) => [$e->value => $e->getLabel()])
                    )
                    ->required()
                    ->live(),

                // ✅ Aviso solo cuando eligen CONVERTIDO
                Placeholder::make('aviso_convertido')
                    ->content('Vas a iniciar el proceso de conversión. Al confirmar, se abrirá la pantalla de Conversión para preparar servicios/venta y enviar el link al cliente.')
                    ->visible(fn (Get $get) =>
                        LeadEstadoEnum::tryFrom($get('estado_nuevo')) === LeadEstadoEnum::CONVERTIDO
                    ),

                // ✅ Confirmación obligatoria solo para CONVERTIDO
                Checkbox::make('confirmar_convertido')
                    ->label('Sí, estoy seguro. Iniciar conversión ahora.')
                    ->visible(fn (Get $get) =>
                        LeadEstadoEnum::tryFrom($get('estado_nuevo')) === LeadEstadoEnum::CONVERTIDO
                    )
                    ->accepted()
                    ->required(fn (Get $get) =>
                        LeadEstadoEnum::tryFrom($get('estado_nuevo')) === LeadEstadoEnum::CONVERTIDO
                    ),

                Select::make('motivo_descarte_id')
                    ->label('Motivo de descarte')
                    ->options(
                        \App\Models\MotivoDescarte::query()
                            ->where('activo', true)
                            ->pluck('nombre', 'id')
                    )
                    ->visible(fn (Get $get) =>
                        LeadEstadoEnum::tryFrom($get('estado_nuevo')) === LeadEstadoEnum::DESCARTADO
                    )
                    ->required(fn (Get $get) =>
                        LeadEstadoEnum::tryFrom($get('estado_nuevo')) === LeadEstadoEnum::DESCARTADO
                    ),

                Textarea::make('observacion_cierre')
                    ->label('Observaciones')
                    ->visible(fn (Get $get) =>
                        LeadEstadoEnum::tryFrom($get('estado_nuevo'))?->isFinal()
                    )
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, Lead $record, $livewire) {

                $nuevo = LeadEstadoEnum::tryFrom($data['estado_nuevo'] ?? null);
                if (! $nuevo) {
                    return;
                }

                // ✅ Extra safety (aunque el checkbox ya valida)
                if ($nuevo === LeadEstadoEnum::CONVERTIDO && empty($data['confirmar_convertido'])) {
                    Notification::make()
                        ->title('Confirmación requerida')
                        ->danger()
                        ->body('Debes confirmar para iniciar la conversión.')
                        ->send();
                    return;
                }

                $record->estado = $nuevo;
                $record->fecha_cierre = $nuevo->isFinal() ? now() : null;

                if ($nuevo === LeadEstadoEnum::DESCARTADO) {
                    $record->motivo_descarte_id = $data['motivo_descarte_id'] ?? null;
                    $record->observacion_cierre = $data['observacion_cierre'] ?? null;
                }

                $record->save();

                $texto = 'Cambio de estado a: ' . $nuevo->getLabel();

                if (! empty($data['observacion_cierre'])) {
                    $texto .= "\nObs: " . $data['observacion_cierre'];
                }

                $record->comentarios()->create([
                    'user_id'   => auth()->id(),
                    'contenido' => $texto,
                ]);

                Notification::make()
                    ->title('Estado actualizado')
                    ->success()
                    ->send();

                // ✅ Filament v4: refresh real
                $record->refresh();
                $livewire->dispatch('$refresh');

                // ✅ Si es CONVERTIDO, nos vamos a la pantalla de conversión
                if ($nuevo === LeadEstadoEnum::CONVERTIDO) {
                    $livewire->redirect(
                        LeadResource::getUrl('conversion', ['record' => $record]),
                        navigate: true
                    );
                }
            })
    ),


                                            TextEntry::make('venta_asociada')
                                                ->label('Venta asociada')
                                                ->badge()
                                                ->color('warning')
                                                ->visible(fn(Lead $record) => $record->ventas->isNotEmpty())
                                                ->getStateUsing(
                                                    fn(Lead $record) =>
                                                    'Ver venta #' . $record->ventas->first()->id
                                                )
                                                ->url(
                                                    fn(Lead $record) =>
                                                    VentaResource::getUrl('view', [
                                                        'record' => $record->ventas->first()->id,
                                                    ])
                                                )
                                                ->openUrlInNewTab(),
                                        ]),
                                    ]),

                                // ===============================
                                // 3. AGENDA & GESTIÓN
                                // ===============================
                                Section::make('Agenda & Gestión')
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextEntry::make('updated_at')
                                                ->label(new HtmlString('<span class="font-semibold">Actualizado</span>'))
                                                ->dateTime('d/m/Y H:i')
                                                ->badge()
                                                ->color('gray'),

                                            TextEntry::make('agenda')
                                                ->label(new HtmlString('<span class="font-semibold">📆 Próxima cita</span>'))
                                                ->dateTime('d/m/Y H:i')
                                                ->placeholder('Sin agendar')
                                                ->badge()
                                                ->color(fn($state) => $state ? 'info' : 'gray')
                                                ->suffixAction(
                                                    Action::make('reagendar')
                                                        ->icon('heroicon-m-calendar-days')
                                                        ->tooltip('Reagendar seguimiento')
                                                        ->modalHeading('Reagendar seguimiento')
                                                        ->modalSubmitActionLabel('Guardar fecha')
                                                        ->form([
                                                            DateTimePicker::make('agenda')
                                                                ->label('Nueva fecha de agenda')
                                                                ->displayFormat('d/m/Y H:i')
                                                                ->native(false)
                                                                ->minutesStep(30)
                                                                ->required(),
                                                        ])
                                                        ->action(function (array $data, Lead $record) {

                                                            $record->agenda = $data['agenda'];
                                                            $record->save();

                                                            $fechaFormateada = Carbon::parse($data['agenda'])
                                                                ->isoFormat('dddd D [de] MMMM, HH:mm');

                                                            $record->comentarios()->create([
                                                                'user_id'   => auth()->id(),
                                                                'contenido' => "📅 Nueva fecha de agenda fijada para: {$fechaFormateada}.",
                                                            ]);

                                                            Notification::make()
                                                                ->title('Agenda actualizada')
                                                                ->body('La nueva fecha de seguimiento se ha guardado correctamente.')
                                                                ->success()
                                                                ->send();
                                                        })
                                                ),

                                            TextEntry::make('autospam_activo')
                                                ->label('🤖 IA Boot Fy')
                                                ->badge()
                                                ->weight('bold')
                                                ->formatStateUsing(fn(?bool $state): string => $state ? 'Activo' : 'Desactivado')
                                                ->color(fn(?bool $state): string => $state ? 'success' : 'gray')
                                                ->icon(fn(?bool $state): ?string => $state ? 'heroicon-m-bug-ant' : 'heroicon-m-bell-slash')
                                                ->suffixAction(
                                                    Action::make('toggleAutospam')
                                                        ->icon(
                                                            fn(Lead $record): string => $record->autospam_activo
                                                                ? 'heroicon-m-no-symbol'
                                                                : 'heroicon-m-check'
                                                        )
                                                        ->color(fn(Lead $record): string => $record->autospam_activo ? 'danger' : 'success')
                                                        ->tooltip(
                                                            fn(Lead $record): string => $record->autospam_activo
                                                                ? 'Desactivar autospam'
                                                                : 'Activar autospam'
                                                        )
                                                        ->action(function (Lead $record): void {
                                                            $record->update([
                                                                'autospam_activo' => ! $record->autospam_activo,
                                                            ]);

                                                            Notification::make()
                                                                ->title('Autospam actualizado')
                                                                ->success()
                                                                ->send();
                                                        })
                                                ),

                                            //segundo grid
                                            // ====== INTERACCIONES (fila completa) ======
                                            Section::make('Interacciones')
                                                ->schema([
                                                    Grid::make(5)->schema([

                                                        TextEntry::make('llamadas')
                                                            ->label('📞 Llamadas')
                                                            ->size('xl')
                                                            ->weight('bold')
                                                            ->alignment(Alignment::Center)
                                                            ->suffixAction(
                                                                Action::make('registrar_llamada')
                                                                    ->icon('heroicon-m-phone-arrow-up-right')
                                                                    ->color('primary')
                                                                    ->tooltip('Registrar llamada')
                                                                    ->modalHeading('Registrar llamada')
                                                                    ->modalSubmitActionLabel('Guardar llamada')
                                                                    ->modalWidth('lg')
                                                                    ->form([
                                                                        Toggle::make('respuesta')
                                                                            ->label('¿Ha contestado?')
                                                                            ->default(false)
                                                                            ->live(),

                                                                        Textarea::make('comentario')
                                                                            ->label('Comentario')
                                                                            ->rows(3)
                                                                            ->maxLength(500)
                                                                            ->visible(fn($get) => $get('respuesta') === true)
                                                                            ->required(fn($get) => $get('respuesta') === true),

                                                                        Toggle::make('agendar')
                                                                            ->label('Agendar seguimiento')
                                                                            ->default(false)
                                                                            ->live(),

                                                                        DateTimePicker::make('agenda')
                                                                            ->label('Fecha y hora del seguimiento')
                                                                            ->minutesStep(30)
                                                                            ->seconds(false)
                                                                            ->native(false)
                                                                            ->after(now())
                                                                            ->visible(fn($get) => $get('agendar') === true),
                                                                    ])
                                                                    ->action(function (array $data, Lead $record) {

                                                                        $usuario = auth()->user();
                                                                        $nombreUsuario = $usuario?->name ?? 'Usuario';

                                                                        $record->increment('llamadas');

                                                                        $comentario = "📞 Llamada registrada por {$nombreUsuario}.";
                                                                        $comentario .= (($data['respuesta'] ?? false) === true) ? ' [Contestada]' : ' [Sin respuesta]';

                                                                        if (! empty($data['comentario'])) {
                                                                            $comentario .= ' - ' . $data['comentario'];
                                                                        }

                                                                        if (($data['agendar'] ?? false) === true && ! empty($data['agenda'])) {
                                                                            $fechaAgenda = Carbon::parse($data['agenda']);
                                                                            $record->agenda = $fechaAgenda;
                                                                            $record->save();

                                                                            $comentario .= "\n📅 Próximo seguimiento: " . $fechaAgenda->isoFormat('dddd D [de] MMMM, HH:mm');
                                                                        }

                                                                        $record->comentarios()->create([
                                                                            'user_id'   => $usuario?->id,
                                                                            'contenido' => $comentario,
                                                                        ]);

                                                                        $record->marcarInteraccionManual();

                                                                        Notification::make()
                                                                            ->title('Llamada registrada')
                                                                            ->success()
                                                                            ->send();
                                                                    })
                                                            ),

                                                        TextEntry::make('emails')
                                                            ->label('📧 Emails')
                                                            ->size('xl')
                                                            ->weight('bold')
                                                            ->alignment(Alignment::Center)
                                                            ->suffixAction(
                                                                Action::make('registrar_email')
                                                                    ->icon('heroicon-m-envelope-open')
                                                                    ->color('warning')
                                                                    ->tooltip('Registrar email')
                                                                    ->modalHeading('Registrar email')
                                                                    ->modalSubmitActionLabel('Registrar email')
                                                                    ->modalWidth('lg')
                                                                    ->form([

                                                                        Textarea::make('comentario')
                                                                            ->label('Comentario (opcional)')
                                                                            ->rows(3)
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
                                                                            ->after(now())
                                                                            ->visible(fn(Get $get) => $get('agendar') === true),

                                                                        Select::make('nuevo_estado')
                                                                            ->label('Nuevo estado del lead')
                                                                            ->options(LeadEstadoEnum::class)
                                                                            ->visible(
                                                                                fn(?Lead $record) =>
                                                                                $record?->estado === LeadEstadoEnum::SIN_GESTIONAR
                                                                            )
                                                                            ->required(
                                                                                fn(?Lead $record) =>
                                                                                $record?->estado === LeadEstadoEnum::SIN_GESTIONAR
                                                                            )
                                                                            ->live(),

                                                                        Radio::make('modo_envio')
                                                                            ->label('¿Quién envía este email?')
                                                                            ->options([
                                                                                'manual' => 'Lo envío yo (ya enviado)',
                                                                                'boot'   => 'Que lo envíe Boot IA automáticamente',
                                                                            ])
                                                                            ->inline()
                                                                            ->required()
                                                                            ->visible(function (Get $get) {
                                                                                $valor = $get('nuevo_estado');
                                                                                if (! $valor) {
                                                                                    return false;
                                                                                }

                                                                                $enum = $valor instanceof LeadEstadoEnum
                                                                                    ? $valor
                                                                                    : LeadEstadoEnum::tryFrom($valor);

                                                                                return $enum && in_array($enum, [
                                                                                    LeadEstadoEnum::INTENTO_CONTACTO,
                                                                                    LeadEstadoEnum::ESPERANDO_INFORMACION,
                                                                                ], true);
                                                                            }),
                                                                    ])
                                                                    ->action(function (array $data, Lead $record) {

                                                                        $usuario = auth()->user();
                                                                        $nombreUsuario = $usuario?->name ?? 'Usuario';

                                                                        // Incrementar contador
                                                                        $record->increment('emails');

                                                                        // Comentario base
                                                                        $comentario = "📧 Email registrado por {$nombreUsuario}";

                                                                        if (! empty($data['comentario'])) {
                                                                            $comentario .= "\n" . $data['comentario'];
                                                                        }

                                                                        // Agenda
                                                                        if (($data['agendar'] ?? false) === true && ! empty($data['agenda'])) {
                                                                            $fechaAgenda = Carbon::parse($data['agenda']);
                                                                            $record->agenda = $fechaAgenda;
                                                                            $record->save();

                                                                            $comentario .= "\n📅 Seguimiento: "
                                                                                . $fechaAgenda->isoFormat('dddd D [de] MMMM, HH:mm');
                                                                        }

                                                                        // Cambio de estado (solo si estaba SIN_GESTIONAR)
                                                                        $estadoFinalEnum = $record->estado;

                                                                        if (
                                                                            $record->estado === LeadEstadoEnum::SIN_GESTIONAR
                                                                            && ! empty($data['nuevo_estado'])
                                                                        ) {
                                                                            $nuevoEnum = $data['nuevo_estado'] instanceof LeadEstadoEnum
                                                                                ? $data['nuevo_estado']
                                                                                : LeadEstadoEnum::tryFrom($data['nuevo_estado']);

                                                                            if ($nuevoEnum) {
                                                                                $record->estado = $nuevoEnum;
                                                                                $record->saveQuietly();
                                                                                $estadoFinalEnum = $nuevoEnum;
                                                                            }
                                                                        }

                                                                        // Modo de envío
                                                                        $modoEnvio = $data['modo_envio'] ?? 'manual';

                                                                        if ($modoEnvio === 'manual') {

                                                                            // Interacción manual (bloquea autospam)
                                                                            $record->marcarInteraccionManual();

                                                                            // Si es estado con autospam, registramos intento manual
                                                                            if ($estadoFinalEnum && in_array($estadoFinalEnum, [
                                                                                LeadEstadoEnum::INTENTO_CONTACTO,
                                                                                LeadEstadoEnum::ESPERANDO_INFORMACION,
                                                                            ], true)) {
                                                                                $record->registrarEnvioEmailEstado();
                                                                            }
                                                                        } else {
                                                                            // Envío automático con Boot IA
                                                                            if ($estadoFinalEnum && in_array($estadoFinalEnum, [
                                                                                LeadEstadoEnum::INTENTO_CONTACTO,
                                                                                LeadEstadoEnum::ESPERANDO_INFORMACION,
                                                                            ], true)) {
                                                                                SendLeadEstadoChangedEmailJob::dispatch(
                                                                                    $record->id,
                                                                                    $estadoFinalEnum->value
                                                                                );
                                                                            }
                                                                        }

                                                                        // Guardar comentario
                                                                        $record->comentarios()->create([
                                                                            'user_id'   => $usuario?->id,
                                                                            'contenido' => $comentario,
                                                                        ]);

                                                                        // Notificación
                                                                        Notification::make()
                                                                            ->title(
                                                                                $modoEnvio === 'boot'
                                                                                    ? 'Email IA en cola de envío'
                                                                                    : 'Email registrado'
                                                                            )
                                                                            ->success()
                                                                            ->send();
                                                                    })
                                                            ),


                                                        TextEntry::make('chats')
                                                            ->label('💬 Chats')
                                                            ->size('xl')
                                                            ->weight('bold')
                                                            ->alignment(\Filament\Support\Enums\Alignment::Center)
                                                            ->suffixAction(
                                                                Action::make('registrar_chat')
                                                                    ->icon('icon-whatsapp')
                                                                    ->color('success')
                                                                    ->tooltip('Registrar chat')
                                                                    ->schema([
                                                                        Textarea::make('comentario')
                                                                            ->label('Comentario (opcional)')
                                                                            ->rows(3)
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
                                                                            ->after(now())
                                                                            ->visible(fn(Get $get) => $get('agendar') === true),

                                                                        Select::make('nuevo_estado')
                                                                            ->label('Nuevo estado del lead')
                                                                            ->options(LeadEstadoEnum::class)
                                                                            ->visible(
                                                                                fn(?Lead $record) =>
                                                                                $record?->estado === LeadEstadoEnum::SIN_GESTIONAR
                                                                            )
                                                                            ->required(
                                                                                fn(?Lead $record) =>
                                                                                $record?->estado === LeadEstadoEnum::SIN_GESTIONAR
                                                                            )
                                                                            ->live(),
                                                                    ])
                                                                    ->modalHeading('Registrar chat')
                                                                    ->modalSubmitActionLabel('Registrar chat')
                                                                    ->modalWidth('lg')
                                                                    ->action(function (array $data, Lead $record) {

                                                                        $usuario = auth()->user();
                                                                        $nombreUsuario = $usuario?->name ?? 'Usuario';

                                                                        // Incrementar contador
                                                                        $record->increment('chats');

                                                                        // Comentario base
                                                                        $comentario = "💬 Chat registrado por {$nombreUsuario}.";

                                                                        if (! empty($data['comentario'])) {
                                                                            $comentario .= "\n" . $data['comentario'];
                                                                        }

                                                                        // Agenda
                                                                        if (($data['agendar'] ?? false) === true && ! empty($data['agenda'])) {
                                                                            $fechaAgenda = \Carbon\Carbon::parse($data['agenda']);
                                                                            $record->agenda = $fechaAgenda;
                                                                            $record->save();

                                                                            $comentario .= "\n📅 Seguimiento: "
                                                                                . $fechaAgenda->isoFormat('dddd D [de] MMMM, HH:mm');
                                                                        }

                                                                        // Cambio de estado SOLO si estaba sin gestionar
                                                                        if (
                                                                            $record->estado === LeadEstadoEnum::SIN_GESTIONAR
                                                                            && ! empty($data['nuevo_estado'])
                                                                        ) {
                                                                            $nuevoEnum = $data['nuevo_estado'] instanceof LeadEstadoEnum
                                                                                ? $data['nuevo_estado']
                                                                                : LeadEstadoEnum::tryFrom($data['nuevo_estado']);

                                                                            if ($nuevoEnum) {
                                                                                $record->estado = $nuevoEnum;
                                                                                $record->save();
                                                                            }
                                                                        }

                                                                        // Guardar comentario
                                                                        $record->comentarios()->create([
                                                                            'user_id'   => $usuario?->id,
                                                                            'contenido' => $comentario,
                                                                        ]);

                                                                        // Marcar interacción manual (bloquea recordatorios IA)
                                                                        $record->marcarInteraccionManual();

                                                                        // Notificación
                                                                        \Filament\Notifications\Notification::make()
                                                                            ->title('Chat registrado')
                                                                            ->success()
                                                                            ->send();
                                                                    })
                                                            ),


                                                        TextEntry::make('otros_acciones')
                                                            ->label('📎 Otros')
                                                            ->size('xl')
                                                            ->weight('bold')
                                                            ->alignment(\Filament\Support\Enums\Alignment::Center)
                                                            ->suffixAction(
                                                                Action::make('registrar_otro')
                                                                    ->icon('heroicon-m-paper-airplane')
                                                                    ->color('gray')
                                                                    ->tooltip('Registrar otra acción')
                                                                    ->schema([
                                                                        Textarea::make('comentario')
                                                                            ->label('Comentario obligatorio')
                                                                            ->rows(3)
                                                                            ->required()
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
                                                                            ->after(now())
                                                                            ->visible(fn(Get $get) => $get('agendar') === true),
                                                                    ])
                                                                    ->modalHeading('Registrar otra acción')
                                                                    ->modalSubmitActionLabel('Registrar acción')
                                                                    ->modalWidth('lg')
                                                                    ->action(function (array $data, Lead $record) {

                                                                        $usuario = auth()->user();
                                                                        $nombreUsuario = $usuario?->name ?? 'Usuario';

                                                                        // Incrementar contador
                                                                        $record->increment('otros_acciones');

                                                                        // Comentario base
                                                                        $comentario = "📎 Acción registrada por {$nombreUsuario}.";

                                                                        if (! empty($data['comentario'])) {
                                                                            $comentario .= "\n" . $data['comentario'];
                                                                        }

                                                                        // Agenda
                                                                        if (($data['agendar'] ?? false) === true && ! empty($data['agenda'])) {
                                                                            $fechaAgenda = \Carbon\Carbon::parse($data['agenda']);
                                                                            $record->agenda = $fechaAgenda;
                                                                            $record->save();

                                                                            $comentario .= "\n📅 Seguimiento: "
                                                                                . $fechaAgenda->isoFormat('dddd D [de] MMMM, HH:mm');
                                                                        }

                                                                        // Guardar comentario
                                                                        $record->comentarios()->create([
                                                                            'user_id'   => $usuario?->id,
                                                                            'contenido' => $comentario,
                                                                        ]);

                                                                        // Marcar interacción manual (bloquea autospam)
                                                                        $record->marcarInteraccionManual();

                                                                        // Notificación
                                                                        \Filament\Notifications\Notification::make()
                                                                            ->title('Acción registrada')
                                                                            ->success()
                                                                            ->send();
                                                                    })
                                                            ),


                                                        TextEntry::make('total')
                                                            ->label('🔥 Total')
                                                            ->state(
                                                                fn(Lead $record) => ($record->llamadas ?? 0)
                                                                    + ($record->emails ?? 0)
                                                                    + ($record->chats ?? 0)
                                                                    + ($record->otros_acciones ?? 0)
                                                            )
                                                            ->size('xl')
                                                            ->weight('extrabold')
                                                            ->color('warning')
                                                            ->alignment(Alignment::Center)
                                                            ->badge(),
                                                    ]),
                                                ])->columnSpanFull(),
                                        ]),

                                    ]),

                                //fin agenda y gestion
                            ]),
                        ])
                        ->columnSpanFull(),
                ]),


            Grid::make(3)
                ->columnSpanFull()
                ->schema([

                    Section::make('🤖 Autospam IA Boot Fy')
                        ->description('Últimos envíos automáticos asociados a este lead (🤖IA / autospam).')
                        ->headerActions([
                            Action::make('enviar_primer_email_ia')
                                ->label('Enviar primer email IA ahora')
                                ->visible(fn(Lead $record): bool => $record->puedeSugerirPrimerEmailIa())
                                ->icon('heroicon-m-sparkles')
                                ->color('success')
                                ->requiresConfirmation()
                                ->modalHeading('Enviar primer email IA automático')
                                ->modalSubheading('Se enviará el primer email de la secuencia según el estado actual del lead.')
                                ->action(function (Lead $record): void {
                                    SendLeadEstadoChangedEmailJob::dispatch(
                                        $record->id,
                                        $record->estado instanceof LeadEstadoEnum
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

                            // =========================
                            // AVISO DE PRIMER EMAIL IA
                            // =========================
                            TextEntry::make('autospam_sugerencia')
                                ->label(false)
                                ->visible(fn(Lead $record): bool => $record->puedeSugerirPrimerEmailIa())
                                ->html()
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
                                gap:1rem;
                                font-size:0.9rem;
                            ">
                                <div>
                                    <strong>Este lead nunca ha recibido un email automático IA.</strong><br>
                                    Tiene email y autospam activo. Puedes iniciar la secuencia desde el botón superior.
                                </div>
                            </div>
                        ';
                                }),

                            // =========================
                            // LOGS DE EMAILS IA
                            // =========================
                            RepeatableEntry::make('autoEmailLogs')
                                ->hiddenLabel()
                                ->contained(false)
                                ->schema([
                                    TextEntry::make('linea')
                                        ->hiddenLabel()
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

                                            $asunto = e($log->subject ?: '(sin asunto)');
                                            $url = LeadAutoEmailLogResource::getUrl('view', ['record' => $log->id]);

                                            return "
                                            <div style='
                                                display:flex;
                                                align-items:center;
                                                gap:12px;
                                                padding:8px 12px;
                                                border-radius:10px;
                                                border:1px solid rgba(148,163,184,0.35);
                                                background-color:rgba(15,23,42,0.04);
                                                font-size:14px;
                                            '>

                                                <span>{$icono}</span>

                                                <span style='color:#6b7280; font-size:12px; white-space:nowrap;'>
                                                    {$fecha}
                                                </span>

                                                <span style='
                                                    background-color:rgba(59,130,246,0.10);
                                                    color:#1d4ed8;
                                                    padding:2px 8px;
                                                    border-radius:999px;
                                                    font-size:12px;
                                                    font-weight:600;
                                                '>
                                                    #{$intento}
                                                </span>

                                                <span style='
                                                    background-color:{$estadoColor}20;
                                                    color:{$estadoColor};
                                                    padding:2px 8px;
                                                    border-radius:999px;
                                                    font-size:12px;
                                                    font-weight:600;
                                                '>
                                                    {$estadoTexto}
                                                </span>

                                                <a href=\"{$url}\" target=\"_blank\" style=\"
                                                    color:#2563eb;
                                                    text-decoration:underline;
                                                    font-weight:500;
                                                    white-space:normal;
                                                \">
                                                    {$asunto}
                                                </a>

                                            </div>";
                                        }),

                                ]),

                            // =========================
                            // VER MÁS LOGS
                            // =========================
                            TextEntry::make('ver_mas_logs')
                                ->label(false)
                                ->html()
                                ->visible(fn(Lead $lead) => $lead->autoEmailLogs()->count() > 10)
                                ->state(function (Lead $lead): string {
                                    $url = LeadAutoEmailLogResource::getUrl('index');
                                    $total = $lead->autoEmailLogs()->count();

                                    return "
                        <div style='margin-top:8px;font-size:13px;color:#4b5563;'>
                            Hay <strong>{$total}</strong> envíos automáticos para este lead.
                            <a href='{$url}' target='_blank' style='color:#2563eb;text-decoration:underline;'>
                                Ver todos en el log global
                            </a>
                        </div>
                        ";
                                }),

                        ])
                        ->visible(
                            fn(Lead $record) =>
                            $record->autoEmailLogs()->exists() || $record->puedeSugerirPrimerEmailIa()
                        )
                        ->collapsible()
                        ->collapsed(),
                    //->columnSpanFull(),

              Section::make('Documentación Legal')
            ->icon('heroicon-o-document-check')
            ->description('Acceso al contrato firmado y opciones de envío.')
            ->collapsible()
            ->collapsed()
            ->visible(fn (Lead $record) =>
                $record->conversionLinks()
                    ->whereNotNull('meta->pdf')
                    ->exists()
            )
            ->schema(function (Lead $record) {

                // 🔥 Ya NO dependemos de used_at (porque no lo estás seteando)
                // Elegimos el último link que tenga pdf
                $link = $record->conversionLinks()
                    ->whereNotNull('meta->pdf')
                    ->latest('id')
                    ->first();

                if (! $link) {
                    return [
                        TextEntry::make('no_pdf')
                            ->label(false)
                            ->default('Sin contrato firmado.'),
                    ];
                }

                $pdfPath = data_get($link->meta, 'pdf');
                if (! $pdfPath) {
                    return [
                        TextEntry::make('no_pdf')
                            ->label(false)
                            ->default('Sin contrato firmado.'),
                    ];
                }

                $pdfUrl  = \Illuminate\Support\Facades\Storage::disk('public')->url($pdfPath);

                // ✅ Fecha de firma robusta:
                // - si existe signed_at en la venta asociada, úsala
                // - si no, fallback a created_at del link
                $fechaFirma = null;

                try {
                    $ventaId = data_get($link->meta, 'existing_venta_id');
                    $venta = $ventaId ? \App\Models\Venta::find($ventaId) : null;
                    $fechaFirma = $venta?->signed_at ?? $link->created_at;
                } catch (\Throwable $e) {
                    $fechaFirma = $link->created_at;
                }

                return [

                    Grid::make(4)
                        ->schema([

                            // BOTÓN VER CONTRATO
                            Action::make('ver_contrato_pdf')
                                ->label('Ver contrato')
                                ->icon('heroicon-m-eye')
                                ->color('gray')
                                ->size('sm')
                                ->url($pdfUrl)
                                ->openUrlInNewTab(),

                            // BOTÓN ENVIAR COPIA
                            Action::make('reenviar_email_contrato')
                                ->label('Enviar copia')
                                ->icon('heroicon-m-paper-airplane')
                                ->color('primary')
                                ->size('sm')
                                ->requiresConfirmation()
                                ->modalHeading('Enviar copia del contrato')
                                ->modalDescription("Se enviará una copia del contrato firmado a {$record->email}.")
                                ->action(function () use ($record, $pdfPath) {

                                    $ruta = \Illuminate\Support\Facades\Storage::disk('public')->path($pdfPath);

                                    \Illuminate\Support\Facades\Mail::to($record->email)
                                        ->send(new ContractCopyMail($record, $ruta));

                                    $record->comentarios()->create([
                                        'user_id'   => auth()->id(),
                                        'contenido' => '📧📄 Copia del contrato enviada manualmente.',
                                    ]);

                                    Notification::make()
                                        ->title('Copia enviada')
                                        ->success()
                                        ->send();
                                }),

                            // FECHA DE FIRMA (robusta)
                            TextEntry::make('fecha_firma')
                                ->hiddenLabel()
                                ->html()
                                ->columnSpan(2)
                                ->state(fn () => "
                                    <div style='
                                        display:flex;
                                        align-items:center;
                                        gap:8px;
                                        padding:6px 12px;
                                        border-radius:6px;
                                        background-color:rgba(16,185,129,0.10);
                                        color:rgb(16,185,129);
                                        font-weight:600;
                                        white-space:nowrap;
                                    '>
                                        <span>📅 Fecha de firma:</span>
                                        <span>" . ($fechaFirma?->format('d/m/Y \\a \\l\\a\\s H:i') ?? '—') . "</span>
                                    </div>
                                "),
                        ])
                        ->columnSpanFull(),
                ];
            }),


                    // ->columnSpanFull(),
                Section::make('Gestión de Cobro y Ventas')
    ->icon('heroicon-o-currency-dollar')
    ->description('Estado de los pagos de las ventas asociadas a este lead.')
    ->visible(fn (Lead $record) => $record->ventas()->exists())
    ->schema([

        RepeatableEntry::make('ventas')
            ->hiddenLabel()
            ->contained(false)
            ->schema([

                // ==========================
                // CABECERA VENTA (lo tuyo, más legible en helperText)
                // ==========================
                Grid::make(4)->schema([

                    // 1️⃣ IDENTIFICACIÓN DE LA VENTA
                    TextEntry::make('concepto_venta')
                        ->label('Venta / Servicios')
                        ->icon('heroicon-m-shopping-bag')
                        ->formatStateUsing(fn (Venta $record) => "Venta #{$record->id}")
                        ->helperText(function (Venta $record) {
                            $txt = $record->items
                                ->filter(fn ($item) => $item->servicio)
                                ->map(fn ($item) => '• ' . $item->servicio->nombre . ' (' . ucfirst($item->servicio->tipo->value) . ')')
                                ->implode('<br>');

                            return new HtmlString($txt ?: '—');
                        })
                        ->url(fn (Venta $record) => VentaResource::getUrl('edit', ['record' => $record->id]))
                        ->color('primary'),

                    // 2️⃣ IMPORTE Y MÉTODO
                    TextEntry::make('importe_total')
                        ->label('Importe / Método')
                        ->weight('bold')
                        ->formatStateUsing(function (Venta $record) {

                            if (! $record->facturas()->exists()) {
                                return 'Pendiente de facturar';
                            }

                            $total = (float) $record->facturas()->sum('total_factura');

                            return number_format($total, 2, ',', '.') . ' €';
                        })
                        ->helperText(function (Venta $record) {
                            $metodo = ucfirst($record->pago_inicial_metodo ?? 'No definido');
                            $n = (int) $record->facturas()->count();
                            $txt = $n === 1 ? '1 factura' : "{$n} facturas";
                            return "{$metodo} · {$txt}";
                        }),

                    // 3️⃣ ESTADO DEL PAGO
                    TextEntry::make('estado_pago')
                        ->label('Estado Pago')
                        ->badge()
                        ->state(fn (Venta $record) => $record->tienePagoInicialCompletado() ? 'PAGADO' : 'PENDIENTE')
                        ->color(fn (string $state) => $state === 'PAGADO' ? 'success' : 'danger')
                        ->icon(fn (string $state) => $state === 'PAGADO' ? 'heroicon-m-check-circle' : 'heroicon-m-clock'),

                    // 4️⃣ ACCIÓN: RECORDATORIO DE PAGO
                    Action::make('enviar_recordatorio')
                        ->label('Recordar pago')
                        ->icon('heroicon-m-paper-airplane')
                        ->color('warning')
                        ->size('xs')
                        ->tooltip('Enviar email con instrucciones de pago')
                        ->visible(fn (Venta $record) => ! $record->tienePagoInicialCompletado() && $record->estado !== VentaEstadoEnum::CANCELADA)
                        ->requiresConfirmation()
                        ->modalHeading('Enviar recordatorio de pago')
                        ->modalDescription('Se enviará un email al cliente con las instrucciones de pago.')
                        ->action(function (Venta $record) {

                            if (! $record->cliente || ! $record->cliente->email_contacto) {
                                Notification::make()
                                    ->title('Error')
                                    ->body('El cliente no tiene email.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            try {
                                Mail::to($record->cliente->email_contacto)
                                    ->send(new RecordatorioPagoMail($record));

                                if ($record->lead_id && $record->lead) {

                                    LeadAutoEmailLog::create([
                                        'lead_id'              => $record->lead_id,
                                        'estado'               => $record->lead->estado->value ?? 'unknown',
                                        'intento'              => 1,
                                        'template_identifier'  => 'manual_payment_reminder',
                                        'subject'              => 'Recordatorio de Pago',
                                        'body_preview'         => 'Recordatorio manual enviado desde ficha Lead.',
                                        'scheduled_at'         => now(),
                                        'sent_at'              => now(),
                                        'status'               => 'sent',
                                        'triggered_by_user_id' => auth()->id(),
                                        'trigger_source'       => 'lead_infolist_action',
                                    ]);

                                    $record->lead->comentarios()->create([
                                        'user_id'   => auth()->id(),
                                        'contenido' => '📤 Recordatorio de pago enviado manualmente.',
                                    ]);
                                }

                                Notification::make()
                                    ->title('Recordatorio enviado')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Error al enviar')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    // 5️⃣ ACCIÓN: VER ÚLTIMA FACTURA (se mantiene)
                   /*  Action::make('ver_factura')
                        ->label('Factura')
                        ->icon('heroicon-m-document-text')
                        ->color('gray')
                        ->size('xs')
                        ->url(fn (Venta $record) => optional($record->facturas()->latest()->first())
                            ? route('facturas.generar-pdf', $record->facturas()->latest()->first())
                            : null
                        )
                        ->openUrlInNewTab()
                        ->visible(fn (Venta $record) => $record->tienePagoInicialCompletado() && $record->facturas()->exists()), */

                ]),

                // ==========================
                // NUEVO: DETALLE DE FACTURAS (todas)
                // ==========================
                RepeatableEntry::make('facturas_detalle')
                    ->label('Facturas')
                    ->contained(false)
                    ->visible(fn (Venta $record) => $record->facturas()->exists())
                    ->state(function (Venta $record) {
                        return $record->facturas()
                            ->latest('fecha_emision')
                            ->get()
                            ->map(function (FacturaModel $f) {
                                return [
                                    'numero'  => $f->numero_factura ?? $f->numero ?? ('Factura #' . $f->id),
                                    'fecha'   => optional($f->fecha_emision)->format('d/m/Y') ?? '—',
                                    'total'   => number_format((float) $f->total_factura, 2, ',', '.') . ' €',
                                    'estado'  => is_object($f->estado) ? ($f->estado->value ?? '—') : ($f->estado ?? '—'),
                                    'pdf_url' => route('facturas.generar-pdf', $f),
                                ];
                            })
                            ->all();
                    })
                    ->schema([
                        Grid::make(5)->schema([

                            TextEntry::make('numero')
                                ->label('Factura'),

                            TextEntry::make('fecha')
                                ->label('Fecha'),

                            TextEntry::make('total')
                                ->label('Total'),
                                //->alignEnd(),

                            TextEntry::make('estado')
                                ->label('Estado')
                                ->badge()
                                ->color(fn (string $state) => in_array(strtoupper($state), ['PAGADA', 'PAGADO'], true) ? 'success' : 'warning'),

                            // ✅ “Botón” por factura (badge clicable)
                            TextEntry::make('pdf_url')
                                ->label('') // si lo dejas vacío no molesta; si quieres pon 'PDF'
                                ->badge()
                                ->icon('heroicon-m-document-text')
                                ->color('gray')
                                ->formatStateUsing(fn () => 'Factura')   // 👈 lo que se ve
                                ->url(fn (?string $state) => $state)    // 👈 el link real
                                ->openUrlInNewTab()
                                ->visible(fn (?string $state) => filled($state)),


                        ]),
                    ])
                    ->columnSpanFull(),



            ]),
    ])
    ->collapsible()
    ->collapsed()


                ]),

            
            //fin formulario dentro
        ]);
    }


    // Método helper COMPLETO Y CORREGIDO para añadir info de agenda al comentario SOLO SI SE AGENDA

    protected static function registrarInteraccion(Lead $record, string $campoContador, string $comentarioTextoInicial, ?Carbon $agenda = null): void
    {
        // 1. Incrementamos el contador
        try {
            $record->increment($campoContador);
        } catch (Exception $e) {
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
            } catch (Exception $e) {
                Log::error('Error al actualizar fecha de agenda en registrarInteraccion para Lead ID ' . $record->id . ': ' . $e->getMessage());
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
        } catch (Exception $e) {
            Log::error('Error al guardar comentario (helper): ' . $e->getMessage(), [
                'lead_id' => $record->id,
                'user_id' => Auth::id(),
                'contenido_length' => strlen($comentarioTextoFinal ?? '')
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
                    ->url(
                        fn(Lead $record): ?string =>
                        $record->cliente_id
                            ? ClienteResource::getUrl('view', ['record' => $record->cliente_id])
                            : null
                    )
                    // Color amarillo (warning) si es enlace, gris si no
                    ->color(
                        fn(Lead $record): ?string =>
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
                    ->formatStateUsing(fn(?LeadEstadoEnum $state): string => $state?->getLabel() ?? '-')
                    ->color(fn(?LeadEstadoEnum $state): string => match ($state) {
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
                    ->color(fn($state) => str_contains($state, 'Sin Asignar') ? 'warning' : 'info')
                    ->searchable(/*
                query: function (Builder $query, string $search): Builder {
                    // Le decimos que busque Leads DONDE la relación 'asignado' EXISTA Y CUMPLA una condición:
                    // Que el campo 'name' (¡o 'full_name'!) de ese usuario asignado contenga el texto buscado.
                    // ***** ¡¡IMPORTANTE!! Cambia 'name' aquí si tu atributo en User es otro *****
                    return $query->orWhereHas('asignado', function (Builder $q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
                },
                isIndividual: true // Mantenemos la búsqueda individual para esta columna */)
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
                        fn(EloquentBuilder $query) => $query->whereHas('roles', fn(EloquentBuilder $q) => $q->where('name', 'comercial'))
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
                    Filter::make('sin_asesor')
                ->label('Sin Asesor Asignado')
                ->query(fn (Builder $query): Builder => $query->whereNull('asignado_id'))
                ->toggle(),
                // ->ranges([...]) // Puedes mantener tus rangos predefinidos

            ], layout: FiltersLayout::AboveContent) // Mantener layout
            ->filtersFormColumns(7) // Mantener columnas

            ->recordActions([ // Acciones de Fila


                ViewAction::make()
                    ->label('') // Sin etiqueta, solo icono
                    ->openUrlInNewTab() // Abrir en nueva pestaña
                    ->tooltip('Ver Detalles'), // Texto al pasar el ratón

                EditAction::make()
                    ->label('')
                    ->tooltip('Editar Lead'),
                Action::make('llamar')
                    ->icon('heroicon-o-phone-arrow-up-right')
                    ->label('')
                    ->tooltip('Registrar Llamada y Opcionalmente Reagendar')
                    ->color('primary')
                    // --- Usamos form() directamente, sin Wizard ---
                    ->schema([
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
                            ->required(fn(Get $get): bool => $get('actualizar_agenda') === true)
                            ->after('now')
                            ->visible(fn(Get $get): bool => $get('actualizar_agenda') === true),
                    ])
                    ->modalHeading(fn(?Lead $record): string => "Registrar Llamada a " . ($record?->nombre ?? 'este lead')) // <-- Añadir esta versión dinámica
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

                            } catch (Exception $e) {
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
                Action::make('enviarEmail')
                    ->icon('heroicon-o-envelope') // Icono cambiado
                    ->label('')
                    ->tooltip('Registrar Email Enviado y Opcionalmente Reagendar') // Texto cambiado
                    ->color('warning') // Color cambiado (ejemplo)
                    ->schema([
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
                            ->required(fn(Get $get): bool => $get('actualizar_agenda') === true)
                            ->after('now')
                            ->visible(fn(Get $get): bool => $get('actualizar_agenda') === true),
                    ])
                    ->modalHeading(fn(?Lead $record): string => "Registrar Email a " . ($record?->nombre ?? 'este lead')) // Título dinámico cambiado
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
                            } catch (Exception $e) {
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
                        $estadoActual = $record->estado instanceof LeadEstadoEnum
                            ? $record->estado->value
                            : (string) $record->estado;

                        if (in_array($estadoActual, [
                            LeadEstadoEnum::INTENTO_CONTACTO->value,
                            LeadEstadoEnum::ESPERANDO_INFORMACION->value,
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
                Action::make('chat')
                    ->icon('heroicon-o-chat-bubble-bottom-center-text')
                    ->label('')
                    ->tooltip('Registrar Chat y Opcionalmente Reagendar') // Texto cambiado
                    ->color('success') // Color cambiado (ejemplo)
                    ->schema([ // Lógica del formulario idéntica a 'llamar', solo cambia el texto del placeholder
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
                            ->hint(function (?Lead $record): ?string { /* ... misma lógica hint ... */
                                if ($record?->agenda) {
                                    $humanDiff = $record->agenda->diffForHumans();
                                    $formattedDate = $record->agenda->isoFormat('dddd D [de] MMMM, H:mm');
                                    return "Actualmente agendado {$humanDiff} (el {$formattedDate})";
                                }
                                return 'No hay seguimiento agendado.';
                            })
                            ->hintIcon('heroicon-m-information-circle'),
                        DateTimePicker::make('agenda_nueva') // Resto del form idéntico...
                            ->label('Nueva Fecha de Seguimiento')
                            ->minutesStep(30)
                            ->seconds(false)
                            ->prefixIcon('heroicon-o-clock')
                            ->native(false)
                            ->required(fn(Get $get): bool => $get('actualizar_agenda') === true)
                            ->after('now')
                            ->visible(fn(Get $get): bool => $get('actualizar_agenda') === true),
                    ])
                    ->modalHeading(fn(?Lead $record): string => "Registrar Chat con " . ($record?->nombre ?? 'este lead')) // Título dinámico cambiado
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
                            try {
                                $nuevaFechaAgenda = Carbon::parse($data['agenda_nueva']);
                                $record->agenda = $nuevaFechaAgenda;
                                $record->save();
                                $fechaAgendaFinal = $nuevaFechaAgenda;
                                $agendaActualizada = true;
                            } catch (Exception $e) {
                                Notification::make()->title('Error al procesar fecha')->danger()->send();
                                return;
                            }
                        }

                        // 3. Construir y crear el comentario (texto adaptado)
                        $textoComentario = "Chat registrado por {$userName}."; // <-- Texto cambiado
                        if ($fechaAgendaFinal instanceof Carbon) {
                            $textoRelativo = $fechaAgendaFinal->diffForHumans();
                            $textoComentario .= " Próximo seguimiento: {$textoRelativo}.";
                        } else {
                            $textoComentario .= " No hay próximo seguimiento agendado.";
                        }
                        $record->comentarios()->create(['user_id' => $currentUser->id, 'contenido' => $textoComentario]);

                        // 4. Enviar Notificación (texto adaptado)
                        if ($agendaActualizada) {
                            Notification::make()->title('Chat registrado y agenda actualizada')->success()->send();
                        } else {
                            Notification::make()->title('Chat registrado')->success()->send();
                        } // <-- Texto cambiado
                    }),


                // --- Acción Otros ---
                Action::make('otros')
                    ->icon('heroicon-o-paper-airplane')
                    ->label('')
                    ->tooltip('Registrar Otra Acción y Opcionalmente Reagendar') // Texto cambiado
                    ->color('gray') // Color cambiado (ejemplo)
                    ->schema([ // Lógica del formulario idéntica a 'llamar', solo cambia el texto del placeholder
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
                            ->hint(function (?Lead $record): ?string { /* ... misma lógica hint ... */
                                if ($record?->agenda) {
                                    $humanDiff = $record->agenda->diffForHumans();
                                    $formattedDate = $record->agenda->isoFormat('dddd D [de] MMMM, H:mm');
                                    return "Actualmente agendado {$humanDiff} (el {$formattedDate})";
                                }
                                return 'No hay seguimiento agendado.';
                            })
                            ->hintIcon('heroicon-m-information-circle'),
                        DateTimePicker::make('agenda_nueva') // Resto del form idéntico...
                            ->label('Nueva Fecha de Seguimiento')
                            ->minutesStep(30)
                            ->seconds(false)
                            ->prefixIcon('heroicon-o-clock')
                            ->native(false)
                            ->required(fn(Get $get): bool => $get('actualizar_agenda') === true)
                            ->after('now')
                            ->visible(fn(Get $get): bool => $get('actualizar_agenda') === true),
                    ])
                    ->modalHeading(fn(?Lead $record): string => "Registrar Otra Acción para " . ($record?->nombre ?? 'este lead')) // Título dinámico cambiado
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
                            try {
                                $nuevaFechaAgenda = Carbon::parse($data['agenda_nueva']);
                                $record->agenda = $nuevaFechaAgenda;
                                $record->save();
                                $fechaAgendaFinal = $nuevaFechaAgenda;
                                $agendaActualizada = true;
                            } catch (Exception $e) {
                                Notification::make()->title('Error al procesar fecha')->danger()->send();
                                return;
                            }
                        }

                        // 3. Construir y crear el comentario (texto adaptado)
                        $textoComentario = "Otra acción registrada por {$userName}."; // <-- Texto cambiado
                        if ($fechaAgendaFinal instanceof Carbon) {
                            $textoRelativo = $fechaAgendaFinal->diffForHumans();
                            $textoComentario .= " Próximo seguimiento: {$textoRelativo}.";
                        } else {
                            $textoComentario .= " No hay próximo seguimiento agendado.";
                        }
                        $record->comentarios()->create(['user_id' => $currentUser->id, 'contenido' => $textoComentario]);

                        // 4. Enviar Notificación (texto adaptado)
                        if ($agendaActualizada) {
                            Notification::make()->title('Otra acción registrada y agenda actualizada')->success()->send();
                        } else {
                            Notification::make()->title('Otra acción registrada')->success()->send();
                        } // <-- Texto cambiado
                    }),

            ])
            ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
            ->toolbarActions([ // Acciones Masivas
                BulkActionGroup::make([
                    ExportBulkAction::make('exportar_completo')
                        ->label('Exportar seleccionados')
                        ->exports([
                            ExcelExport::make('leads')
                                //->fromTable() // usa los registros seleccionados
                                ->withColumns([
                                    Column::make('id'),
                                    Column::make('nombre')
                                        ->heading('Nombre'),
                                    Column::make('email')
                                        ->heading('Email'),
                                    Column::make('tfn')
                                        ->heading('Teléfono'),
                                    Column::make('procedencia.procedencia')
                                        ->heading('Procedencia'),
                                    Column::make('creador.name')
                                        ->heading('Creador'),
                                    Column::make('asignado.name')
                                        ->heading('Asignado'),
                                    Column::make('estado')
                                        ->heading('Estado'),
                                    Column::make('demandado')
                                        ->heading('Demandado'),
                                    Column::make('fecha_gestion')
                                        ->heading('Fecha de gestión')
                                        ->formatStateUsing(fn($state) => Carbon::parse($state)->format('d/m/Y - H:i')),
                                    Column::make('agenda')
                                        ->heading('Agendado')
                                        ->formatStateUsing(fn($state) => Carbon::parse($state)->format('d/m/Y - H:i')),
                                    Column::make('fecha_cierre')
                                        ->heading('Fecha de cierre')
                                        ->formatStateUsing(fn($state) => Carbon::parse($state)->format('d/m/Y - H:i')),
                                    Column::make('observacion_cierre')
                                        ->heading('Observaciones cierre'),

                                    Column::make('motivoDescarte.motivo')
                                        ->heading('Motivo de descarte'),
                                    Column::make('cliente.nombre')
                                        ->heading('Cliente'),
                                    Column::make('llamadas')
                                        ->heading('Llamadas'),
                                    Column::make('emails')
                                        ->heading('Emails'),
                                    Column::make('chats')
                                        ->heading('Chats'),
                                    Column::make('otros_acciones')
                                        ->heading('Otras acciones'),
                                    Column::make('observaciones')
                                        ->heading('Observaciones'),
                                    Column::make('created_at')
                                        ->heading('Creado en App')
                                        ->formatStateUsing(fn($state) => Carbon::parse($state)->format('d/m/Y - H:i')),
                                    Column::make('updated_at')
                                        ->heading('Actualizado en App')
                                        ->formatStateUsing(fn($state) => Carbon::parse($state)->format('d/m/Y - H:i')),


                                ]),
                        ])
                        ->icon('icon-excel2')
                        ->color('success')
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->modalHeading('Exportar Leads Seleccionados')
                        ->modalDescription('Exportarás todos los datos de los Leads seleccionados.'),
                    DeleteBulkAction::make(),
                    // ExportBulkAction::make(), // Si usas exportación

                    // Acción Masiva: Asignar (Movida aquí y adaptada)
                    BulkAction::make('asignarComercial')
                        ->label('Asignar Comercial')
                        ->icon('heroicon-o-users')
                        ->schema([
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
                        ->action(function (array $data, EloquentCollection $records) {
                            $userID = $data['asignado_id_masivo'];
                            $records->each->update(['asignado_id' => $userID]); // Actualiza cada registro

                            // Notificar al usuario asignado (opcional, puede ser pesado si son muchos leads)
                            $assignedUser = User::find($userID);
                            if ($assignedUser) {
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
        \App\Filament\Resources\ClienteResource\RelationManagers\ComentariosRelationManager::class,
        \App\Filament\Resources\LeadResource\RelationManagers\DocumentosRelationManager::class,
    ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeads::route('/'),
            'create' => CreateLead::route('/create'),
            'view' => ViewLead::route('/{record}'),
            'edit' => EditLead::route('/{record}/edit'),
            //conversion nueva
            'conversion' => GestionarConversion::route('/{record}/conversion'),
        ];
    }
}
