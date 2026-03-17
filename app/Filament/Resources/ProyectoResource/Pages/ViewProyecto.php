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
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;
use Filament\Notifications\Notification;
use App\Filament\Resources\ProyectoResource as ProyectoRes;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Enums\VentaEstadoEnum;
use App\Enums\ClienteSuscripcionEstadoEnum;

class ViewProyecto extends ViewRecord
{
    protected static string $resource = ProyectoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            // --- 1. ASIGNAR (sin asesor) ---
            Action::make('asignarAsesor')
                ->label('Asignar Asesor')
                ->icon('heroicon-o-user-plus')
                ->color('info')
                ->modalHeading('Asignar responsable al proyecto')
                ->modalSubmitActionLabel('Asignar')
                ->visible(fn (Proyecto $record): bool =>
                    is_null($record->user_id)
                    && auth()->user()?->can('assignAssessor', $record)
                )
                ->schema([
                    Placeholder::make('info_asesor_cliente')
                        ->label('')
                        ->content(function () {
                            $record = $this->getRecord();
                            $asesorNombre = $record->cliente->asesor->name ?? null;
                            if ($asesorNombre) {
                                return new HtmlString("
                                    <div style='background-color:#78350f;color:#fef9c3;padding:0.75rem;border-radius:0.375rem;font-size:0.9rem;text-align:center;margin-bottom:1rem;'>
                                        ⚠️ Este cliente ya tiene asesor asignado: <strong>{$asesorNombre}</strong><br>
                                        <span style='font-size:0.8rem;'>Si quieres asignarle el mismo al proyecto, selecciónalo en el desplegable.</span>
                                    </div>
                                ");
                            }
                            return new HtmlString("
                                <div style='background-color:#f59e0b;color:white;padding:0.75rem;border-radius:0.375rem;font-size:0.9rem;text-align:center;margin-bottom:1rem;'>
                                    ⚠️ Este cliente no tiene asesor asignado todavía.
                                </div>
                            ");
                        }),
                    Select::make('user_id')
                        ->label('Selecciona Asesor')
                        ->options(
                            User::whereHas('roles', fn ($q) => $q->whereIn('name', ['asesor', 'super_admin']))
                                ->pluck('name', 'id')
                        )
                        ->default(fn () => $this->getRecord()->user_id)
                        ->searchable()
                        ->required(),
                ])
                ->action(function (Proyecto $record, array $data): void {
                    $record->update(['user_id' => $data['user_id']]);
                }),

            // --- 2. CAMBIAR (ya tiene asesor) ---
            Action::make('cambiarAsesor')
                ->label('Cambiar Asesor')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->modalHeading('Cambiar responsable del proyecto')
                ->modalSubmitActionLabel('Cambiar')
                ->visible(fn (Proyecto $record): bool =>
                    !is_null($record->user_id)
                    && auth()->user()?->can('assignAssessor', $record)
                )
                ->schema([
                    Placeholder::make('info_asesor_cliente')
                        ->label('')
                        ->content(function () {
                            $record = $this->getRecord();
                            $asesorNombre = $record->cliente->asesor->name ?? null;
                            if ($asesorNombre) {
                                return new HtmlString("
                                    <div style='background-color:#78350f;color:#fef9c3;padding:0.75rem;border-radius:0.375rem;font-size:0.9rem;text-align:center;margin-bottom:1rem;'>
                                        ⚠️ Este cliente ya tiene asesor asignado: <strong>{$asesorNombre}</strong><br>
                                        <span style='font-size:0.8rem;'>Si quieres asignarle el mismo al proyecto, selecciónalo en el desplegable.</span>
                                    </div>
                                ");
                            }
                            return new HtmlString('');
                        }),
                    Select::make('user_id')
                        ->label('Selecciona Nuevo Asesor')
                        ->options(
                            User::whereHas('roles', fn ($q) => $q->whereIn('name', ['asesor', 'super_admin']))
                                ->pluck('name', 'id')
                        )
                        ->default(fn () => $this->getRecord()->user_id)
                        ->searchable()
                        ->required(),
                ])
                ->action(function (Proyecto $record, array $data): void {
                    $record->update(['user_id' => $data['user_id']]);
                }),

            // --- 3. QUITAR ---
            Action::make('quitarAsesor')
                ->label('Quitar Asesor')
                ->icon('heroicon-o-user-minus')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Quitar asesor del proyecto')
                ->modalDescription('¿Estás seguro? El proyecto se quedará sin responsable asignado.')
                ->visible(fn (Proyecto $record): bool =>
                    !is_null($record->user_id)
                    && auth()->user()?->can('unassignAssessor', $record)
                )
                ->action(fn (Proyecto $record) => $record->update(['user_id' => null])),

            // --- CANCELAR SUSCRIPCIÓN RECURRENTE ---
            Action::make('cancelar_suscripcion')
                ->label('Cancelar suscripción recurrente')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('¿Cancelar suscripción recurrente?')
                ->modalDescription('La suscripción recurrente pendiente de activación se cancelará. El cliente recibirá un email informativo. Esta acción no se puede deshacer.')
                ->modalSubmitActionLabel('Sí, cancelar suscripción')
                ->visible(function (Proyecto $record): bool {
                    // Solo visible si el proyecto tiene una venta con suscripción pendiente de activación
                    return \App\Models\ClienteSuscripcion::whereHas('ventaOrigen', fn ($q) => 
                            $q->where('id', $record->venta_id)
                        )
                        ->where('estado', ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION)
                        ->exists();
                })
                ->action(function (Proyecto $record): void {
                    // 1. Cancelar suscripciones pendientes
                    $suscripciones = \App\Models\ClienteSuscripcion::whereHas('ventaOrigen', fn ($q) =>
                            $q->where('id', $record->venta_id)
                        )
                        ->where('estado', ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION)
                        ->get();
                    foreach ($suscripciones as $suscripcion) {
                        // Si por alguna razón ya tiene ID en Stripe, cancelarla también allí
                        if ($suscripcion->stripe_subscription_id) {
                            try {
                                \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                                \Stripe\Subscription::retrieve($suscripcion->stripe_subscription_id)
                                    ->cancel();
                            } catch (\Throwable $e) {
                                Log::warning('No se pudo cancelar suscripción en Stripe: ' . $e->getMessage());
                            }
                        }
                        $suscripcion->update(['estado' => 'cancelada']);
                    }

                    // 2. Cambiar estado de la venta
                    $venta = \App\Models\Venta::find($record->venta_id);
                    if ($venta) {
                        $venta->update(['estado' => VentaEstadoEnum::RECURRENTE_CANCELADO]);

                        // 3. Email al cliente
                        try {
                            if ($venta->cliente?->email_contacto) {
                                Mail::to($venta->cliente->email_contacto)
                                    ->send(new \App\Mail\SuscripcionCanceladaMail($venta->cliente, $venta));
                            }
                        } catch (\Throwable $e) {
                            Log::warning('No se pudo enviar SuscripcionCanceladaMail: ' . $e->getMessage());
                        }
                    }

                    // Comentario en el proyecto
                    try {
                        $record->comentarios()->create([
                            'user_id'   => auth()->id(),
                            'contenido' => '🚫 Suscripción recurrente cancelada manualmente. No se activará el servicio mensual.',
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('No se pudo crear comentario en proyecto: ' . $e->getMessage());
                    }

                    Notification::make()
                        ->title('Suscripción cancelada correctamente')
                        ->success()
                        ->send();
                }),
        ];
    }


    public function relations(): array
    {
        return [
            DocumentosRelationManager::class,
        ];
    }
}