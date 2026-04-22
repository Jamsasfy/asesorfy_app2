<div class="space-y-6">

    <!-- Resumen General -->
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">Periodo</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ \Carbon\Carbon::create($historial->año, $historial->mes, 1)->locale('es')->isoFormat('MMMM YYYY') }}
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">Comercial</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $historial->comercial->name }}
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">Total Comisiones</p>
                <p class="text-lg font-semibold {{ $historial->total_comisiones_calculado > 0 ? 'text-green-600' : 'text-gray-900 dark:text-white' }}">
                    €{{ number_format($historial->total_comisiones_calculado, 2, ',', '.') }}
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-600 dark:text-gray-400">Estado Objetivos</p>
                <p class="text-lg font-semibold {{ $historial->alcanzo_todos_minimos_obligatorios ? 'text-green-600' : 'text-amber-600' }}">
                    {{ $historial->alcanzo_todos_minimos_obligatorios ? '✓ Superados' : '! No alcanzados' }}
                </p>
            </div>
        </div>
    </div>

    <!-- Desglose por Regla -->
    <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Desglose por Regla</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-2 text-left">Regla</th>
                        <th class="px-4 py-2 text-right">Mínimo</th>
                        <th class="px-4 py-2 text-right">Alcanzado</th>
                        <th class="px-4 py-2 text-center">%</th>
                        <th class="px-4 py-2 text-right">Comisión</th>
                        <th class="px-4 py-2 text-center">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($desgloseReglas as $regla)
                        @php
                            $supera = $regla['facturacion_neta'] >= $regla['minimo_requerido'];
                        @endphp
                        <tr class="{{ $supera ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20' }}">
                            <td class="px-4 py-2 font-medium">{{ $regla['regla_nombre'] }}</td>
                            <td class="px-4 py-2 text-right">€{{ number_format($regla['minimo_requerido'], 2, ',', '.') }}</td>
                            <td class="px-4 py-2 text-right">€{{ number_format($regla['facturacion_neta'], 2, ',', '.') }}</td>
                            <td class="px-4 py-2 text-center">{{ number_format($regla['porcentaje_comision'] ?? 0, 2) }}%</td>
                            <td class="px-4 py-2 text-right font-semibold">€{{ number_format($regla['comision'], 2, ',', '.') }}</td>
                            <td class="px-4 py-2 text-center">
                                <span class="px-2 py-1 text-xs font-semibold rounded {{ $supera ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100' }}">
                                    {{ $supera ? 'SUPERADO' : 'NO ALCANZADO' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Detalle de Ventas -->
    <div>
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Detalle de Ventas del Mes</h3>
        @if($ventas->count() > 0)
            <div class="overflow-x-auto max-h-96">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-800 sticky top-0">
                        <tr>
                            <th class="px-4 py-2 text-left">Cliente</th>
                            <th class="px-4 py-2 text-left">Fecha</th>
                            <th class="px-4 py-2 text-left">Servicio</th>
                            <th class="px-4 py-2 text-center">Tipo</th>
                            <th class="px-4 py-2 text-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @php $totalVentas = 0; @endphp
                        @foreach($ventas as $venta)
                            @foreach($venta->items as $item)
                                @php $totalVentas += $item->precio_unitario; @endphp
                                <tr>
                                    <td class="px-4 py-2">{{ $venta->cliente->nombre_fiscal ?? $venta->cliente->razon_social }}</td>
                                    <td class="px-4 py-2">{{ \Carbon\Carbon::parse($venta->fecha_venta)->format('d/m/Y') }}</td>
                                    <td class="px-4 py-2">{{ $item->servicio->nombre }}</td>
                                    <td class="px-4 py-2 text-center">
                                        <span class="px-2 py-1 text-xs font-semibold rounded {{ $item->servicio->tipo->value === 'recurrente' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                                            {{ strtoupper($item->servicio->tipo->value) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-right">€{{ number_format($item->precio_unitario, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                        <tr class="bg-gray-100 dark:bg-gray-800 font-semibold">
                            <td colspan="4" class="px-4 py-2 text-right">TOTAL FACTURADO:</td>
                            <td class="px-4 py-2 text-right">€{{ number_format($totalVentas, 2, ',', '.') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-center text-gray-500 py-8">No se registraron ventas en este periodo.</p>
        @endif
    </div>

    <!-- Advertencia si no supera objetivos -->
    @if(!$historial->alcanzo_todos_minimos_obligatorios)
        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
            <p class="text-sm text-amber-800 dark:text-amber-200">
                <strong>Atención:</strong> Este mes no se alcanzaron todos los mínimos establecidos.
                Al aprobar, se generará y enviará el informe oficial al comercial según lo establecido
                en el contrato de incentivos.
            </p>
        </div>
    @endif

    <!-- Bonos (solo lectura cuando ya está aprobado) -->
    @if(isset($soloLectura) && $soloLectura && $historial->total_bonos > 0)
        <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Bonos Adicionales</h3>
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Importe del Bono</p>
                        <p class="text-xl font-bold text-green-600">€{{ number_format($historial->total_bonos, 2, ',', '.') }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Descripción</p>
                        <p class="text-base text-gray-900 dark:text-white">
                            {{ $historial->datos_adicionales['bono_descripcion'] ?? 'Sin descripción' }}
                        </p>
                    </div>
                </div>
                <div class="mt-4 pt-4 border-t border-green-300 dark:border-green-700">
                    <div class="flex justify-between items-center">
                        <p class="text-base font-semibold text-gray-900 dark:text-white">Total Final (Comisiones + Bonos):</p>
                        <p class="text-2xl font-bold text-green-600">€{{ number_format($historial->total_final, 2, ',', '.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
