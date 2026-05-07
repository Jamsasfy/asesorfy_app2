<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static string | \UnitEnum | null $navigationGroup = 'Usuarios plataforma';
    protected static ?string $navigationLabel = 'Usuarios Web';
    protected static ?string $modelLabel = 'Usuario web';
    protected static ?string $pluralModelLabel = 'Usuarios con acceso web';

            public static function getEloquentQuery(): Builder
        {
            return parent::getEloquentQuery()
                ->where('id', '!=', 9999);   // ⛔ Ocultamos a Boot IA Fy siempre
        }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('super_admin');
    }
    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin');
    }
    

   public static function form(Schema $schema): Schema
{
    return $schema
        ->components([
            // ===============================
            // CREACIÓN / EDICIÓN USUARIO WEB
            // ===============================
            Section::make('Usuario con acceso a AsesorFy')
                ->description('Configuración de acceso y permisos del usuario en la plataforma.')
                ->icon('heroicon-o-user-plus')
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->suffixIcon('heroicon-m-user-circle')
                        ->maxLength(191)
                        ->columnSpan(2),

                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->suffixIcon('heroicon-m-at-symbol')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(191)
                        ->columnSpan(2),

                    TextInput::make('password')
                        ->label('Contraseña')
                        ->password()
                        ->revealable()
                        ->required(fn ($livewire) => $livewire instanceof CreateRecord)
                        ->visible(fn ($livewire) => $livewire instanceof CreateRecord)
                        ->dehydrateStateUsing(fn ($state) =>
                            filled($state) ? Hash::make($state) : null
                        )
                        ->columnSpan(2),

                    TextInput::make('password_confirmation')
                        ->label('Confirmar contraseña')
                        ->password()
                        ->revealable()
                        ->dehydrated(false)
                        ->required(fn ($livewire) => $livewire instanceof CreateRecord)
                        ->visible(fn ($livewire) => $livewire instanceof CreateRecord)
                        ->same('password')
                        ->helperText('Repite la contraseña.')
                        ->columnSpan(2),
                    
                    Select::make('roles')
                        ->label('Rol del usuario')
                        ->options(fn () => \Spatie\Permission\Models\Role::pluck('name', 'id'))
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->required()
                        ->native(false)
                        ->suffixIcon('heroicon-m-shield-check')
                        ->default(fn ($record) => $record?->roles->pluck('id')->toArray() ?? [])
                        ->afterStateHydrated(function ($component, $state, $record) {
                            // Cargar roles actuales al editar
                            if ($record) {
                                $component->state($record->roles->pluck('id')->toArray());
                            }
                        })
                        ->columnSpan(4),
                    
                    Toggle::make('acceso_app')
                        ->label('Acceso al panel de administración')
                        ->helperText('Permite acceso al panel admin de Filament.')
                        ->default(false)
                        ->inline(false)
                        ->live()
                        ->visible(fn ($record) => 
                            $record?->trabajador !== null || // Es trabajador
                            $record?->hasRole('super_admin') || // Es super admin
                            $record?->acceso_app == 1 // O ya tiene acceso admin activado
                        )
                        ->columnSpan(2),
                    
                    Toggle::make('portal_activo')
                        ->label('Acceso al portal activo')
                        ->helperText('Desactivar para bloquear acceso (impagos, suspensiones, etc.).')
                        ->default(true)
                        ->inline(false)
                        ->columnSpan(2),
                    
                    Forms\Components\Placeholder::make('tipo_usuario_info')
                        ->label('Tipo de usuario')
                        ->content(fn (callable $get) => 
                            $get('acceso_app') 
                                ? '👨💼 Trabajador - Puede acceder al panel de administración' 
                                : '👤 Cliente - Solo acceso al portal cliente'
                        )
                        ->columnSpan(4),
                ])
                ->columns(4)
                ->columnSpanFull(),

            // ===============================
            // CAMBIO DE CONTRASEÑA (SOLO EDIT)
            // ===============================
            Section::make('Actualizar contraseña')
                ->description('Cambiar la contraseña del usuario.')
                ->icon('heroicon-o-key')
                ->schema([
                    TextInput::make('password')
                        ->label('Nueva contraseña')
                        ->password()
                        ->revealable()
                        ->nullable()
                        ->helperText('Déjalo vacío si no quieres cambiarla')
                        ->dehydrated(fn ($state) => filled($state))
                        ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                        ->maxLength(191),

                    TextInput::make('password_confirmation')
                        ->label('Confirmar contraseña')
                        ->password()
                        ->revealable()
                        ->nullable()
                        ->dehydrated(false)
                        ->requiredWith('password')
                        ->same('password')
                        ->helperText('Solo obligatorio si cambias la contraseña'),
                ])
                ->columns(2)
                ->visible(fn ($livewire) => $livewire instanceof EditRecord),

        ]);
}


    public static function table(Table $table): Table
    {
        return $table
        ->striped()
        ->paginated([10, 25, 50, 100, 'all'])
        ->defaultPaginationPageOption(25)
        ->extremePaginationLinks()
        ->poll('30s')
            ->columns([
                TextColumn::make('name')
                ->label('Nombre')
                    ->searchable(),
                TextColumn::make('email')
                ->label('Correo de registro')
                    ->searchable(),
                TextColumn::make('roles.name') 
                    ->label('Rol del usuario')
                    ->badge()    
                    ->color('primary')                    
                    ->searchable(),  
                    TextColumn::make('tipo')
                        ->label('Tipo')
                        ->state(fn ($record) => $record) // 👈 AÑADIR ESTO
                        ->formatStateUsing(fn ($record) => $record->acceso_app ? '👨💼 Trabajador' : '👤 Cliente')
                        ->badge()
                        ->color(fn ($record) => $record->acceso_app ? 'warning' : 'info'),
                    
                    TextColumn::make('acceso')
                        ->label('Acceso')
                        ->state(fn ($record) => $record) // 👈 AÑADIR ESTO
                        ->formatStateUsing(function ($record) {
                            if ($record->acceso_app) {
                                return 'Panel Admin';
                            }
                            return $record->portal_activo ? 'Portal ✅' : 'Portal 🔒';
                        })
                        ->badge()
                        ->color(function ($record) {
                            if ($record->acceso_app) {
                                return 'success';
                            }
                            return $record->portal_activo ? 'success' : 'danger';
                        }),    
                TextColumn::make('created_at')
                ->label('Fecha creación')
                    ->dateTime('d/m/y - H:m')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('updated_at')
                    ->label('Fecha actualización')
                    ->dateTime('d/m/y - H:m')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('email')
                ->schema([
                    TextInput::make('value')
                        ->label('Email')
                        ->placeholder('Buscar email...'),
                ])
                ->query(fn ($query, $data) =>
                    $data['value'] ? $query->where('email', 'like', "%{$data['value']}%") : $query
                )
                ->indicateUsing(fn ($data) =>
                    $data['value'] ? 'Email: ' . $data['value'] : null
                ),
        
            // 👤 Filtrar por nombre (input libre)
            Filter::make('name')
                ->schema([
                    TextInput::make('value')
                        ->label('Nombre')
                        ->placeholder('Buscar nombre...'),
                ])
                ->query(fn ($query, $data) =>
                    $data['value'] ? $query->where('name', 'like', "%{$data['value']}%") : $query
                )
                ->indicateUsing(fn ($data) =>
                    $data['value'] ? 'Nombre: ' . $data['value'] : null
                ),
        
                SelectFilter::make('roles')
                ->label('Rol')
                ->relationship('roles', 'name')
                ->multiple() // Puedes ponerlo si quieres seleccionar más de uno
                ->searchable()
                ->preload(),

                SelectFilter::make('acceso_app')
                ->label('Acceso a la app')
                ->options([
                    '1' => 'Con acceso',
                    '0' => 'Sin acceso',
                ])
                ->placeholder('Todos'),

                DateRangeFilter::make('created_at')
                ->label('Alta APP')
                ->placeholder('Rango de fechas a buscar'),  
                DateRangeFilter::make('updated_at')
                ->label('Actualizado en la APP')
                ->placeholder('Rango de fechas a buscar'),      
                  
            ],layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(6)
            ->recordActions([
                EditAction::make()
                    ->label(''),
                
                ViewAction::make()
                    ->label(''),
                
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
                
                DeleteAction::make()
                    ->label('')
                    ->before(function ($record, $action) {
                        if ($record->trabajador) {
                            Notification::make()
                                ->title('⛔ No se puede eliminar')
                                ->body('Este usuario está vinculado a un trabajador. Elimínalo desde la sección Trabajadores.')
                                ->danger()
                                ->persistent()
                                ->send();
                            
                            $action->cancel();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
