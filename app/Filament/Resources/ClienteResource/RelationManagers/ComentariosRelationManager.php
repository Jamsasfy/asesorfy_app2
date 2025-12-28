<?php

namespace App\Filament\Resources\ClienteResource\RelationManagers;

use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ComentariosRelationManager extends RelationManager
{
    protected static string $relationship = 'Comentarios';


 // QUITA el “static” y asegúrate de no ponerle parámetros
 public function isReadOnly(): bool
 {
     return false;
 }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('contenido')
                    ->label('Comentario')
                    ->required()
                   // ->rows(4)
                   // ->maxLength(1000)
                       ->columnSpanFull(), // <-- Esto es clave

                    
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('contenido')
            ->columns([
                ViewColumn::make('contenido')
                ->label('')
                ->view('filament.components.comentario-card')
                ->grow(false),
               
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                
            ])
           
            ->headerActions([
                CreateAction::make()
                ->mutateDataUsing(function (array $data): array {
                    $data['user_id'] = auth()->id();
                    return $data;
                }),
            ])
            ->recordActions([                
                DeleteAction::make(),
                EditAction::make()
                 ->label('Editar')
                ->modalHeading(fn ($record) => 'Editar comentario de ' . ($record->user->name ?? 'usuario'))
                ->modalSubmitActionLabel('Guardar cambios')
                ->modalCancelActionLabel('Cancelar'),

            ])
            ->toolbarActions([
                /* Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]), */
            ]);
    }
}
