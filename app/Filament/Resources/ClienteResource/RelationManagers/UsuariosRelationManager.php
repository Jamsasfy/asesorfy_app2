<?php

namespace App\Filament\Resources\ClienteResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\Filter;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

// ✅ IMPORTACIONES CORREGIDAS PARA FILAMENT v4
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;

class UsuariosRelationManager extends RelationManager
{
    protected static string $relationship = 'usuarios';
    protected static ?string $title = 'Usuarios con acceso';
    protected static ?string $recordTitleAttribute = 'email';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required(),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->unique(table: User::class, column: 'email', ignoreRecord: true)
                            ->required(),

                        TextInput::make('password')
                            ->label('Contraseña (dejar vacío para no cambiar)')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->confirmed()
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn (?string $state): bool => filled($state)),

                        TextInput::make('password_confirmation')
                            ->label('Confirmar Contraseña')
                            ->password()
                            ->revealable()
                            ->requiredWith('password'),

                        Toggle::make('portal_activo')
                            ->label('Acceso portal activado')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre'),
                TextColumn::make('email')->label('Email'),
                TextColumn::make('roles.name')
                    ->label('Rol')
                    ->badge()
                    ->color('primary')
                    ->getStateUsing(fn ($record) => $record->roles->pluck('name')->join(', '))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Creado en App')
                    ->dateTime('d/m/y - H:i')
                    ->sortable(),
                TextColumn::make('portal_activo')
                    ->label('Acceso Portal')
                    ->formatStateUsing(fn ($state) => $state ? '✅ Activo' : '❌ Inactivo')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'danger'),
            ])
            ->filters([
                Filter::make('nombre')
                    ->schema([
                        TextInput::make('nombre'),
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['nombre']) {
                            return $query->where('name', 'like', '%' . $data['nombre'] . '%');
                        }
                        return $query;
                    }),

                Filter::make('email')
                    ->schema([
                        TextInput::make('email'),
                    ])
                    ->query(function ($query, array $data) {
                        if ($data['email']) {
                            return $query->where('email', 'like', '%' . $data['email'] . '%');
                        }
                        return $query;
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)

            ->headerActions([
                // 1. EL NUEVO BOTÓN PARA VINCULAR USUARIOS EXISTENTES
                Action::make('vincular_usuario')
                    ->label('🔗 Vincular usuario existente')
                    ->color('info')
                    ->icon('heroicon-o-link')
                    ->schema([
                        Select::make('recordId')
                            ->label('Usuario')
                            ->options(fn () => User::where('acceso_app', false)->pluck('email', 'id'))
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(function ($state, $set) {
                                if (!$state) return;
                                $user = User::find($state);
                                if (!$user) return;
                                $set('clientes_actuales', $user->clientes()->pluck('razon_social')->toArray());
                            }),

                        Placeholder::make('info_clientes')
                            ->label('Empresas con acceso actual')
                            ->content(function ($get) {
                                $clientes = $get('clientes_actuales') ?? [];
                                if (empty($clientes)) {
                                    return '✅ Este usuario no tiene acceso a ninguna empresa todavía.';
                                }
                                $lista = implode("\n• ", $clientes);
                                return "⚠️ Este usuario ya tiene acceso a:\n\n• " . $lista;
                            })
                            ->visible(fn ($get) => !empty($get('recordId'))),

                        Hidden::make('clientes_actuales'),
                    ])
                    ->modalHeading('Vincular Usuario Existente')
                    ->modalDescription('Selecciona el usuario y confirma la vinculación')
                    ->modalSubmitActionLabel('Vincular Usuario')
                    ->action(function (array $data, $livewire) {
                        $cliente = $livewire->getOwnerRecord();
                        $userId  = $data['recordId'];

                        $cliente->usuarios()->syncWithoutDetaching([$userId]);

                        $user = User::find($userId);
                        if ($user) {
                            try {
                                \Illuminate\Support\Facades\Mail::to($user->email)
                                    ->send(new \App\Mail\UsuarioVinculadoMail($cliente, $user));

                                Notification::make()
                                    ->success()
                                    ->title('Usuario vinculado correctamente')
                                    ->body("Email de notificación enviado a {$user->email}")
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->warning()
                                    ->title('Usuario vinculado pero email no enviado')
                                    ->body('El usuario fue vinculado pero hubo un error al enviar el email de notificación.')
                                    ->persistent()
                                    ->send();
                            }
                        }
                    }),

                // 2. EL BOTÓN ORIGINAL PARA CREAR DESDE CERO
                CreateAction::make()
                    ->label('➕ Crear nuevo usuario')
                    ->icon('heroicon-o-user-plus')
                    ->modalHeading('Crear Nuevo Usuario para Cliente')
                    ->modalDescription('Se creará el usuario y se enviará un email de activación automáticamente')
                    ->form([
                        Section::make('Datos del Usuario')
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nombre completo')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->required()
                                    ->unique('users', 'email')
                                    ->maxLength(255),

                                Toggle::make('portal_activo')
                                    ->label('Acceso al portal activado')
                                    ->default(true)
                                    ->helperText('El usuario podrá acceder al portal una vez active su cuenta'),
                            ]),

                        Section::make('Información')
                            ->schema([
                                Placeholder::make('info_activacion')
                                    ->label('')
                                    ->content('ℹ️ El usuario recibirá un email con un link de activación válido por 72 horas. No es necesario introducir contraseña.'),
                            ]),
                    ])
                    ->using(function (array $data, $livewire): User {
                        $cliente = $livewire->getOwnerRecord();

                        $user = User::create([
                            'name'         => $data['name'],
                            'email'        => $data['email'],
                            'password'     => bcrypt(\Illuminate\Support\Str::random(32)),
                            'portal_activo' => $data['portal_activo'] ?? true,
                            'acceso_app'   => false,
                        ]);

                        $cliente->usuarios()->attach($user->id);

                        // Enviar email de activación (establece token + envía ClienteActivadoMail)
                        try {
                            app(\App\Services\ClienteActivacionService::class)
                                ->reenviarActivacion($cliente, $user, 'creacion_manual');

                            Notification::make()
                                ->success()
                                ->title('Usuario creado correctamente')
                                ->body("Email de activación enviado a {$user->email}")
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->warning()
                                ->title('Usuario creado pero email no enviado')
                                ->body('El usuario fue creado pero hubo un error al enviar el email. Usa el botón "Reenviar Activación".')
                                ->persistent()
                                ->send();
                        }

                        return $user;
                    })
                    ->successNotification(null),
            ])

            ->recordActions([
                EditAction::make(),
                // AÑADIMOS DESVINCULAR POR SEGURIDAD
                DetachAction::make()->label('Desvincular'),
                DeleteAction::make(),

                Action::make('reenviar_activacion')
                    ->label('Reenviar Activación')
                    ->icon('heroicon-o-envelope')
                    ->color('warning')
                    ->tooltip('Reenviar email de activación de cuenta')
                    ->visible(fn ($record) => !empty($record->activation_token))
                    ->requiresConfirmation()
                    ->modalHeading('Reenviar email de activación')
                    ->modalDescription(fn ($record) => "Se enviará un nuevo link de activación (válido 72h) a {$record->email}.")
                    ->modalSubmitActionLabel('Sí, reenviar')
                    ->action(function ($record, $livewire) {
                        $cliente = $livewire->getOwnerRecord();
                        $resultado = app(\App\Services\ClienteActivacionService::class)
                            ->reenviarActivacion($cliente, $record);

                        if ($resultado['success']) {
                            Notification::make()
                                ->title('✅ Email reenviado')
                                ->body("Nuevo link de activación enviado a {$record->email}.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('❌ Error al reenviar')
                                ->body($resultado['message'])
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('toggle_portal')
                    ->label('Portal')
                    ->icon(fn ($record) => $record->portal_activo ? 'heroicon-o-lock-open' : 'heroicon-o-lock-closed')
                    ->color(fn ($record) => $record->portal_activo ? 'success' : 'danger')
                    ->tooltip(fn ($record) => $record->portal_activo ? 'Portal activo' : 'Portal bloqueado')
                    ->requiresConfirmation()
                    ->modalHeading(fn ($record) => $record->portal_activo ? '🔒 Bloquear acceso al portal' : '🔓 Activar acceso al portal')
                    ->modalDescription(fn ($record) => $record->portal_activo 
                        ? 'El usuario no podrá acceder al portal (útil para impagos, suspensiones, etc.).' 
                        : 'El usuario podrá acceder de nuevo al portal.'
                    )
                    ->modalSubmitActionLabel(fn ($record) => $record->portal_activo ? 'Bloquear' : 'Activar')
                    ->action(function ($record) {
                        $record->update([
                            'portal_activo' => !$record->portal_activo,
                        ]);
                        
                        Notification::make()
                            ->title($record->portal_activo ? '✅ Portal activado' : '🔒 Portal bloqueado')
                            ->success()
                            ->send();
                    }),
            ])

            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()->label('Desvincular seleccionados'),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}