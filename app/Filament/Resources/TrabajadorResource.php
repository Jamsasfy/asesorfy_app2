<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrabajadorResource\Pages;
use App\Filament\Resources\TrabajadorResource\RelationManagers;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\Trabajador;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Tables\Actions\Action;
use Filament\Forms;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
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

class TrabajadorResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Trabajador::class;

    protected static ?string $navigationIcon = 'icon-f-city-worker';
    protected static ?string $navigationGroup = 'Usuarios plataforma';
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
    

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Section::make('Trabajador con acceso a Asesorfy')
                ->description('Estos son los datos de acceso a la plataforma de AsesorFy. Una vez creado el trabajador, debe acceder a usuario web y darle los permisos que corresponden.')
                ->schema([
                    Group::make()
                        ->relationship('user')
                        ->schema([
                            TextInput::make('name')
                                ->label('Nombre')
                                ->columnspan(1) 
                                ->required(),
                            TextInput::make('email')
                                ->label('Email address')
                                ->email()
                                ->columnspan(1) 
                                ->required(),
                      
                 
                            TextInput::make('password')
                                ->label('Password')
                                ->password()
                                ->required(fn ($livewire) => $livewire instanceof CreateRecord)
                                ->revealable()
                                ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                                ->visible(fn ($livewire) => $livewire instanceof CreateRecord)
                                ->same('password_confirmation')
                                ->maxLength(191),
                            TextInput::make('password_confirmation')
                                ->helperText('Repite la contraseña de acceso.')
                                ->password()
                                ->revealable()
                                ->dehydrated(false)
                                ->required(fn ($livewire) => $livewire instanceof CreateRecord)
                                ->visible(fn ($livewire) => $livewire instanceof CreateRecord)
                                ->label('Confirmar password'),    
                        ])->columns(4), // Tu layout original de 4 columnas
                ]), // Fin Section 1

                    Section::make('Datos del trabajador')
                    ->description('Demás datos relativos al trabajador a nivel laboral y de accesos a la plataforma')
                   
                    ->schema([
                        //Forms\Components\Select::make('user_id')
                        //->hidden(),
                    Forms\Components\Select::make('oficina_id')
                        ->relationship('oficina', 'nombre')
                        ->preload()
                        ->searchable()
                        ->required(),
                    Forms\Components\TextInput::make('apellidos')
                        ->maxLength(191),
                    Forms\Components\TextInput::make('telefono')
                        ->tel()
                        ->required()
                        ->rule('regex:/^[0-9]{9}$/')
                        ->helperText('Debe tener 9 dígitos')
                        ->maxLength(191),
                    Forms\Components\TextInput::make('dni_o_cif')
                        ->required()
                        ->maxLength(191),
                    Forms\Components\TextInput::make('cargo')
                        ->maxLength(191),
                    Forms\Components\Textarea::make('direccion')
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('observaciones')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('email_personal')
                        ->email()
                        ->required()
                        ->maxLength(191),
                    Forms\Components\TextInput::make('numero_seg_social')
                        ->rule('digits:12')
                        ->maxLength(191),
                    Forms\Components\TextInput::make('numero_cuenta_nomina')
                        ->maxLength(191),
                   Select::make('departamento_id')
                        ->label('Departamento')
                        ->relationship('departamento', 'nombre')
                        ->searchable()
                        ->preload()
                        ->required() // Opcional: hazlo ->nullable() si un trabajador puede no tener depto.
                        ->placeholder('Selecciona un departamento'),
             


                        ])->columns(4),   
              
            ]);
    }
   


    public static function table(Table $table): Table
    {
        return $table       
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
                \Filament\Tables\Columns\IconColumn::make('user.acceso_app')
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
                    ->form([
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
                ->form([
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
                    ->form([
                        \Filament\Forms\Components\Select::make('estado')
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
            ->actions([
                Tables\Actions\EditAction::make(),
                Action::make('ver_usuario')
                ->label('Permisos y acceso app')
                ->icon('heroicon-o-users') // Puedes cambiar el ícono aquí
                ->iconSize(IconSize::Small)
                ->color('warning')
                ->tooltip('Permisos del usuario y contraseña de acceso')
                ->url(fn (Trabajador $record): string => UserResource::getUrl('edit', ['record' => $record->user_id]))
                ->openUrlInNewTab(), // Opcional, si quieres abrir en nueva pestaña
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
            'index' => Pages\ListTrabajadors::route('/'),
            'create' => Pages\CreateTrabajador::route('/create'),
            'edit' => Pages\EditTrabajador::route('/{record}/edit'),
        ];
    }
}
