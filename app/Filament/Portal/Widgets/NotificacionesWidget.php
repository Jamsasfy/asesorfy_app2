<?php

namespace App\Filament\Portal\Widgets;

use App\Models\NotificacionPortal;
use Filament\Widgets\Widget;

class NotificacionesWidget extends Widget
{
    protected string $view = 'filament.portal.widgets.notificaciones-widget';

    protected int | string | array $columnSpan = 'full';

    public function getNotificaciones()
    {
        $user = auth()->user();
        
        // Obtener cliente activo
        $clienteActivoId = session('cliente_activo_id');
        if (!$clienteActivoId) {
            return collect();
        }
        
        // Obtener fecha de alta del cliente
        $cliente = \App\Models\Cliente::find($clienteActivoId);
        if (!$cliente || !$cliente->fecha_alta) {
            return collect();
        }
        
        // Notificaciones activas que el usuario aún no ha leído
        return NotificacionPortal::activas()
            ->where('created_at', '>=', $cliente->fecha_alta) // 🔥 SOLO DESDE QUE ES CLIENTE
            ->whereDoesntHave('vistas', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->where('visto_en_plataforma', true); // Solo si marcó como leída
            })
            ->where(function ($query) use ($user, $clienteActivoId) {
                $query->where('destinatarios', 'todos')
                    ->orWhere(function ($q) use ($clienteActivoId) {
                        // Por servicio: verificar si el cliente activo tiene suscripción a alguno de los servicios
                        $q->where('destinatarios', 'por_servicio')
                          ->whereRaw('JSON_OVERLAPS(filtro_servicios, COALESCE((
                              SELECT JSON_ARRAYAGG(servicio_id) FROM cliente_suscripciones
                              WHERE cliente_id = ? AND estado = "activa"
                          ), JSON_ARRAY()))', [$clienteActivoId]);
                    })
                    ->orWhere(function ($q) use ($clienteActivoId) {
                        // Cliente específico: verificar si el cliente activo está en la lista
                        $q->where('destinatarios', 'cliente_especifico')
                          ->whereJsonContains('filtro_clientes', (string)$clienteActivoId);
                    });
            })
            ->latest('created_at') // MÁS NUEVA ARRIBA
            ->limit(5)
            ->get();
    }

    public function marcarComoLeida($notificacionId)
    {
        $notificacion = NotificacionPortal::find($notificacionId);
        
        if ($notificacion) {
            $notificacion->marcarComoLeida(auth()->user());
            
            // Refrescar el widget para que desaparezca la notificación
            $this->dispatch('$refresh');
        }
    }
}
