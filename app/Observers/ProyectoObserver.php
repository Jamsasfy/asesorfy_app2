<?php

namespace App\Observers;

use Filament\Actions\Action;
use Throwable;
use App\Enums\ClienteEstadoEnum;
use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\ProyectoEstadoEnum;
use App\Filament\Resources\ClienteResource;
use App\Filament\Resources\ProyectoResource;
use App\Models\Proyecto;
use App\Models\User;
use Filament\Notifications\Notification;
use App\Services\StripeSubscriptionService;
use Illuminate\Support\Facades\Log;

class ProyectoObserver
{
    public function created(Proyecto $proyecto): void
    {
        try {
            $cliente = $proyecto->venta?->cliente;
            if ($cliente && !in_array($cliente->estado, [
                ClienteEstadoEnum::ACTIVO,
                ClienteEstadoEnum::EN_PROYECTO,
            ])) {
                $cliente->update(['estado' => ClienteEstadoEnum::EN_PROYECTO]);
                $cliente->comentarios()->create([
                    'user_id'   => 9999,
                    'contenido' => '🔧 Cliente pasado a En Proyecto — Lead #' . ($proyecto->venta->lead_id ?? '—') . ' · Venta #' . $proyecto->venta->id . ' · Proyecto #' . $proyecto->id . ' creado: "' . $proyecto->nombre . '".',
                ]);
                $proyecto->comentarios()->create([
                    'user_id'   => 9999,
                    'contenido' => '📁 Proyecto creado para cliente ' . $cliente->razon_social . ' — Lead #' . ($proyecto->venta->lead_id ?? '—') . ' · Venta #' . $proyecto->venta->id . '.',
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('[ProyectoObserver] Error en created: ' . $e->getMessage());
        }
    }

    public function updated(Proyecto $proyecto): void
    {
        // 1) NOTIFICAR Y COMENTAR ASIGNACIÓN DE ASESOR
        if ($proyecto->wasChanged('user_id') && $proyecto->user_id) {
            if ($asesor = User::find($proyecto->user_id)) {
                Notification::make()
                    ->title('Te han asignado un nuevo proyecto')
                    ->body("Proyecto: '{$proyecto->nombre}'")
                    ->icon('heroicon-o-briefcase')
                    ->actions([
                        Action::make('view')
                            ->label('Ver Proyecto')
                            ->url(ProyectoResource::getUrl('view', ['record' => $proyecto]))
                            ->openUrlInNewTab()
                            ->markAsRead()
                            ->close(),
                    ])
                    ->sendToDatabase($asesor);

                try {
                    \Illuminate\Support\Facades\Mail::to($asesor->email)
                        ->send(new \App\Mail\ProyectoAsignadoMail($asesor, $proyecto));
                } catch (\Throwable $e) {
                    Log::warning('No se pudo enviar email asignación proyecto: ' . $e->getMessage());
                }

                // Comentario en proyecto
                $proyecto->comentarios()->create([
                    'user_id'   => 9999,
                    'contenido' => '👤 Proyecto asignado a ' . $asesor->name . '.',
                ]);

                // Comentario en cliente + cambio estado
                try {
                    $cliente = $proyecto->venta?->cliente;
                    if ($cliente && !in_array($cliente->estado, [
                        ClienteEstadoEnum::ACTIVO,
                        ClienteEstadoEnum::EN_PROYECTO,
                    ])) {
                        $cliente->update(['estado' => ClienteEstadoEnum::EN_PROYECTO]);
                        $cliente->comentarios()->create([
                            'user_id'   => 9999,
                            'contenido' => '🔧 Cliente en proyecto — asesor de proyecto asignado: ' . $asesor->name . '.',
                        ]);
                    }
                } catch (Throwable $e) {
                    Log::warning('[ProyectoObserver] Error al cambiar estado a EN_PROYECTO en updated: ' . $e->getMessage());
                }
            }
        }

        // 2) GESTIÓN DE FINALIZACIÓN / CANCELACIÓN
        if (!$proyecto->venta || !$proyecto->wasChanged('estado')) {
            return;
        }

        // A) PROYECTO FINALIZADO
        if ($proyecto->estado === ProyectoEstadoEnum::Finalizado) {

            $quedanProyectosPendientes = $proyecto->venta->proyectos()
                ->where('id', '!=', $proyecto->id)
                ->where('estado', '!=', ProyectoEstadoEnum::Finalizado)
                ->exists();

            if ($quedanProyectosPendientes) {
                Log::info('[ProyectoObserver] Aún quedan proyectos pendientes', ['venta_id' => $proyecto->venta->id]);
                return;
            }

            // Comentario en proyecto
            $proyecto->comentarios()->create([
                'user_id'   => 9999,
                'contenido' => '🏁 Proyecto finalizado.',
            ]);

            // Activar suscripciones recurrentes diferidas
            $suscripciones = $proyecto->venta->suscripciones()
                ->where('estado', ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION)
                ->get();

            foreach ($suscripciones as $suscripcion) {
                if ($suscripcion->stripe_subscription_id) continue;

                try {
                    Log::info('🚀 Inicio real del servicio recurrente tras proyecto', ['suscripcion_id' => $suscripcion->id]);
                    StripeSubscriptionService::activarSuscripcion($suscripcion);
                } catch (Throwable $e) {
                    Log::error('❌ Error activando suscripción tras proyecto', [
                        'suscripcion_id' => $suscripcion->id,
                        'error'          => $e->getMessage(),
                    ]);
                    Notification::make()
                        ->title('Error activando suscripción')
                        ->body("Falló la activación automática de la suscripción #{$suscripcion->id}. Revisa los logs.")
                        ->danger()
                        ->sendToDatabase(
                            User::whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->get()
                        );
                }
            }

            // Cambiar estado del cliente
            try {
                $cliente = $proyecto->venta->cliente;

                if ($cliente && $cliente->estado === ClienteEstadoEnum::EN_PROYECTO) {
                    $tieneRecurrente = $proyecto->venta->suscripciones()
                        ->whereNotNull('stripe_subscription_id')
                        ->exists();

                    if ($tieneRecurrente) {
                        $cliente->update(['estado' => ClienteEstadoEnum::PENDIENTE_ASIGNACION]);
                        $cliente->comentarios()->create([
                            'user_id'   => 9999,
                            'contenido' => '🏁 Proyecto finalizado — pendiente de asignar asesor definitivo.',
                        ]);
                    } else {
                        $cliente->update(['estado' => ClienteEstadoEnum::PROYECTO_FINALIZADO]);
                        $cliente->comentarios()->create([
                            'user_id'   => 9999,
                            'contenido' => '🏁 Proyecto finalizado — sin servicio recurrente. Proyecto concluido.',
                        ]);
                    }

                    Log::info('[ProyectoObserver] Estado cliente actualizado tras finalizar proyecto', [
                        'cliente_id'   => $cliente->id,
                        'nuevo_estado' => $cliente->estado->value,
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning('[ProyectoObserver] Error al cambiar estado cliente tras finalizar: ' . $e->getMessage());
            }

            // Notificar admins/coordinadores si tiene recurrente
            try {
                $cliente = $proyecto->venta->cliente;

                if ($cliente && $cliente->estado === ClienteEstadoEnum::PENDIENTE_ASIGNACION) {
                    $adminsYCoords = User::whereHas('roles', fn ($q) =>
                        $q->whereIn('name', ['super_admin', 'coordinador'])
                    )->get();

                    Notification::make()
                        ->title('✅ Proyecto finalizado - Asignar asesor definitivo')
                        ->body("Todos los proyectos de {$cliente->razon_social} han finalizado y la suscripción está activa. Asigna el asesor definitivo para activar el cliente.")
                        ->warning()
                        ->actions([
                            Action::make('asignar_asesor')
                                ->label('Ir al cliente')
                                ->url(ClienteResource::getUrl('view', ['record' => $cliente->id]))
                                ->markAsRead(),
                        ])
                        ->sendToDatabase($adminsYCoords);

                    Log::info('🔔 Notificación enviada a admins para asignar asesor definitivo', [
                        'cliente_id' => $cliente->id,
                    ]);
                }
            } catch (Throwable $e) {
                Log::warning('No se pudo notificar asignación asesor definitivo: ' . $e->getMessage());
            }
        }

        // B) PROYECTO CANCELADO
        if ($proyecto->estado === ProyectoEstadoEnum::Cancelado) {

            $proyecto->comentarios()->create([
                'user_id'   => 9999,
                'contenido' => '❌ Proyecto cancelado.',
            ]);

            $otrosActivos = $proyecto->venta->proyectos()
                ->where('id', '!=', $proyecto->id)
                ->whereNotIn('estado', [
                    ProyectoEstadoEnum::Finalizado,
                    ProyectoEstadoEnum::Cancelado,
                ])
                ->exists();

            if (!$otrosActivos) {
                $suscripciones = $proyecto->venta->suscripciones()
                    ->where('estado', ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION)
                    ->get();

                foreach ($suscripciones as $suscripcion) {
                    $suscripcion->update([
                        'estado' => ClienteSuscripcionEstadoEnum::CANCELADA,
                    ]);
                    Log::info('⛔ Suscripción cancelada por proyecto cancelado', ['suscripcion_id' => $suscripcion->id]);
                }
            }
        }
    }
}