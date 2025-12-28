<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\TrabajadorResource\Pages\ListTrabajadors;
use App\Filament\Resources\TrabajadorResource\Pages\CreateTrabajador;
use App\Filament\Resources\TrabajadorResource\Pages\EditTrabajador;
use App\Filament\Resources\TrabajadorResource\Pages;
use App\Filament\Resources\TrabajadorResource\RelationManagers;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\Trabajador;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Support\Enums\IconSize;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Filament\Tables\Enums\RecordActionsPosition;


class TrabajadorResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Trabajador::class;

    protected static string | \BackedEnum | null $navigationIcon = 'icon-f-city-worker';
    protected static string | \UnitEnum | null $navigationGroup = 'Usuarios plataforma';
    protected static ?string $navigationLabel = 'Trabajadores AsesorFy';
    protected static ?string $modelLabel = 'Trabajador AsesorFy';
    protected static ?string $pluralModelLabel = 'Trabajadores AsesorFy';

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
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }
    

public static function form(Schema $schema): Schema
{
    return $schema
        ->columns(1) // 🔒 Fuerza las sections en vertical
        ->components([

            // =====================================================
            // SECTION 1 · ACCESO A LA PLATAFORMA
            // =====================================================
            Section::make('Trabajador con acceso a AsesorFy')
                ->description('Estos son los datos de acceso a la plataforma de AsesorFy. Una vez creado el trabajador, debe acceder a usuario web y darle los permisos que correspondan.')
                ->schema([
                    Group::make()
                        ->relationship('user')
                        ->schema([
                            TextInput::make('name')
                                ->label('Nombre')
                                ->required(),

                            TextInput::make('email')
                                ->label('Email address')
                                ->email()
                                ->required(),

                            TextInput::make('password')
                                ->label('Password')
                                ->password()
                                ->revealable()
                                ->required(fn ($livewire) => $livewire instanceof CreateRecord)
                                ->visible(fn ($livewire) => $livewire instanceof CreateRecord)
                                ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                ->same('password_confirmation')
                                ->maxLength(191),

                            TextInput::make('password_confirmation')
                                ->label('Confirmar password')
                                ->password()
                                ->revealable()
                                ->dehydrated(false)
                                ->required(fn ($livewire) => $livewire instanceof CreateRecord)
                                ->visible(fn ($livewire) => $livewire instanceof CreateRecord)
                                ->helperText('Repite la contraseña de acceso.'),
                        ])
                        ->columns(4),
                ])
                ->columnSpanFull(),

            // =====================================================
            // SECTION 2 · DATOS DEL TRABAJADOR
            // =====================================================
            Section::make('Datos del trabajador')
                ->description('Demás datos relativos al trabajador a nivel laboral y de accesos a la plataforma')
                ->schema([

                    Select::make('oficina_id')
                        ->relationship('oficina', 'nombre')
                        ->preload()
                        ->searchable()
                        ->required(),

                    TextInput::make('apellidos')
                        ->maxLength(191),

                    TextInput::make('telefono')
                        ->tel()
                        ->required()
                        ->rule('regex:/^[0-9]{9}$/')
                        ->helperText('Debe tener 9 dígitos'),

                    TextInput::make('dni_o_cif')
                        ->label('DNI o CIF')
                        ->required()
                        ->maxLength(191),

                    TextInput::make('cargo')
                        ->maxLength(191),

                    Textarea::make('direccion')
                        ->columnSpanFull(),

                    Textarea::make('observaciones')
                        ->columnSpanFull(),

                    TextInput::make('email_personal')
                        ->email()
                        ->required()
                        ->maxLength(191),

                    TextInput::make('numero_seg_social')
                        ->label('Número Seguridad Social')
                        ->rule('digits:12')
                        ->maxLength(191),

                    TextInput::make('numero_cuenta_nomina')
                        ->label('Número cuenta nómina')
                        ->maxLength(191),

                    Select::make('departamento_id')
                        ->label('Departamento')
                        ->relationship('departamento', 'nombre')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->placeholder('Selecciona un departamento'),

                ])
                ->columns(4)
                ->columnSpanFull(),

        ]);
}



    public static function table(Table $table): Table
    {
        return $table      
                ->recordActionsPosition(RecordActionsPosition::BeforeColumns)
 
            ->columns([
                TextColumn::make('user.name')
                    ->label('Nombre')                    
                    ->sortable(),
                TextColumn::make('apellidos')
                    ->searchable(),
                TextColumn::make('rol_estado')
                    ->label('Rol')
                    ->badge()
                    ->getStateUsing(fn ($record) =>
                        $record->user && $record->user->roles->isNotEmpty()
                            ? implode(', ', $record->user->roles->pluck('name')->toArray())
                            : '⚠️ Sin rol, asignar uno'
                    )
                    ->color(fn ($state) => str_contains($state, 'Sin rol') ? 'warning' : 'primary'),
               TextColumn::make('departamento.nombre')
                        ->label('Departamento')
                        ->badge()
                        ->color('info')
                        ->placeholder('Sin departamento')
                        ->searchable()
                        ->sortable(),        
                TextColumn::make('oficina.nombre')                  
                    ->sortable(),               
                TextColumn::make('telefono')
                    ->searchable(),
                TextColumn::make('dni_o_cif')
                    ->searchable(),
                TextColumn::make('cargo')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('Email trabajo')
                    ->searchable(),
             /*    TextColumn::make('numero_seg_social')
                    ->searchable(),
                TextColumn::make('numero_cuenta_nomina')
                    ->searchable(), */
                IconColumn::make('user.acceso_app')
                    ->label('Acceso app')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->sortable(),    
                TextColumn::make('created_at')
                ->label('Fecha de alta')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                ->label('Actualizado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user.name')
                ->label('Nombre')
                ->relationship('user', 'name')
                ->searchable(),
                Filter::make('apellidos')
                    ->schema([
                        TextInput::make('valor')
                            ->label('Apellido')
                            ->placeholder('Buscar apellido'),
                    ])
                    ->query(function ($query, array $data) {
                        if (! $data['valor']) return $query;

                        return $query->where('apellidos', 'like', "%{$data['valor']}%");
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return $data['valor']
                            ? 'Apellido: ' . $data['valor']
                            : null;
                    }),
               SelectFilter::make('oficina.nombre')
                ->label('Oficina')
                ->relationship('oficina', 'nombre')
                ->preload()
                ->searchable(),
              SelectFilter::make('departamento') // Filtramos por la relación
                ->relationship('departamento', 'nombre')
                ->label('Filtrar por Departamento'),
           
                Filter::make('rol')
                //->label('Rol del usuario')
                ->schema([
                    Select::make('rol_id')
                        ->label('Rol del trabajador')
                        ->options(Role::query()->pluck('name', 'id'))
                        ->searchable()
                        ->preload(),
                ])
                ->query(function ($query, array $data) {
                    if (! $data['rol_id']) return $query;
            
                    return $query->whereHas('user.roles', function ($q) use ($data) {
                        $q->where('id', $data['rol_id']);
                    });
                }),
                Filter::make('acceso_app')
                    ->label('Acceso')
                    ->schema([
                        Select::make('estado')
                            ->label('Estado de acceso a la app')
                            ->options([
                                '1' => 'Con acceso',
                                '0' => 'Sin acceso',
                            ])
                            ->placeholder('Todos'),
                    ])
                    ->query(function ($query, array $data) {
                        if (!isset($data['estado'])) return $query;

                        return $query->whereHas('user', function ($q) use ($data) {
                            $q->where('acceso_app', $data['estado']);
                        });
                    }),
               
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(6)
            ->recordActions([
                EditAction::make()
                ->label(''),
                Action::make('ver_usuario')
                ->label('Accesos')
                ->icon('heroicon-o-users') // Puedes cambiar el ícono aquí
                ->iconSize(IconSize::Small)
                ->color('warning')
                ->tooltip('Permisos del usuario y contraseña de acceso')
                ->url(fn (Trabajador $record): string => UserResource::getUrl('edit', ['record' => $record->user_id]))
                ->openUrlInNewTab(), // Opcional, si quieres abrir en nueva pestaña
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
            'index' => ListTrabajadors::route('/'),
            'create' => CreateTrabajador::route('/create'),
            'edit' => EditTrabajador::route('/{record}/edit'),
        ];
    }
}
