<div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5 shadow-sm">

    {{-- Cabecera: nombre + badge obligatoria --}}
    <div class="flex items-start justify-between gap-3 mb-4">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white leading-tight">
            {{ $regla->nombre }}
        </h3>
        @if ($regla->pivot->es_obligatoria)
            <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                Obligatoria
            </span>
        @endif
    </div>

    {{-- Métricas --}}
    <dl class="space-y-2 text-sm mb-4">
        <div class="flex justify-between">
            <dt class="text-gray-500 dark:text-gray-400">Facturación mínima mensual</dt>
            <dd class="font-semibold text-gray-900 dark:text-white">
                {{ number_format((float) $regla->minimo_mensual, 2, ',', '.') }} €
            </dd>
        </div>
        <div class="flex justify-between">
            <dt class="text-gray-500 dark:text-gray-400">Porcentaje de comisión</dt>
            <dd class="font-semibold text-gray-900 dark:text-white">
                {{ number_format((float) $regla->porcentaje_comision, 2, ',', '.') }}%
            </dd>
        </div>
        @if ($regla->penalizacion_baja_antes_meses > 0)
            <div class="flex justify-between">
                <dt class="text-gray-500 dark:text-gray-400">Penalización si baja antes de</dt>
                <dd class="font-semibold text-red-600 dark:text-red-400">
                    {{ $regla->penalizacion_baja_antes_meses }} meses
                </dd>
            </div>
        @endif
    </dl>

    {{-- Servicios aplicables --}}
    @php
        $servicios = $serviciosPorRegla[$regla->id] ?? [];
        $mostrar   = array_slice($servicios, 0, 5);
        $resto     = count($servicios) - count($mostrar);
    @endphp

    @if (count($servicios) > 0)
        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
                Servicios aplicables
            </p>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($mostrar as $nombre)
                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-xs text-gray-700 dark:text-gray-300">
                        {{ $nombre }}
                    </span>
                @endforeach
                @if ($resto > 0)
                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-xs text-gray-500 dark:text-gray-400">
                        y {{ $resto }} más
                    </span>
                @endif
            </div>
        </div>
    @else
        <p class="text-xs text-gray-400 dark:text-gray-500">Sin servicios especificados.</p>
    @endif
</div>
