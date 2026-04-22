<div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-4 space-y-2 text-sm">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <span class="font-semibold text-gray-700 dark:text-gray-300">Tipo:</span>
            <span class="ml-2 px-2 py-1 rounded text-xs {{ $regla->tipo_servicio === 'recurrente' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' }}">
                {{ $regla->tipo_servicio === 'recurrente' ? 'Recurrente' : 'Único' }}
            </span>
        </div>
        <div>
            <span class="font-semibold text-gray-700 dark:text-gray-300">Estado:</span>
            <span class="ml-2 px-2 py-1 rounded text-xs {{ $regla->activa ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' }}">
                {{ $regla->activa ? 'Activa' : 'Inactiva' }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-4 pt-2 border-t border-gray-200 dark:border-gray-700">
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Mínimo Mensual</div>
            <div class="text-lg font-bold text-gray-900 dark:text-gray-100">€{{ number_format($regla->minimo_mensual, 2, ',', '.') }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Comisión</div>
            <div class="text-lg font-bold text-green-600 dark:text-green-400">{{ $regla->porcentaje_comision }}%</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 dark:text-gray-400">Penalización Bajas</div>
            <div class="text-lg font-bold text-orange-600 dark:text-orange-400">&lt; {{ $regla->penalizacion_baja_antes_meses }} meses</div>
        </div>
    </div>

    @if($regla->servicios_ids)
        <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">Servicios específicos:</div>
            <div class="flex flex-wrap gap-1">
                @foreach($regla->servicios_ids as $servicioId)
                    @php $servicio = \App\Models\Servicio::find($servicioId); @endphp
                    @if($servicio)
                        <span class="px-2 py-0.5 bg-gray-200 dark:bg-gray-700 rounded text-xs">{{ $servicio->nombre }}</span>
                    @endif
                @endforeach
            </div>
        </div>
    @else
        <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
            <div class="text-xs text-gray-500 dark:text-gray-400">✓ Aplica a TODOS los servicios del tipo</div>
        </div>
    @endif
</div>
