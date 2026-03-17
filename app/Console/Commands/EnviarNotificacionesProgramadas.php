<?php

namespace App\Console\Commands;

use App\Models\NotificacionPortal;
use App\Jobs\EnviarNotificacionPortalJob;
use Illuminate\Console\Command;

class EnviarNotificacionesProgramadas extends Command
{
    protected $signature = 'notificaciones:enviar-programadas';
    protected $description = 'Envía las notificaciones programadas cuya fecha ha llegado';

    public function handle()
    {
        $notificaciones = NotificacionPortal::where('activa', true)
            ->where('enviada', false)
            ->whereNotNull('fecha_publicacion')
            ->where('fecha_publicacion', '<=', now())
            ->get();

        if ($notificaciones->isEmpty()) {
            $this->info('No hay notificaciones programadas pendientes.');
            return 0;
        }

        foreach ($notificaciones as $notif) {
            dispatch(new EnviarNotificacionPortalJob($notif));
            $this->info("Notificación #{$notif->id} enviada: {$notif->titulo}");
        }

        $this->info("Total enviadas: {$notificaciones->count()}");
        return 0;
    }
}
