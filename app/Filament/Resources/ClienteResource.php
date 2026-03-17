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
use App\Filament\Resources\ClienteResource\RelationManagers\ContratosResponsabilidadRelationManager;
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

            // FILA 1: las 3 secciones principales
            \Filament\Schemas\Components\Grid::make(3)
                ->columnSpan(3)
                ->schema([

                    Section::make('Datos básicos del cliente')
                        ->icon('heroicon-o-user')
                        ->columnSpan(1)
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

                    Section::make('Dirección del cliente')
                        ->icon('heroicon-m-map-pin')
                        ->columnSpan(1)
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

                    Section::make('Estado y asignación')
                        ->icon('heroicon-o-shield-check')
                        ->columnSpan(1)
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

                ]),

            // FILA 2: Telegram
           \Filament\Schemas\Components\Grid::make(3)
                ->columnSpan(3)
                ->schema([


                   Section::make('Telegram y Comunicación')
                ->icon('icon-telegram')
                ->columnSpan(1)
                ->collapsed(true)
                ->description(fn ($record) => new HtmlString(
                    $record->chatConversacion?->telegram_chat_id
                        ? '<span style="display:inline-flex;align-items:center;gap:4px;background:#dcfce7;color:#15803d;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700;">● Vinculado</span>'
                        : (is_null($record->asesor_id)
                            ? '<span style="display:inline-flex;align-items:center;gap:4px;background:#fee2e2;color:#991b1b;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700;">● Sin vincular (Requiere asesor)</span>'
                            : '<span style="display:inline-flex;align-items:center;gap:4px;background:#fef9c3;color:#854d0e;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700;">● Sin vincular</span>')
                ))
                ->headerActions([

                    \Filament\Actions\Action::make('enviar_enlace_telegram')
                        ->label('Enviar enlace vinculación')
                        ->icon('icon-telegram')
                        ->color('info')
                        ->visible(fn ($record): bool =>
                            !is_null($record->asesor_id) &&
                            is_null($record->chatConversacion?->telegram_chat_id)
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Enviar enlace de vinculación')
                        ->modalDescription('Se generará un nuevo enlace y se enviará al email del cliente.')
                        ->modalSubmitActionLabel('Enviar enlace')
                        ->action(function ($record) {
                            $token = \Illuminate\Support\Str::random(48);

                            \App\Models\TelegramLink::query()
                                ->where('cliente_id', $record->id)
                                ->whereNull('used_at')
                                ->update(['expires_at' => now()->subMinute()]);

                            \App\Models\TelegramLink::create([
                                'cliente_id' => $record->id,
                                'token'      => $token,
                                'expires_at' => now()->addHours(48),
                                'used_at'    => null,
                            ]);

                            $linkUrl = url("/telegram/link/{$token}");

                            try {
                                \Illuminate\Support\Facades\Mail::to($record->email_contacto)
                                    ->send(new \App\Mail\TelegramVinculacionMail($record, $linkUrl));

                                \Filament\Notifications\Notification::make()
                                    ->title('✅ Enlace enviado')
                                    ->body('Se ha enviado el enlace al email del cliente.')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Error al enviar email')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    \Filament\Actions\Action::make('revincular_telegram')
                        ->label('Revincular')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->visible(fn ($record): bool =>
                            !is_null($record->asesor_id) &&
                            !is_null($record->chatConversacion?->telegram_chat_id)
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Revincular Telegram')
                        ->modalDescription('Se generará un enlace de revinculación y se enviará al email del cliente.')
                        ->modalSubmitActionLabel('Enviar enlace revinculación')
                        ->action(function ($record) {
                            $token = 'relink_' . \Illuminate\Support\Str::random(48);

                            \App\Models\TelegramLink::query()
                                ->where('cliente_id', $record->id)
                                ->whereNull('used_at')
                                ->update(['expires_at' => now()->subMinute()]);

                            \App\Models\TelegramLink::create([
                                'cliente_id' => $record->id,
                                'token'      => $token,
                                'expires_at' => now()->addHours(48),
                                'used_at'    => null,
                            ]);

                            $linkUrl = url("/telegram/link/{$token}");

                            try {
                            \Illuminate\Support\Facades\Mail::to($record->email_contacto)
                ->send(new \App\Mail\TelegramVinculacionMail($record, $linkUrl));

                                \Filament\Notifications\Notification::make()
                                    ->title('✅ Enlace de revinculación enviado')
                                    ->body('Se ha enviado el enlace al email del cliente.')
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Error al enviar email')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                    \Filament\Actions\Action::make('desvincular_telegram')
                        ->label('Desvincular')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn ($record): bool =>
                            !is_null($record->chatConversacion?->telegram_chat_id)
                        )
                        ->requiresConfirmation()
                        ->modalHeading('¿Desvincular Telegram?')
                        ->modalDescription('Esto eliminará la vinculación de Telegram de este cliente.')
                        ->modalSubmitActionLabel('Sí, desvincular')
                        ->action(function ($record) {
                            $record->chatConversacion?->update([
                                'telegram_chat_id'    => null,
                                'telegram_thread_id'  => null,
                                'telegram_username'   => null,
                                'telegram_first_name' => null,
                            ]);

                            \Filament\Notifications\Notification::make()
                                ->title('🔌 Telegram desvinculado')
                                ->danger()
                                ->send();
                        }),

                ])
                ->schema([
                    TextEntry::make('telegram_estado')
                        ->label('Estado vinculación')
                        ->badge()
                        ->getStateUsing(fn ($record) => match(true) {
                            $record->chatConversacion?->telegram_chat_id !== null => 'Vinculado',
                            is_null($record->asesor_id) => 'Sin vincular (Requiere asesor)',
                            default => 'Sin vincular'
                        })
                        ->color(fn ($state) => match($state) {
                            'Vinculado' => 'success',
                            'Sin vincular (Requiere asesor)' => 'danger',
                            default => 'warning',
                        }),

                    TextEntry::make('telegram_username')
                        ->label('Usuario Telegram')
                        ->getStateUsing(fn ($record) => $record->chatConversacion?->telegram_username
                            ? '@' . $record->chatConversacion->telegram_username
                            : '—')
                        ->copyable(),

                    TextEntry::make('telegram_chat_id')
                        ->label('Chat ID')
                        ->getStateUsing(fn ($record) => $record->chatConversacion?->telegram_chat_id ?? '—')
                        ->copyable(),

                    TextEntry::make('ultimo_mensaje')
                        ->label('Último mensaje')
                        ->getStateUsing(fn ($record) => $record->chatConversacion?->last_message_at
                            ? $record->chatConversacion->last_message_at->format('d/m/Y H:i')
                            : '—'),

                    TextEntry::make('telegram_first_name')
                        ->label('Nombre en Telegram')
                        ->getStateUsing(fn ($record) => $record->chatConversacion?->telegram_first_name ?? '—'),

                    TextEntry::make('mensajes_sin_leer')
                        ->label('Sin leer')
                        ->badge()
                        ->getStateUsing(fn ($record) => $record->chatConversacion?->unread_count ?? 0)
                        ->color(fn ($state) => $state > 0 ? 'danger' : 'gray'),
                ])
                ->columns(3),

                    Section::make('Contratos firmados')
                        ->icon('heroicon-o-document-check')
                        ->columnSpan(1)
                        ->collapsed(true)
                        ->description('Contrato de servicios firmados.')
                        ->schema(function ($record) {

                            $items = [];

                            // ── Contrato de servicios ──
                            $link = \App\Models\LeadConversionLink::where(function($q) use ($record) {
                                $q->where('meta->existing_cliente_id', $record->id)
                                  ->orWhere('meta->cliente_id', $record->id);
                            })
                            ->whereNotNull('meta->pdf')
                            ->latest('id')
                            ->first();

                            if ($link) {
                                $pdfPath = data_get($link->meta, 'pdf');
                                $pdfUrl  = \Illuminate\Support\Facades\Storage::disk('public')->url($pdfPath);
                                $ventaId = data_get($link->meta, 'existing_venta_id');
                                $venta   = $ventaId ? \App\Models\Venta::find($ventaId) : null;
                                $fecha   = $venta?->signed_at ?? $link->created_at;

                                $items[] = \Filament\Infolists\Components\TextEntry::make('contrato_servicios')
                                    ->label('Contrato de servicios')
                                    ->html()
                                    ->getStateUsing(fn () => new \Illuminate\Support\HtmlString(
                                        "<a href='{$pdfUrl}' target='_blank' style='color:#0ea5e9;text-decoration:underline;'>
                                            📄 Ver contrato firmado — " . $fecha->format('d/m/Y H:i') . "
                                        </a>"
                                    ));
                            } else {
                                $items[] = \Filament\Infolists\Components\TextEntry::make('sin_contrato_servicios')
                                    ->label('Contrato de servicios')
                                    ->getStateUsing(fn () => 'Sin contrato firmado.');
                            }

                           

                            return $items;
                        }),
                ]),
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
             // 👇 NUEVA COLUMNA DE TELEGRAM 👇
            TextColumn::make('chat_telegram')
                ->label('Telegram')
                ->getStateUsing(function ($record) {
                    $chat = \App\Models\ChatConversacion::where('cliente_id', $record->id)
                        ->where('tipo', 'cliente')
                        ->first();
                        
                    if (!$chat || !$chat->telegram_chat_id) {
                        return 'Sin vincular';
                    }
                    
                    $nombre = $chat->telegram_first_name ?: 'Vinculado';
                    $user = $chat->telegram_username ? ' (@' . $chat->telegram_username . ')' : '';
                    
                    return $nombre . $user;
                })
                ->badge()
                ->color(fn (string $state): string => $state === 'Sin vincular' ? 'danger' : 'success')
                ->toggleable(),
            // 👆 FIN NUEVA COLUMNA 👆   

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
                
              \Filament\Tables\Filters\TernaryFilter::make('telegram_vinculado')
                ->label('Estado Telegram')
                ->placeholder('Todos')
                ->trueLabel('Vinculado ✅')
                ->falseLabel('Sin vincular ❌')
                ->queries(
                    true: fn ($query) => $query->whereIn('id', \App\Models\ChatConversacion::whereNotNull('telegram_chat_id')->select('cliente_id')),
                    false: fn ($query) => $query->whereNotIn('id', \App\Models\ChatConversacion::whereNotNull('telegram_chat_id')->select('cliente_id'))
                ),

            // =========================================================
            // 🔥 FILTROS RECOMENDADOS SEGÚN TU ARQUITECTURA 🔥
            // =========================================================

            // 2. TARIFA ACTIVA: Brutal para ver quién te está pagando y quién está de "oyente"
            \Filament\Tables\Filters\TernaryFilter::make('tarifa_activa')
                ->label('Tarifa Principal')
                ->placeholder('Todos')
                ->trueLabel('Con tarifa activa 💰')
                ->falseLabel('Sin tarifa (Potencial baja) ⚠️')
                ->queries(
                    true: fn ($query) => $query->whereHas('suscripciones', fn ($q) => 
                        $q->where('es_tarifa_principal', true)->where('estado', \App\Enums\ClienteSuscripcionEstadoEnum::ACTIVA)
                    ),
                    false: fn ($query) => $query->whereDoesntHave('suscripciones', fn ($q) => 
                        $q->where('es_tarifa_principal', true)->where('estado', \App\Enums\ClienteSuscripcionEstadoEnum::ACTIVA)
                    )
                ),

            // 3. ACCESO A LA APP WEB: Para saber a qué clientes tienes que invitar a la plataforma web
            \Filament\Tables\Filters\TernaryFilter::make('acceso_app')
                ->label('Acceso a Plataforma Web')
                ->placeholder('Todos')
                ->trueLabel('Con acceso (Usuarios creados) 👤')
                ->falseLabel('Sin acceso 👻')
                ->queries(
                    true: fn ($query) => $query->has('usuarios'),
                    false: fn ($query) => $query->doesntHave('usuarios')
                ),

            // 4. METODO DE PAGO (STRIPE): Fundamental para facturación. Evita que te dejen pufos.
            \Filament\Tables\Filters\TernaryFilter::make('metodo_pago')
                ->label('Método de Pago (Stripe)')
                ->placeholder('Todos')
                ->trueLabel('Configurado 💳')
                ->falseLabel('Sin método de pago 🚨')
                ->queries(
                    true: fn ($query) => $query->whereNotNull('stripe_customer_id'),
                    false: fn ($query) => $query->whereNull('stripe_customer_id')
                ),  

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

                // 👇 NUEVA ACCIÓN: DESVINCULAR TELEGRAM 👇
            Action::make('desvincularTelegram')
                ->label('')
                ->tooltip('Desvincular cuenta de Telegram')
                ->icon('icon-telegram')
                ->color('danger')
                ->visible(fn ($record) => 
                    auth()->user()?->can('Chats:UnlinkTelegram') && 
                    \App\Models\ChatConversacion::where('cliente_id', $record->id)
                        ->whereNotNull('telegram_chat_id')
                        ->exists()
                )
                ->requiresConfirmation()
                ->modalHeading('¿Desvincular Telegram del cliente?')
                ->modalDescription('Esto cortará la conexión con la cuenta de Telegram actual, impidiendo que el usuario envíe o reciba más mensajes, y caducará los enlaces pendientes. Tendrás que generar un enlace nuevo si quieres volver a vincularlo.')
                ->modalSubmitActionLabel('Sí, desvincular')
                ->action(function ($record) {
                    $chats = \App\Models\ChatConversacion::where('cliente_id', $record->id)
                        ->whereNotNull('telegram_chat_id')
                        ->get();
                    
                    if ($chats->isEmpty()) return;

                    foreach ($chats as $chat) {
                        $oldTelegramChatId = $chat->telegram_chat_id;

                        \Illuminate\Support\Facades\DB::transaction(function () use ($record, $chat) {
                            // 1. Limpiamos la conexión en el chat
                            $chat->update([
                                'telegram_chat_id'    => null,
                                'telegram_thread_id'  => null,
                                'telegram_username'   => null,
                                'telegram_first_name' => null,
                            ]);

                            // 2. Caducamos enlaces para que no pueda usar los viejos
                            \App\Models\TelegramLink::where('cliente_id', $record->id)
                                ->whereNull('used_at')
                                ->update(['expires_at' => now()]);
                                
                            // 3. Dejamos un log en el chat
                            \App\Models\ChatMensaje::create([
                                'chat_id'   => $chat->id,
                                'origen'    => 'sistema',
                                'tipo'      => 'text',
                                'contenido' => '🔌 Telegram desvinculado manualmente por administración.',
                                'leido'     => true,
                            ]);
                        });

                      // Obtenemos el nombre de la empresa para el mensaje
                        $nombreEmpresa = $record->razon_social ?? trim(($record->nombre ?? '') . ' ' . ($record->apellidos ?? '')) ?: 'tu empresa';

                        // 4. Intentamos avisar al Telegram del cliente que ha sido expulsado
                        try {
                            app(\App\Services\TelegramService::class)->sendMessage(
                                $oldTelegramChatId, 
                                "🔌 La cuenta de " . $nombreEmpresa . " ha sido desvinculada de AsesorFy por administración.\n\nPara volver a acceder, solicita un nuevo enlace."
                            );
                        } catch (\Throwable $e) {
                            // Ignorar si falla el envío (ej. el cliente bloqueó al bot)
                        }
                    }

                    Notification::make()
                        ->success()
                        ->title('Telegram desvinculado con éxito')
                        ->send();
                }),
            // 👆 FIN NUEVA ACCIÓN 👆


              
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
         ContratosResponsabilidadRelationManager::class,
      
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
