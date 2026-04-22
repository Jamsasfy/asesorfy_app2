<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Historial de Rendimiento (Últimos 12 meses)
        </x-slot>

        @php
            $data = $this->getViewData();
            $historial               = $data['historial'];
            $consecutivosSinMinimo   = $data['consecutivosSinMinimo'];
            $alternosSinMinimo       = $data['alternosSinMinimo'];
            $enRiesgoConsecutivos    = $data['enRiesgoConsecutivos'];
            $enRiesgoAlternos        = $data['enRiesgoAlternos'];
            $config                  = $data['config'];
        @endphp

        <div class="space-y-4">

            @if($enRiesgoConsecutivos || $enRiesgoAlternos)
                <div class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                    <div class="flex items-center gap-2 text-red-700 dark:text-red-400 font-semibold mb-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        ⚠️ ALERTA DE RENDIMIENTO
                    </div>
                    <ul class="text-sm text-red-600 dark:text-red-300 space-y-1">
                        @if($enRiesgoConsecutivos)
                            <li>• Llevas {{ $consecutivosSinMinimo }} meses consecutivos sin alcanzar mínimo (límite: {{ $config->meses_consecutivos_despido }})</li>
                        @endif
                        @if($enRiesgoAlternos)
                            <li>• Llevas {{ $alternosSinMinimo }} meses alternos sin mínimo en {{ $config->periodo_meses_alternos }} meses (límite: {{ $config->meses_alternos_despido }})</li>
                        @endif
                    </ul>
                </div>
            @endif

            @if($historial->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">Sin historial disponible todavía.</p>
            @else
                <div class="grid grid-cols-12 gap-2">
                    @foreach($historial as $item)
                        @php
                            $fecha   = \Carbon\Carbon::create($item->año, $item->mes, 1);
                            $alcanza = $item->alcanzo_todos_minimos_obligatorios;
                        @endphp

                        <div class="text-center">
                            <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">
                                {{ $fecha->locale('es')->shortMonthName }}
                            </div>
                            <div class="w-full h-12 rounded flex items-center justify-center text-white font-bold {{ $alcanza ? 'bg-green-500' : 'bg-red-500' }}">
                                {{ $alcanza ? '✓' : '✗' }}
                            </div>
                            <div class="text-xs mt-1 text-gray-600 dark:text-gray-300">
                                {{ $fecha->year }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="grid grid-cols-2 gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Racha actual sin mínimo</div>
                    <div class="text-2xl font-bold {{ $consecutivosSinMinimo > 0 ? 'text-red-600' : 'text-green-600' }}">
                        {{ $consecutivosSinMinimo }} meses
                    </div>
                </div>
                @if($config)
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Meses sin mínimo (últimos 12 meses)</div>
                        <div class="text-2xl font-bold {{ $alternosSinMinimo > 0 ? 'text-orange-600' : 'text-green-600' }}">
                            {{ $alternosSinMinimo }} / {{ $config->meses_alternos_despido }}
                        </div>
                    </div>
                @endif
            </div>

        </div>
    </x-filament::section>
</x-filament-widgets::widget>
