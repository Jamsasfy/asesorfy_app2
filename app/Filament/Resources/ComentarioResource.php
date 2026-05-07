<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use App\Models\Lead;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\ComentarioResource\Pages\ListComentarios;
use App\Filament\Resources\ComentarioResource\Pages\CreateComentario;
use App\Filament\Resources\ComentarioResource\Pages\EditComentario;
use App\Filament\Resources\ComentarioResource\Pages;
use App\Filament\Resources\ComentarioResource\RelationManagers;
use App\Models\Cliente;
use App\Models\Comentario;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Str;

class ComentarioResource extends Resource
{
    protected static ?string $model = Comentario::class;

    protected static string | \BackedEnum | null $navigationIcon = 'icon-comentario-clientes';

    protected static string | \UnitEnum | null $navigationGroup = 'Admin Comentarios';
    protected static ?string $navigationLabel = 'Comentarios Clientes';
    protected static ?string $modelLabel = 'Comentario Cliente';
    protected static ?string $pluralModelLabel = 'Comentarios Clientes';




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
                Select::make('comentable_type')
                ->label('Tipo de entidad')
                ->options(Comentario::getComentableModels())
                ->required()
                ->reactive()
                ->afterStateUpdated(fn ($state, callable $set) => $set('comentable_id', null))
                ->columnSpanFull(),

            Select::make('comentable_id')
                ->label('Entidad relacionada')
                ->options(function (callable $get) {
                    $model = $get('comentable_type');

                    if (! $model || !class_exists($model)) return [];

                    if ($model === Cliente::class) {
                        return $model::all()->pluck('razon_social', 'id');
                    }

                    if ($model === Lead::class) {
                        return $model::all()->pluck('nombre', 'id');
                    }

                    return $model::all()->pluck('id', 'id'); // fallback
                })
                ->searchable()
                ->required()
                ->columnSpanFull(),

            Textarea::make('contenido')
                ->label('Comentario')
                ->required()
                ->rows(5),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                ->label('Autor')
                ->badge()
                ->color('info')
                ->sortable()
                ->searchable(),

            TextColumn::make('comentable_type')
                ->label('Tipo')
                ->formatStateUsing(fn (string $state) => class_basename($state))
                ->badge()
                ->color('gray')
                ->sortable(),

                TextColumn::make('comentable_nombre')
                ->label('Entidad')
                ->badge()
                ->color('warning')
                ->getStateUsing(function ($record) {
                    if (! $record->comentable) return '—';

                    return $record->comentable->razon_social
                        ?? $record->comentable->nombre
                        ?? $record->comentable->name
                        ?? ($record->comentable->nombre ?? 'ID ' . $record->comentable->id);
                })

                ->sortable()
                ->searchable(),

            TextColumn::make('contenido')
                ->label('Comentario')
                ->wrap()
                ->limit(80)
                ->tooltip(fn ($state) => $state),

            TextColumn::make('created_at')
                ->label('Fecha')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
        ])
        ->defaultSort('created_at', 'desc')
        ->filters([    
                Filter::make('entidad')
                    ->label('Entidad relacionada')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('tipo')
                                ->label('Tipo de comentario')
                                ->options(Comentario::getComentableModels()) // ['App\Models\Cliente' => 'Cliente', ...]
                                ->reactive()
                                ->afterStateUpdated(fn ($state, callable $set) => $set('entidad_id', null)),

                            Select::make('entidad_id')
                                ->label('Cliente ')
                                ->visible(fn (Get $get) => filled($get('tipo')))
                                ->options(function (callable $get) {
                                    $model = $get('tipo');
                                    if (! $model || ! class_exists($model)) return [];

                                    $campo = 'name';
                                    if (Str::contains($model, 'Cliente')) $campo = 'razon_social';
                                    if (Str::contains($model, 'Lead')) $campo = 'nombre';
                                    if (Str::contains($model, 'Proyecto')) $campo = 'nombre';

                                    return $model::query()->pluck($campo, 'id');
                                })
                                ->searchable(),
                        ]),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['tipo'] ?? null, fn ($q) => $q->where('comentable_type', $data['tipo']))
                            ->when($data['entidad_id'] ?? null, fn ($q) => $q->where('comentable_id', $data['entidad_id']));
                    }),
                    
                    // Filtro por autor del comentario
                    SelectFilter::make('user_id')
                    ->label('Comentario creado por')
                    ->options(fn () => User::pluck('name', 'id')->toArray())
                    ->searchable(),                
    
                    // Filtro por rango de fechas
                    DateRangeFilter::make('created_at')
                        ->label('Fecha del comentario'),

              
        ], layout: FiltersLayout::AboveContent)
        ->filtersFormColumns(3)
          
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
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
            'index' => ListComentarios::route('/'),
            'create' => CreateComentario::route('/create'),
            'edit' => EditComentario::route('/{record}/edit'),
        ];
    }
}
