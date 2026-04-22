<x-filament-panels::page>

    {{-- ═══════════════════════════════════════════════════════
         A) DESGLOSE POR REGLAS
    ════════════════════════════════════════════════════════════ --}}
    <x-filament::section>
        <x-slot name="heading">Desglose por Reglas</x-slot>

        @php
            $desgloseReglas = $historial->datos_adicionales['desglose_reglas'] ?? [];
        @endphp

        @if (empty($desgloseReglas))
            <p class="text-sm text-gray-500 dark:text-gray-400">Sin desglose disponible.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 dark:border-gray-700 text-left">
                            <th class="pb-3 pr-4 font-semibold text-gray-700 dark:text-gray-300">Regla</th>
                            <th class="pb-3 pr-4 font-semibold text-gray-700 dark:text-gray-300 text-right">Facturación Neta</th>
                            <th class="pb-3 pr-4 font-semibold text-gray-700 dark:text-gray-300 text-right">Mínimo Requerido</th>
                            <th class="pb-3 pr-4 font-semibold text-gray-700 dark:text-gray-300 text-center">Alcanzado</th>
                            <th class="pb-3 pr-4 font-semibold text-gray-700 dark:text-gray-300 text-right">% Comisión</th>
                            <th class="pb-3 font-semibold text-gray-700 dark:text-gray-300 text-right">Comisión Generada</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($desgloseReglas as $regla)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-3 pr-4 font-medium text-gray-900 dark:text-white">
                                    {{ $regla['regla_nombre'] ?? '—' }}
                                </td>
                                <td class="py-3 pr-4 text-right text-gray-700 dark:text-gray-300">
                                    {{ number_format((float) ($regla['facturacion_neta'] ?? 0), 2, ',', '.') }} €
                                </td>
                                <td class="py-3 pr-4 text-right text-gray-700 dark:text-gray-300">
                                    {{ number_format((float) ($regla['minimo_requerido'] ?? 0), 2, ',', '.') }} €
                                </td>
                                <td class="py-3 pr-4 text-center">
                                    @if ($regla['alcanzo_minimo'] ?? false)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            ✓ Sí
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                            ✗ No
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 pr-4 text-right text-gray-700 dark:text-gray-300">
                                    {{ number_format((float) ($regla['porcentaje_comision'] ?? 0), 2, ',', '.') }}%
                                </td>
                                <td class="py-3 text-right font-semibold {{ ($regla['alcanzo_minimo'] ?? false) ? 'text-green-700 dark:text-green-400' : 'text-gray-400 dark:text-gray-500' }}">
                                    {{ number_format((float) ($regla['comision'] ?? 0), 2, ',', '.') }} €
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-300 dark:border-gray-600">
                            <td colspan="5" class="pt-3 pr-4 font-semibold text-gray-700 dark:text-gray-300">Total Comisiones</td>
                            <td class="pt-3 text-right font-bold text-gray-900 dark:text-white">
                                {{ number_format((float) $historial->total_comisiones_calculado, 2, ',', '.') }} €
                            </td>
                        </tr>
                        @if ($historial->total_bonos > 0)
                            <tr>
                                <td colspan="5" class="pt-1 pr-4 text-gray-600 dark:text-gray-400">Bonos adicionales</td>
                                <td class="pt-1 text-right font-semibold text-yellow-600 dark:text-yellow-400">
                                    + {{ number_format((float) $historial->total_bonos, 2, ',', '.') }} €
                                </td>
                            </tr>
                            <tr>
                                <td colspan="5" class="pt-1 pr-4 font-bold text-gray-900 dark:text-white">TOTAL FINAL</td>
                                <td class="pt-1 text-right font-bold text-lg text-blue-700 dark:text-blue-400">
                                    {{ number_format((float) $historial->total_final, 2, ',', '.') }} €
                                </td>
                            </tr>
                        @endif
                    </tfoot>
                </table>
            </div>
        @endif
    </x-filament::section>

    {{-- ═══════════════════════════════════════════════════════
         B) DETALLE DE COMISIONES MENSUALES POR REGLA
    ════════════════════════════════════════════════════════════ --}}
    <x-filament::section>
        <x-slot name="heading">Detalle por Regla</x-slot>

        @if ($comisionesMensuales->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Sin detalle de comisiones para este período.</p>
        @else
            <div class="space-y-6">
                @foreach ($comisionesMensuales as $comision)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">

                        {{-- Cabecera de la regla --}}
                        <div class="px-4 py-3 {{ $comision->alcanzo_minimo ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20' }} flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="font-semibold text-gray-900 dark:text-white">
                                    {{ $comision->regla?->nombre ?? 'Regla #' . $comision->regla_id }}
                                </span>
                                @if ($comision->es_regla_obligatoria)
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300">Obligatoria</span>
                                @endif
                                @if ($comision->alcanzo_minimo)
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300">✓ Mínimo alcanzado</span>
                                @else
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300">✗ Mínimo no alcanzado</span>
                                @endif
                            </div>
                            <div class="text-right">
                                <div class="text-sm text-gray-600 dark:text-gray-400">Facturación neta: <strong>{{ number_format((float) $comision->facturacion_neta, 2, ',', '.') }} €</strong></div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">Comisión ({{ $comision->porcentaje }}%): <strong class="text-green-700 dark:text-green-400">{{ number_format((float) $comision->importe_comision_calculado, 2, ',', '.') }} €</strong></div>
                            </div>
                        </div>

                        {{-- Detalle línea a línea --}}
                        @php $esRecurrente = ($comision->regla?->tipo_servicio === 'recurrente'); @endphp
                        @if ($comision->detalles->isNotEmpty())
                            <div class="overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="bg-gray-50 dark:bg-gray-800 text-left">
                                            <th class="px-4 py-2 font-medium text-gray-600 dark:text-gray-400">Fecha venta</th>
                                            <th class="px-4 py-2 font-medium text-gray-600 dark:text-gray-400">Nº Factura</th>
                                            <th class="px-4 py-2 font-medium text-gray-600 dark:text-gray-400">Servicio</th>
                                            <th class="px-4 py-2 font-medium text-gray-600 dark:text-gray-400">Cliente</th>
                                            @if ($esRecurrente)
                                                <th class="px-4 py-2 font-medium text-gray-600 dark:text-gray-400">Fecha baja</th>
                                            @endif
                                            <th class="px-4 py-2 font-medium text-gray-600 dark:text-gray-400 text-right">Importe</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($comision->detalles as $detalle)
                                            <tr class="border-t border-gray-100 dark:border-gray-700 {{ $detalle->importe < 0 ? 'bg-red-50/30 dark:bg-red-900/10' : '' }}">
                                                <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                                                    {{ $detalle->factura?->fecha_emision?->format('d/m/Y') ?? '—' }}
                                                </td>
                                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                                    @if ($detalle->factura)
                                                        {{ $detalle->factura->serie }}-{{ $detalle->factura->numero_factura }}
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                                    {{ $detalle->servicio?->nombre ?? '—' }}
                                                </td>
                                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                                    {{ $detalle->factura?->cliente?->razon_social ?? '—' }}
                                                </td>
                                                @if ($esRecurrente)
                                                    <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                                                        @if ($detalle->clienteSuscripcion)
                                                            @if ($detalle->clienteSuscripcion->fecha_fin)
                                                                {{ $detalle->clienteSuscripcion->fecha_fin->format('d/m/Y') }}
                                                            @else
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">Activa</span>
                                                            @endif
                                                        @else
                                                            —
                                                        @endif
                                                    </td>
                                                @endif
                                                <td class="px-4 py-2 text-right font-medium {{ $detalle->importe < 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">
                                                    {{ number_format((float) $detalle->importe, 2, ',', '.') }} €
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="px-4 py-3 text-sm text-gray-400 dark:text-gray-500">Sin líneas de detalle.</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    {{-- ═══════════════════════════════════════════════════════
         C) INFORME PDF
    ════════════════════════════════════════════════════════════ --}}
    <x-filament::section>
        <x-slot name="heading">Informe PDF</x-slot>

        @if ($historial->informe_pdf_path)
            <div class="flex items-start gap-6">
                <div class="flex-1 space-y-2">
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        <span class="font-medium">Generado el:</span>
                        {{ $historial->informe_generado_at?->format('d/m/Y H:i') ?? '—' }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400 break-all">
                        <span class="font-medium">Hash SHA-256:</span>
                        <code class="text-xs bg-gray-100 dark:bg-gray-800 px-2 py-0.5 rounded">
                            {{ $historial->informe_hash ?? '—' }}
                        </code>
                    </div>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">
                        El hash garantiza la integridad del documento. Cualquier modificación posterior al fichero alteraría el hash.
                    </p>
                </div>

                <x-filament::button
                    tag="a"
                    href="{{ route('descargar-informe-comision', $historial->id) }}"
                    target="_blank"
                    icon="heroicon-o-arrow-down-tray"
                    color="primary"
                    size="lg"
                >
                    Descargar informe PDF
                </x-filament::button>
            </div>
        @else
            <p class="text-sm text-gray-400 dark:text-gray-500">
                El informe PDF aún no ha sido generado para este período.
            </p>
        @endif
    </x-filament::section>

</x-filament-panels::page>
