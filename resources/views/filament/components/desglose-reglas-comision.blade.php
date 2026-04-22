@php
    use Illuminate\Support\Facades\DB;
    $algunaObligatoriaNoAlcanzo = false;
    $comercialId = $comercialId ?? null;
@endphp

<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Regla</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Facturación</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Mínimo</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Alcanzó</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Comisión</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
            @foreach($reglas as $regla)
                @php
                    $reglaModel    = \App\Models\ComisionRegla::where('nombre', $regla['regla_nombre'] ?? '')->first();
                    $esObligatoria = false;
                    if ($reglaModel && $comercialId) {
                        $esObligatoria = DB::table('comercial_reglas')
                            ->where('comercial_id', $comercialId)
                            ->where('regla_id', $reglaModel->id)
                            ->where('es_obligatoria', true)
                            ->exists();
                    }
                    if ($esObligatoria && !($regla['alcanzo_minimo'] ?? false)) {
                        $algunaObligatoriaNoAlcanzo = true;
                    }
                @endphp
                <tr>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ $regla['regla_nombre'] ?? '—' }}
                        @if($esObligatoria)
                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-bold bg-red-600 text-white">
                                ⚠️ OBLIGATORIA
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-300">€{{ number_format($regla['facturacion_neta'] ?? 0, 2, ',', '.') }}</td>
                    <td class="px-4 py-3 text-sm text-right text-gray-600 dark:text-gray-300">€{{ number_format($regla['minimo_requerido'] ?? 0, 2, ',', '.') }}</td>
                    <td class="px-4 py-3 text-sm text-center">
                        @if($regla['alcanzo_minimo'] ?? false)
                            <span class="text-green-600 dark:text-green-400 font-semibold">✅</span>
                        @else
                            <span class="text-red-600 dark:text-red-400 font-semibold">❌</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-right font-semibold text-gray-900 dark:text-gray-100">
                        @php
                            $mostrarTachado = $algunaObligatoriaNoAlcanzo
                                && ($regla['alcanzo_minimo'] ?? false)
                                && !empty($regla['comision_teorica'])
                                && $regla['comision_teorica'] > 0;
                        @endphp
                        @if($mostrarTachado)
                            <span class="line-through text-gray-400 dark:text-gray-500 mr-2">€{{ number_format($regla['comision_teorica'], 2, ',', '.') }}</span>
                            <span class="text-red-600 dark:text-red-400 font-bold">€0,00</span>
                        @else
                            €{{ number_format($regla['comision'] ?? 0, 2, ',', '.') }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($algunaObligatoriaNoAlcanzo)
        <div class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border-l-4 border-red-600 dark:border-red-400">
            <p class="text-sm text-red-800 dark:text-red-200">
                <strong>⚠️ Importante:</strong> Una o más reglas marcadas como OBLIGATORIAS no alcanzaron el mínimo.
                Según la política de incentivos, esto anula las comisiones de <strong>TODAS</strong> las reglas para este mes.
            </p>
        </div>
    @endif
</div>
