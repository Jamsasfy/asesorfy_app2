<?php

namespace App\Observers;

use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\ProyectoEstadoEnum;
use App\Filament\Resources\ProyectoResource;
use App\Models\Proyecto;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action;
use App\Services\StripeSubscriptionService;
// use App\Services\FacturacionRecurrenteService; // ❌ YA NO SE NECESITA AQUÍ DIRECTAMENTE
use Illuminate\Support\Facades\Log;

class ProyectoObserver
{
    public function updated(Proyecto $proyecto): void
    {
        // 1) NOTIFICAR ASIGNACIÓN (Igual que antes)
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
                            ->markAsRead()
                            ->close(),
                    ])
                    ->sendToDatabase($asesor);
            }
        }

        // 2) GESTIÓN DE FINALIZACIÓN / CANCELACIÓN
        if (!$proyecto->venta || !$proyecto->wasChanged('estado')) {
            return;
        }

        // A) PROYECTO FINALIZADO → INICIO REAL DEL SERVICIO RECURRENTE
        if ($proyecto->estado === ProyectoEstadoEnum::Finalizado) {

            // ¿Quedan otros proyectos pendientes?
            $quedanProyectosPendientes = $proyecto->venta->proyectos()
                ->where('id', '!=', $proyecto->id)
                ->where('estado', '!=', ProyectoEstadoEnum::Finalizado)
                ->exists();

            if ($quedanProyectosPendientes) {
                Log::info('[ProyectoObserver] Aún quedan proyectos pendientes', ['venta_id' => $proyecto->venta->id]);
                return;
            }

            // Activar suscripciones recurrentes diferidas
            $suscripciones = $proyecto->venta->suscripciones()
                ->where('estado', ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION)
                ->get();

            foreach ($suscripciones as $suscripcion) {
                // Solo si no tiene ID de Stripe (no está activada ya)
                if ($suscripcion->stripe_subscription_id) continue;

                try {
                    Log::info('🚀 Inicio real del servicio recurrente tras proyecto', ['suscripcion_id' => $suscripcion->id]);

                    // 🔥 LLAMADA UNIFICADA (Aquí está la magia)
                    // Este servicio se encarga de:
                    // 1. Activar suscripción local.
                    // 2. Crear suscripción Stripe.
                    // 3. Gestionar Prorrata (Cobro inmediato o diferido).
                    // 4. Generar Factura Local (Pagada o Pendiente).
                    StripeSubscriptionService::activarSuscripcion($suscripcion);

                } catch (\Throwable $e) {
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
        }

        // B) PROYECTO CANCELADO → CANCELAR SUSCRIPCIONES PENDIENTES
        if ($proyecto->estado === ProyectoEstadoEnum::Cancelado) {
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