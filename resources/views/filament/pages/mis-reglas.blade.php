<x-filament-panels::page>

    {{-- ═══════════════════════════════════════════════════════
         SECCIÓN 1: Mi estado como comercial
    ════════════════════════════════════════════════════════════ --}}
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6 shadow-sm">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Mi estado</h2>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

            {{-- Fecha inicio --}}
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                    Comercial desde
                </p>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                    {{ $fechaInicioComercial?->format('d/m/Y') ?? '—' }}
                </p>
            </div>

            {{-- Período de prueba --}}
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                    Período de prueba
                </p>
                @if ($enPeriodoPrueba)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                        En prueba — Mes {{ $mesPrueba }}
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                        Fuera de prueba
                    </span>
                @endif
            </div>

            {{-- Contrato firmado --}}
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                    Contrato de incentivos
                </p>
                @if ($tieneContratoFirmado)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                        ✓ Firmado
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                        Pendiente de firma
                    </span>
                @endif
            </div>

            {{-- Total reglas activas --}}
            <div>
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                    Reglas activas
                </p>
                <p class="text-sm font-semibold text-gray-900 dark:text-white">
                    {{ $reglas->count() }}
                </p>
            </div>

        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         SECCIÓN 2: Reglas agrupadas por tipo
    ════════════════════════════════════════════════════════════ --}}
    @php
        $recurrentes = $reglas->where('tipo_servicio', 'recurrente');
        $unicos      = $reglas->where('tipo_servicio', 'unico');
    @endphp

    @if ($recurrentes->count())
        <div class="mt-8">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                Reglas sobre servicios recurrentes
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 mb-4">
                Servicios con suscripción mensual
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($recurrentes as $regla)
                    @include('filament.pages.partials.card-regla', [
                        'regla'            => $regla,
                        'serviciosPorRegla' => $serviciosPorRegla,
                    ])
                @endforeach
            </div>
        </div>
    @endif

    @if ($unicos->count())
        <div class="mt-8">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                Reglas sobre servicios de pago único
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 mb-4">
                Servicios de cobro puntual
            </p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($unicos as $regla)
                    @include('filament.pages.partials.card-regla', [
                        'regla'            => $regla,
                        'serviciosPorRegla' => $serviciosPorRegla,
                    ])
                @endforeach
            </div>
        </div>
    @endif

    @if ($reglas->isEmpty())
        <div class="text-center py-12 text-gray-500 dark:text-gray-400">
            Todavía no tienes reglas de comisión asignadas. Contacta con administración.
        </div>
    @endif

    {{-- ═══════════════════════════════════════════════════════
         SECCIÓN 3: Condiciones generales (informativa)
    ════════════════════════════════════════════════════════════ --}}
    @if ($config)
        <div class="bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl p-6 mt-8">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">
                Condiciones generales
            </h3>
            <ul class="text-sm space-y-1 text-gray-700 dark:text-gray-300">
                <li>
                    Meses consecutivos sin alcanzar mínimos que activan alerta:
                    <strong>{{ $config->meses_consecutivos_despido }}</strong>
                </li>
                <li>
                    Meses alternos sin alcanzar mínimos en un período de
                    <strong>{{ $config->periodo_meses_alternos }}</strong> meses:
                    <strong>{{ $config->meses_alternos_despido }}</strong>
                </li>
            </ul>
            <p class="text-xs text-gray-400 dark:text-gray-500 mt-3">
                Estas condiciones son informativas y se revisan mensualmente por administración.
            </p>
        </div>
    @endif

</x-filament-panels::page>
