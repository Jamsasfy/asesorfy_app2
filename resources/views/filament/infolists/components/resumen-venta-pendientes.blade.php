@php
    use Illuminate\Support\Facades\Blade;

    $record = $getRecord();
    $proyectosHtml = [];
    $suscripcionesHtml = [];

    if ($record->venta) {
        /**
         * =========================
         * PROYECTOS PENDIENTES
         * =========================
         */
        $otrosProyectos = $record->venta->proyectos()
            ->where('id', '!=', $record->id)
            ->where('estado', '!=', \App\Enums\ProyectoEstadoEnum::Finalizado)
            ->with('user')
            ->get();

        foreach ($otrosProyectos as $proyecto) {
            $url = \App\Filament\Resources\ProyectoResource::getUrl('view', ['record' => $proyecto]);

            // Icono maletín (FIJO, no escalable)
            $icon = Blade::render(
                "<x-heroicon-o-briefcase style='width:20px;height:20px;min-width:20px;min-height:20px;color:#f59e0b;' />"
            );

            // Link del proyecto (color inline)
            $proyectoLink = "
                <a href='{$url}' target='_blank'
                   style='color:#f59e0b;font-weight:600;text-decoration:none;white-space:nowrap;'>
                    {$proyecto->nombre}
                </a>
            ";

            // Badge asesor
            if ($proyecto->user) {
                $badgeStyle = "background:#dcfce7;color:#166534;";
                $badgeText  = e($proyecto->user->name);
            } else {
                $badgeStyle = "background:#fef3c7;color:#92400e;";
                $badgeText  = 'Sin asignar';
            }

            $badgeHtml = "
                <span style='{$badgeStyle}padding:3px 8px;border-radius:9999px;
                             font-size:12px;font-weight:500;white-space:nowrap;'>
                    {$badgeText}
                </span>
            ";

            $proyectosHtml[] = "
                <div style='display:flex;align-items:center;gap:8px;padding:4px 0;'>
                    {$icon}
                    {$proyectoLink}
                    <span style='color:#9ca3af;'>–</span>
                    {$badgeHtml}
                </div>
            ";
        }

        /**
         * =========================
         * SUSCRIPCIONES PENDIENTES
         * =========================
         */
        $suscripciones = $record->venta->suscripciones()
            ->where('estado', \App\Enums\ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION)
            ->with('servicio')
            ->get();

        foreach ($suscripciones as $suscripcion) {
            // Icono alerta (FIJO)
            $icon = Blade::render(
                "<x-heroicon-o-exclamation-triangle style='width:20px;height:20px;min-width:20px;min-height:20px;color:#ef4444;' />"
            );

            $serviceName = e($suscripcion->servicio->nombre);

            $suscripcionesHtml[] = "
                <div style='display:flex;align-items:flex-start;gap:8px;padding:4px 0;'>
                    {$icon}
                    <div>
                        <div style='color:#ef4444;font-size:14px;font-weight:600;'>
                            SERVICIO MENSUAL: {$serviceName}
                        </div>
                        <div style='font-size:12px;color:#6b7280;'>
                            (se activa cuando finalicen los proyectos)
                        </div>
                    </div>
                </div>
            ";
        }
    }

    $htmlParts = array_merge($proyectosHtml, $suscripcionesHtml);
@endphp

@if (empty($htmlParts))
    <div style="display:flex;align-items:center;gap:8px;
                font-size:14px;font-weight:600;color:#16a34a;">
        <span style="font-size:18px;line-height:1;">✅</span>
        <span>No hay elementos pendientes en esta venta, o es un proyecto único.</span>
    </div>
@else
    <div style="display:flex;flex-direction:column;gap:10px;">
        {!! implode('', $htmlParts) !!}
    </div>
@endif
