<?php

namespace App\Filament\Resources\ProyectoResource\Pages;

use Filament\Actions\EditAction;
use App\Filament\Resources\ProyectoResource\RelationManagers\DocumentosRelationManager;
use App\Filament\Resources\ProyectoResource;
use App\Models\Proyecto;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;

class ViewProyecto extends ViewRecord
{
    protected static string $resource = ProyectoResource::class;

     // Opcional: Si quieres añadir acciones específicas a la cabecera de la página de vista
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(), // Permite editar el proyecto desde la vista
            // ▼▼▼ AQUÍ VA EL NUEVO BOTÓN ▼▼▼
            Action::make('asignarAsesor')
                ->label('Asignar Asesor')
                ->icon('heroicon-o-user-plus')
                
                ->color('info') // Un color azul discreto
                ->modalHeading('Asignar responsable al proyecto')
                ->modalSubmitActionLabel('Asignar')
                // El botón solo es visible si el proyecto NO tiene un asesor (user_id) y tienepermisos para asignar asesores
                ->visible(fn (Proyecto $record): bool =>
                    is_null($record->user_id)
                    && auth()->user()?->can('assignAssessor', $record)
                )
                ->schema([
                    Select::make('user_id') // El campo a actualizar en el modelo Proyecto
                        ->label('Selecciona Asesor')
                        ->options(
                            // Lógica para obtener solo usuarios con el rol 'asesor'
                            User::whereHas('roles', fn ($q) => $q->where('name', 'asesor'))
                                ->pluck('name', 'id')
                        )
                        ->searchable()
                        ->required(),
                ])
                ->action(function (Proyecto $record, array $data): void {
                    // Asignamos el asesor al proyecto
                    $record->update(['user_id' => $data['user_id']]);
                }),

                 // --- 2. Botón para CAMBIAR (cuando ya hay alguien) ---
    Action::make('cambiarAsesor')
        ->label('Cambiar Asesor')
        ->icon('heroicon-o-arrow-path')
        ->color('warning')
        ->modalHeading('Cambiar responsable del proyecto')
      ->visible(fn (Proyecto $record): bool =>
    ! is_null($record->user_id)
    && auth()->user()?->can('unassignAssessor', $record)
)

        ->schema([
            Select::make('user_id')->label('Selecciona Nuevo Asesor')->options(User::whereHas('roles', fn ($q) => $q->where('name', 'asesor'))->pluck('name', 'id'))->searchable()->required(),
        ])
        ->action(fn (Proyecto $record, array $data) => $record->update(['user_id' => $data['user_id']])),

    // --- 3. Botón para QUITAR (cuando ya hay alguien) ---
    Action::make('quitarAsesor')
        ->label('Quitar Asesor')
        ->icon('heroicon-o-user-minus')
        ->color('danger')
        ->requiresConfirmation()
        ->modalHeading('Quitar asesor del proyecto')
        ->modalDescription('¿Estás seguro? El proyecto se quedará sin responsable asignado.')
      ->visible(fn (Proyecto $record): bool =>
    ! is_null($record->user_id)
    && auth()->user()?->can('unassignAssessor', $record)
)

        ->action(fn (Proyecto $record) => $record->update(['user_id' => null])),

        ];
    }

   // app/Filament/Resources/ProyectoResource/Pages/ViewProyecto.php
public function relations(): array
{
    return [
        DocumentosRelationManager::class,
        // ...otros relationmanagers
    ];
}


    
}
