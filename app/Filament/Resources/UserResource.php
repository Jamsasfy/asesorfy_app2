<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
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

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Usuarios plataforma';
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
            public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
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
    

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Section::make('Creación de usuario web con acceso a AsesorFy')
                ->description('Este usuario tendrá acceso limitado a la plataforma, necesario para clientes, trabajadores, etc.')
                ->icon('heroicon-o-user-plus')
                ->schema([
                    // Campos normales
                    TextInput::make('name')
                        ->required()
                        ->label('Nombre')
                        ->suffixIcon('heroicon-m-user-circle')
                        ->columnSpan(1)
                        ->maxLength(191),
            
                    Select::make('roles')
                        ->label('Rol del usuario')
                        ->relationship('roles', 'name')
                        ->multiple()
                        ->preload()
                        ->required()
                        ->searchable(),
            
                    TextInput::make('email')
                        ->email()
                        ->suffixIcon('heroicon-m-at-symbol')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->columnSpan(2)
                        ->maxLength(191),
            
                    TextInput::make('password')
                        ->label('Password')
                        ->password()
                        ->revealable()
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                        ->required(fn ($livewire) => $livewire instanceof CreateRecord)
                        ->visible(fn ($livewire) => $livewire instanceof CreateRecord)
                        ->maxLength(191),
            
                    TextInput::make('password_confirmation')
                        ->label('Confirmar password')
                        ->password()
                        ->revealable()
                        ->dehydrated(false)
                        ->required(fn ($livewire) => $livewire instanceof CreateRecord)
                        ->visible(fn ($livewire) => $livewire instanceof CreateRecord)
                        ->helperText('Rellena ambos campos solo si estás creando el usuario.'),
            
                    Toggle::make('acceso_app')
                        ->label('Acceso a la plataforma')
                        ->helperText('Activa este campo para permitir el acceso del usuario al sistema.')
                        ->default(true)
                        ->inline(false),
                ])
                ->columns(4),
            
                    
                Section::make('Actualizar contraseña de acceso a AsesorFy')
                ->icon('heroicon-o-key')
                ->description('Si quieres cambiar la contraseña del usuario, puedes hacerlo aquí.')
                ->schema([
                    TextInput::make('new_password')
                        ->statePath('password')
                        ->label('Nueva contraseña')
                        ->password()
                        ->revealable()
                        ->nullable()
                        ->dehydrated(fn ($state) => filled($state))
                        ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                        ->visible(fn ($livewire) => $livewire instanceof EditRecord)
                        ->maxLength(191),
            
                    TextInput::make('new_password_confirmation')
                        ->label('Repite contraseña')
                        ->password()
                        ->revealable()
                        ->nullable()
                        ->same('password')
                        ->requiredWith('password'),
                ])
                ->visible(fn ($livewire) => $livewire instanceof EditRecord)
                ->columns(2),
        
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
                Tables\Filters\Filter::make('email')
                ->form([
                    Forms\Components\TextInput::make('value')
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
            Tables\Filters\Filter::make('name')
                ->form([
                    Forms\Components\TextInput::make('value')
                        ->label('Nombre')
                        ->placeholder('Buscar nombre...'),
                ])
                ->query(fn ($query, $data) =>
                    $data['value'] ? $query->where('name', 'like', "%{$data['value']}%") : $query
                )
                ->indicateUsing(fn ($data) =>
                    $data['value'] ? 'Nombre: ' . $data['value'] : null
                ),
        
                Tables\Filters\SelectFilter::make('roles')
                ->label('Rol')
                ->relationship('roles', 'name')
                ->multiple() // Puedes ponerlo si quieres seleccionar más de uno
                ->searchable()
                ->preload(),

                Tables\Filters\SelectFilter::make('acceso_app')
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
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
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
                Tables\Actions\Action::make('toggle_acceso_app')
                ->label('Acceso')
                ->icon(fn ($record) => $record->acceso_app ? 'heroicon-o-lock-open' : 'heroicon-o-lock-closed')
                ->color(fn ($record) => $record->acceso_app ? 'success' : 'danger')
                ->form([
                    Forms\Components\Toggle::make('acceso_app')
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
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
