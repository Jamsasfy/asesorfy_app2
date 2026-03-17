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
                AttachAction::make()
                    ->label('🔗 Vincular usuario existente')
                    ->color('info')
                    ->preloadRecordSelect(),

                // 2. EL BOTÓN ORIGINAL PARA CREAR DESDE CERO
                CreateAction::make()
                    ->label('➕ Crear nuevo usuario')
                    ->icon('heroicon-o-user-plus')
                    ->modalHeading('Nuevo usuario con acceso al cliente')
                    ->using(function (array $data, $livewire): User {
                        $cliente = $livewire->getOwnerRecord();
                        $user = User::create($data);
                        $cliente->usuarios()->attach($user->id);
                        return $user;
                    })
                    ->after(function (User $record): void {
                        Notification::make()
                            ->title('✅ Usuario creado correctamente')
                            ->body("Se ha creado un usuario con NOMBRE: 👤 <span style='color:#2563eb; font-weight:bold'>{$record->name}</span> para acceder a este cliente en la plataforma AsesorFy.")
                            ->success()
                            ->send();
                    }),
            ])

            ->recordActions([
                EditAction::make(),
                // AÑADIMOS DESVINCULAR POR SEGURIDAD
                DetachAction::make()->label('Desvincular'),
                DeleteAction::make(),

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