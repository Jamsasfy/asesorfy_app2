<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificacionPortalResource\Pages;
use App\Models\NotificacionPortal;
use App\Models\Servicio;
use App\Models\Cliente;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;

class NotificacionPortalResource extends Resource
{
    protected static ?string $model = NotificacionPortal::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel = 'Notificaciones Portal';
    protected static ?string $modelLabel = 'Notificación';
    protected static ?string $pluralModelLabel = 'Notificaciones Portal';
    protected static string|\UnitEnum|null $navigationGroup = 'Portal';
    protected static ?int $navigationSort = 1;


    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Contenido de la Notificación')
                    ->schema([
                        TextInput::make('titulo')
                            ->label('Título')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        RichEditor::make('mensaje')
                            ->label('Mensaje')
                            ->required()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'bulletList',
                                'orderedList',
                                'link',
                            ])
                            ->columnSpanFull(),
                    ]),

                Section::make('Configuración')
                    ->schema([
                        Select::make('tipo')
                            ->label('Tipo de Notificación')
                            ->required()
                            ->options([
                                'info' => '🔵 Informativa',
                                'aviso' => '🟡 Aviso',
                                'urgente' => '🟠 Urgente',
                                'critico' => '🔴 Crítico',
                            ])
                            ->default('info')
                            ->live(),

                        CheckboxList::make('canales')
                            ->label('Canales de Envío')
                            ->options([
                                'plataforma' => 'Plataforma (Dashboard)',
                                'email' => 'Email',
                                'telegram' => 'Telegram',
                            ])
                            ->default(['plataforma'])
                            ->required()
                            ->columns(3),

                        Toggle::make('bloquea_portal')
                            ->label('Bloquear portal hasta que sea leída')
                            ->helperText('Solo recomendado para notificaciones CRÍTICAS')
                            ->default(false)
                            ->visible(fn ($get) => $get('tipo') === 'critico'),

                        Toggle::make('activa')
                            ->label('Notificación activa')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Destinatarios')
                    ->schema([
                        Select::make('destinatarios')
                            ->label('Enviar a')
                            ->required()
                            ->options([
                                'todos' => 'Todos los clientes',
                                'por_servicio' => 'Clientes con servicio específico',
                                'cliente_especifico' => 'Clientes específicos',
                            ])
                            ->default('todos')
                            ->live(),

                        Select::make('filtro_servicios')
                            ->label('Servicios')
                            ->multiple()
                            ->options(fn () => Servicio::pluck('nombre', 'id'))
                            ->visible(fn ($get) => $get('destinatarios') === 'por_servicio')
                            ->required(fn ($get) => $get('destinatarios') === 'por_servicio'),

                        Select::make('filtro_clientes')
                            ->label('Clientes')
                            ->multiple()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => Cliente::where('razon_social', 'like', "%{$search}%")
                                ->orWhere('nombre', 'like', "%{$search}%")
                                ->orWhere('dni_cif', 'like', "%{$search}%")
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn ($c) => [$c->id => $c->razon_social . ' (' . $c->dni_cif . ')']))
                            ->visible(fn ($get) => $get('destinatarios') === 'cliente_especifico')
                            ->required(fn ($get) => $get('destinatarios') === 'cliente_especifico'),
                    ])
                    ->columns(1),

                Section::make('Programación')
                    ->schema([
                        DateTimePicker::make('fecha_publicacion')
                            ->label('Fecha de Publicación')
                            ->helperText('Dejar vacío para publicar inmediatamente')
                            ->seconds(false),

                        DateTimePicker::make('fecha_caducidad')
                            ->label('Fecha de Caducidad')
                            ->helperText('Dejar vacío para que no caduque nunca')
                            ->seconds(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('titulo')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match($state) {
                        'info' => '🔵 Info',
                        'aviso' => '🟡 Aviso',
                        'urgente' => '🟠 Urgente',
                        'critico' => '🔴 Crítico',
                        default => $state,
                    })
                    ->color(fn ($state) => match($state) {
                        'info' => 'info',
                        'aviso' => 'warning',
                        'urgente' => 'warning',
                        'critico' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('canales')
                    ->label('Canales')
                    ->badge()
                    ->formatStateUsing(fn ($state) => is_array($state)
                        ? collect($state)->map(fn ($c) => match($c) {
                            'plataforma' => '💻 Portal',
                            'email' => '📧 Email',
                            'telegram' => '✈️ Telegram',
                            default => $c,
                        })->implode(', ')
                        : $state),

                IconColumn::make('bloquea_portal')
                    ->label('Bloquea')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('danger')
                    ->falseColor('success'),

                TextColumn::make('destinatarios')
                    ->label('Destinatarios')
                    ->formatStateUsing(fn ($state) => match($state) {
                        'todos' => 'Todos',
                        'por_servicio' => 'Por servicio',
                        'cliente_especifico' => 'Específicos',
                        default => $state,
                    })
                    ->badge()
                    ->color('info'),

                IconColumn::make('activa')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('portal_leidas')
                    ->label('📱 Portal')
                    ->getStateUsing(function ($record) {
                        if (!in_array('plataforma', $record->canales ?? [])) {
                            return '—';
                        }
                        $leidas = $record->contarLeidasPortal();
                        $total = $record->contarTotalDestinatarios();
                        return "{$leidas} / {$total}";
                    })
                    ->badge()
                    ->color(function ($record) {
                        if (!in_array('plataforma', $record->canales ?? [])) {
                            return 'gray';
                        }
                        return $record->contarLeidasPortal() === $record->contarTotalDestinatarios() ? 'success' : 'warning';
                    }),

                TextColumn::make('email_enviados')
                    ->label('📧 Email')
                    ->getStateUsing(function ($record) {
                        if (!in_array('email', $record->canales ?? [])) {
                            return '—';
                        }
                        $enviados = $record->contarEnviosEmail();
                        return "{$enviados} enviados";
                    })
                    ->badge()
                    ->color(fn ($record) => in_array('email', $record->canales ?? []) ? 'info' : 'gray'),

                TextColumn::make('telegram_enviados')
                    ->label('✈️ Telegram')
                    ->getStateUsing(function ($record) {
                        if (!in_array('telegram', $record->canales ?? [])) {
                            return '—';
                        }
                        $enviados = $record->contarEnviosTelegram();
                        return "{$enviados} enviados";
                    })
                    ->badge()
                    ->color(fn ($record) => in_array('telegram', $record->canales ?? []) ? 'info' : 'gray'),

                TextColumn::make('enviada_at')
                    ->label('Enviada el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('No enviada')
                    ->toggleable(),

                TextColumn::make('fecha_publicacion')
                    ->label('Programada para')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Envío inmediato')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),

                Action::make('enviar')
                    ->label('Enviar ahora')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (NotificacionPortal $record) {
                        dispatch(new \App\Jobs\EnviarNotificacionPortalJob($record));

                        \Filament\Notifications\Notification::make()
                            ->title('Notificación enviándose')
                            ->body('Se está enviando a los destinatarios seleccionados')
                            ->success()
                            ->send();
                    })
                    ->visible(fn ($record) => $record->activa)
                    ->authorize('enviar'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotificacionesPortal::route('/'),
            'create' => Pages\CreateNotificacionPortal::route('/create'),
            'edit' => Pages\EditNotificacionPortal::route('/{record}/edit'),
            'view' => Pages\ViewNotificacionPortal::route('/{record}'),
        ];
    }
}
