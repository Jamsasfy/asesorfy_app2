<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
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
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
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

class UserResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static string | \UnitEnum | null $navigationGroup = 'Usuarios plataforma';
    protected static ?string $navigationLabel = 'Usuarios Web';
    protected static ?string $modelLabel = 'Usuario web';
    protected static ?string $pluralModelLabel = 'Usuarios con acceso web';

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
            Section::make('Creación de usuario web con acceso a AsesorFy')
                ->description('Este usuario tendrá acceso limitado a la plataforma, necesario para clientes, trabajadores, etc.')
                ->icon('heroicon-o-user-plus')
                ->schema([

                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->suffixIcon('heroicon-m-user-circle')
                        ->maxLength(191),

                    Select::make('roles')
                        ->label('Rol del usuario')
                        ->relationship('roles', 'name')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->required(),

                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->suffixIcon('heroicon-m-at-symbol')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(191),

                    TextInput::make('password')
                        ->label('Password')
                        ->password()
                        ->revealable()
                        ->required(fn ($livewire) => $livewire instanceof CreateRecord)
                        ->visible(fn ($livewire) => $livewire instanceof CreateRecord)
                        ->dehydrateStateUsing(fn ($state) =>
                            filled($state) ? Hash::make($state) : null
                        ),

                    TextInput::make('password_confirmation')
                        ->label('Confirmar password')
                        ->password()
                        ->revealable()
                        ->dehydrated(false)
                        ->required(fn ($livewire) => $livewire instanceof CreateRecord)
                        ->visible(fn ($livewire) => $livewire instanceof CreateRecord)
                        ->same('password')
                        ->helperText('Rellena ambos campos solo si estás creando el usuario.'),

                    Toggle::make('acceso_app')
                        ->label('Acceso a la plataforma')
                        ->helperText('Activa este campo para permitir el acceso del usuario al sistema.')
                        ->default(true)
                        ->inline(false),

                ])
                ->columns(3)
                ->columnSpanFull(), // 👈 ESTO ES LO QUE LO HACE MÁS ANCHO


            // ===============================
            // CAMBIO DE CONTRASEÑA (SOLO EDIT)
            // ===============================
            Section::make('Actualizar contraseña de acceso a AsesorFy')
                ->description('Si quieres cambiar la contraseña del usuario, puedes hacerlo aquí.')
                ->icon('heroicon-o-key')
                ->schema([

                   TextInput::make('password')
    ->label('Nueva contraseña')
    ->password()
    ->revealable()
    ->nullable()
    ->helperText('Déjalo vacío si no quieres cambiar la contraseña')
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
    ->helperText('Solo obligatorio si introduces una nueva contraseña'),




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
                    TextColumn::make('acceso_app')
                    ->label('Acceso')
                    ->formatStateUsing(fn ($state) => $state ? '✅ Activo' : '❌ Inactivo')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'danger'),    
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
                EditAction::make(),
                ViewAction::make(),
                DeleteAction::make()
                ->before(function ($record, $action) {
                    if ($record->trabajador) {
                        Notification::make()
                            ->title('⛔ No se puede eliminar el usuario')
                            ->body('Este usuario está vinculado a un trabajador. Si deseas eliminarlo, debes hacerlo desde la sección de Trabajadores.')
                            ->danger()
                            ->persistent()
                            ->send();

                        $action->cancel(); // ❌ Cancela el borrado
                    }
                }),
                Action::make('toggle_acceso_app')
                ->label('Acceso')
                ->icon(fn ($record) => $record->acceso_app ? 'heroicon-o-lock-open' : 'heroicon-o-lock-closed')
                ->color(fn ($record) => $record->acceso_app ? 'success' : 'danger')
                ->schema([
                    Toggle::make('acceso_app')
                        ->label('¿Acceso permitido?')
                        ->helperText('Activa o desactiva el acceso del usuario a la plataforma.')
                        ->default(fn ($record) => $record->acceso_app),
                ])
                ->action(function ($record, array $data) {
                  //  dd($data); // Verifica si está llegando el valor de acceso_app
                    $record->update([
                        'acceso_app' => $data['acceso_app'],
                    ]);
                })
                ->modalHeading('Configurar acceso del usuario')
                ->modalSubmitActionLabel('Actualizar')
                ->modalCancelActionLabel('Cancelar')
                ->requiresConfirmation(),
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
