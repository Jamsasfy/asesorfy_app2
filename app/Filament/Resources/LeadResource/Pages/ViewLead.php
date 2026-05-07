<?php

namespace App\Filament\Resources\LeadResource\Pages;

use Filament\Actions\EditAction;
use App\Enums\LeadEstadoEnum;
use App\Filament\Resources\LeadResource;
use App\Models\Lead;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use App\Models\User;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

class ViewLead extends ViewRecord
{
    protected static string $resource = LeadResource::class;

    /**
     * El texto que aparece en la cabecera de la página.
     */
   public function getHeading(): string
{
    $lead = $this->getRecord();

    $end = $lead->fecha_cierre
        ? Carbon::parse($lead->fecha_cierre)
        : now();

    $diff = Carbon::parse($lead->created_at)
        ->diffForHumans($end, [
            'parts'  => 2,
            'short'  => true,
            'syntax' => Carbon::DIFF_ABSOLUTE,
        ]);

    $estado = $lead->estado?->getLabel() ?? '—';

    return "Lead #{$lead->id} · {$estado} · {$diff}";
}




    protected function getHeaderActions(): array
    {

         // Preparamos el array [value => label] de la enum
    $estadoOptions = [];
    foreach (LeadEstadoEnum::cases() as $case) {
        $estadoOptions[$case->value] = $case->getLabel();
    }
    
        return [
            EditAction::make()
                ->visible(fn () => auth()->user()?->can('Update:Lead') ?? false),


Action::make('verConversion')
    ->label('Ver conversión')
    ->icon('heroicon-o-link')
    ->visible(fn (Lead $record) => in_array($record->estado, [
        LeadEstadoEnum::CONVERTIDO_ESPERA_FIRMA,
        LeadEstadoEnum::CONVERTIDO_ESPERA_DATOS,
    ], true) && (
        auth()->user()?->hasRole('super_admin') ||
        $record->asignado_id === auth()->id()
    ))
    ->url(fn (Lead $record) => LeadResource::getUrl('conversion', ['record' => $record->id]))
    ->openUrlInNewTab()
    ->color('info')
    ->extraAttributes([
        // IA blue gradient + glow (funciona bien en header actions)
        'class' => implode(' ', [
            'relative',
            'overflow-hidden',
            'font-extrabold',
            'text-white',
            'border',
            'border-sky-200/40',
            'dark:border-sky-400/20',
            'bg-gradient-to-r',
            'from-sky-500',
            'via-cyan-500',
            'to-blue-600',
            'shadow-[0_14px_35px_-22px_rgba(56,189,248,.95)]',
            'hover:shadow-[0_18px_45px_-26px_rgba(56,189,248,1)]',
            'hover:brightness-[1.06]',
            'active:brightness-[.98]',
            'transition',
        ]),
    ]),


             // 1) Asignar comercial si NO tiene asignado
             Action::make('asignar_comercial')
             ->label('Asignar Comercial')
             ->icon('heroicon-o-user-plus')
             ->color('success')
             ->visible(fn ($record) => is_null($record->asignado_id) && (auth()->user()?->can('Update:Lead') ?? false))
             ->schema([
                 Select::make('asignado_id')
                     ->label('Elige Comercial')
                     ->options(
                         User::whereHas('roles', fn($q) => $q->where('name','comercial'))
                             ->pluck('name','id')
                     )
                     ->searchable()
                     ->required(),
             ])
             ->action(function ($record, array $data) {
                 $record->update(['asignado_id' => $data['asignado_id']]);
                 Notification::make()
                     ->title('✅ Comercial asignado')
                     ->body("Lead asignado a {$record->asignado->name}.")
                     ->success()
                     ->send();
             })
             ->modalHeading('Asignar Comercial al Lead')
             ->modalSubmitActionLabel('Asignar'),

         // 2) Cambiar comercial si ya tiene uno
         Action::make('cambiar_comercial')
             ->label('Cambiar Comercial')
             ->icon('heroicon-o-user-minus')
             ->color('primary')
             ->visible(fn ($record) => ! is_null($record->asignado_id) && (auth()->user()?->can('CambiarComercial:Lead') ?? false))
             ->schema([
                 Select::make('asignado_id')
                     ->label('Nuevo Comercial')
                     ->options(
                         User::whereHas('roles', fn($q) => $q->where('name','comercial'))
                             ->pluck('name','id')
                     )
                     ->searchable()
                     ->required(),
             ])
             ->action(function ($record, array $data) {
                 $record->update(['asignado_id' => $data['asignado_id']]);
                 Notification::make()
                     ->title('🔄 Comercial cambiado')
                     ->body("Ahora asignado a {$record->asignado->name}.")
                     ->success()
                     ->send();
             })
             ->modalHeading('Cambiar Comercial del Lead')
             ->modalSubmitActionLabel('Cambiar'),

         // 3) Quitar comercial si tiene uno
         Action::make('quitar_comercial')
             ->label('Quitar Comercial')
             ->icon('heroicon-o-user-minus')
             ->color('danger')
             ->visible(fn ($record) => ! is_null($record->asignado_id) && (auth()->user()?->can('QuitarComercial:Lead') ?? false))
             ->requiresConfirmation()
             ->modalHeading('¿Quitar comercial?')
             ->modalDescription('Esto dejará el lead sin comercial asignado.')
             ->modalSubmitActionLabel('Sí, quitar')
             ->action(function ($record) {
                 $record->update(['asignado_id' => null]);
                 Notification::make()
                     ->title('🗑️ Comercial removido')
                     ->body('El lead ya no tiene comercial asignado.')
                     ->warning()
                     ->send();
             }),

            
        Action::make('forzar_cambio_estado')
        ->label('Forzar Estado')
        ->icon('heroicon-o-shield-check')
        ->color('danger')
        ->visible(fn () => auth()->user()?->can('Update:Lead') ?? false)
        ->schema([
            Select::make('estado')
                ->label('Estado deseado')
                ->options($estadoOptions)
                ->required(),
        ])
        ->action(function (array $data, Lead $record) {
            // Convierte el string al enum
            $nuevoEstado = LeadEstadoEnum::tryFrom($data['estado']);
            if (! $nuevoEstado) {
                Notification::make()
                    ->title('❌ Estado inválido')
                    ->danger()
                    ->send();
                return;
            }
        
            // Actualiza el estado
            $record->update(['estado' => $nuevoEstado->value]);
        
            // Opcional: crea un comentario
            $record->comentarios()->create([
                'user_id'   => auth()->id(),
                'contenido' => 'Cambio de estado a: ' . $nuevoEstado->getLabel(),
            ]);
        
            // Usa la etiqueta de la enum en la notificación
            Notification::make()
                ->title('🛡️ Estado forzado')
                ->body("El estado se cambió a “{$nuevoEstado->getLabel()}”.")
                ->success()
                ->send();
        })
        ->modalHeading(fn (Lead $record) => "Forzar estado de “{$record->nombre}”")
        ->modalSubmitActionLabel('Aplicar'),

        ];
    }
}
