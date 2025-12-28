<?php

namespace App\Filament\Resources\ClienteResource\RelationManagers;

use Filament\Tables\Columns\TextColumn;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Lead;
use App\Filament\Resources\LeadResource;
use App\Enums\LeadEstadoEnum; // Asegúrate de tener este 'use'
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;


class LeadsRelationManager extends RelationManager
{
    protected static string $relationship = 'leads';
    protected static ?string $title = 'Historial de Leads';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nombre')
            ->recordUrl(null) 
            ->columns([
                // Procedencia del Lead
                TextColumn::make('procedencia.procedencia')
                    ->label('Procedencia')
                    ->badge()
                    ->color('gray'),

                // Estado del Lead
                TextColumn::make('estado')
                    ->badge()
                    ->formatStateUsing(fn (LeadEstadoEnum $state): string => $state->getLabel())
                    ->color(fn (LeadEstadoEnum $state): string => match ($state) {
                        LeadEstadoEnum::CONVERTIDO => 'success',
                        LeadEstadoEnum::INTENTO_CONTACTO, LeadEstadoEnum::ESPERANDO_INFORMACION => 'warning',
                        LeadEstadoEnum::DESCARTADO => 'danger',
                        LeadEstadoEnum::CONTACTADO, LeadEstadoEnum::PROPUESTA_ENVIADA => 'info',
                        default => 'gray',
                    }),

                // Comercial Asignado
            TextColumn::make('asignado_display') // Usamos un nombre virtual
    ->label('Comercial')
    ->badge()
    ->getStateUsing(function (Lead $record): string {
        // Usamos EXACTAMENTE la misma lógica que en tu infolist
        return $record->asignado?->full_name ?? 'Sin asignar';
    })
    ->color(fn (string $state): string => $state === 'Sin asignar' ? 'gray' : 'warning')
    ->searchable(query: function ($query, $search) {
        // Hacemos que la búsqueda funcione con el nombre del usuario asignado
        $query->whereHas('asignado', fn($q) => $q->where('name', 'like', "%{$search}%"));
    }),

                   TextColumn::make('demandado')
                    ->label('Demandado')
                    ->wrap() // <-- Hace que el texto largo salte a la siguiente línea
                    ->lineClamp(2) // Opcional: Limita el texto a 2 líneas y pone "ver más"
                    ->color('gray'),

                // Fecha de última actualización
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true), // Oculto por defecto para limpiar la vista

                // Fecha de creación (la he añadido como extra, es útil)
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([

            ])
            ->recordActions([
                // Botón para ver el Lead en una nueva pestaña
                ViewAction::make()
                ->label('Ver como esta el Leads')
                    ->url(fn (Lead $record): string => LeadResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('created_at', 'desc'); // Ordena por defecto por los más recientes
    }
}