<div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700
            {{ $contrato->estaFirmado() ? 'border-l-4 border-green-500' : 'border-l-4 border-orange-500' }}
            p-6">

    {{-- HEADER --}}
    <div class="flex items-start justify-between mb-4">
        <div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                {{ $esBase ? 'Contrato base de incentivos' : 'Anexo al contrato' }}
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                Enviado el {{ $contrato->fecha_envio?->format('d/m/Y H:i') ?? '—' }}
            </p>
        </div>

        @if ($contrato->estaFirmado())
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                ✓ Firmado
            </span>
        @else
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                ⏳ Pendiente de firma
            </span>
        @endif
    </div>

    {{-- ACCIÓN PRINCIPAL SEGÚN ESTADO --}}
    @if ($contrato->estaPendiente())
        <div class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 p-4 rounded-lg mb-4">
            <p class="text-sm font-semibold text-orange-800 dark:text-orange-200 mb-3">
                Este contrato está pendiente de tu firma.
            </p>
            <a href="{{ $contrato->getUrlFirma() }}"
               target="_blank"
               class="inline-flex items-center gap-2 px-5 py-3 bg-orange-500 hover:bg-orange-600 text-white font-semibold text-sm rounded-lg shadow-sm transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M9 13l6.586-6.586a2 2 0 112.828 2.828L11.828 15.828a2 2 0 01-1.415.586H8v-2.414a2 2 0 01.586-1.414z" />
                </svg>
                Revisar y firmar contrato
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                    Fecha de firma
                </p>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                    {{ $contrato->fecha_firma?->format('d/m/Y H:i') }}
                </p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                    IP de firma
                </p>
                <p class="font-mono text-sm text-gray-700 dark:text-gray-300">
                    {{ $contrato->ip_firma ?? '—' }}
                </p>
            </div>
        </div>

        @if ($contrato->pdf_path)
            <a href="{{ route('descargar-contrato-firmado', ['id' => $contrato->id]) }}"
               download
               class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm rounded-lg shadow-sm transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3" />
                </svg>
                Descargar PDF firmado
            </a>
        @endif

        @if ($contrato->hash_documento)
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                    Hash SHA-256 del documento
                </p>
                <p class="font-mono text-xs break-all text-gray-600 dark:text-gray-400">
                    {{ $contrato->hash_documento }}
                </p>
                <p class="text-xs text-gray-400 dark:text-gray-500 mt-2">
                    Este hash garantiza la integridad del documento firmado. Cualquier modificación posterior alteraría este valor.
                </p>
            </div>
        @endif
    @endif

    {{-- SNAPSHOT DE REGLAS --}}
    @if (!empty($contrato->reglas_snapshot))
        <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <details>
                <summary class="cursor-pointer text-sm font-semibold text-gray-700 dark:text-gray-300 select-none">
                    Ver condiciones económicas del contrato ({{ count($contrato->reglas_snapshot) }} reglas)
                </summary>
                <div class="mt-3 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-900 text-left">
                                <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400">Regla</th>
                                <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400 text-right">Mínimo mensual</th>
                                <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400 text-right">% Comisión</th>
                                <th class="px-3 py-2 font-medium text-gray-600 dark:text-gray-400 text-center">Obligatoria</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($contrato->reglas_snapshot as $regla)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="px-3 py-2 text-gray-900 dark:text-white">
                                        {{ $regla['nombre'] ?? '—' }}
                                    </td>
                                    <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">
                                        {{ number_format((float) ($regla['minimo'] ?? 0), 2, ',', '.') }} €
                                    </td>
                                    <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">
                                        {{ number_format((float) ($regla['porcentaje'] ?? 0), 2, ',', '.') }}%
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        @if (!empty($regla['es_obligatoria']))
                                            <span class="text-amber-600 dark:text-amber-400 font-medium">Sí</span>
                                        @else
                                            <span class="text-gray-400">No</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </div>
    @endif

</div>
