<x-filament-panels::page>

    @if (!$contratoBase)

        {{-- SIN CONTRATO --}}
        <div class="text-center py-16 bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl">
            <svg class="mx-auto mb-4 w-12 h-12 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-2">
                Aún no tienes un contrato de incentivos
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Cuando administración te envíe el contrato aparecerá aquí para que puedas revisarlo y firmarlo.
            </p>
        </div>

    @else

        {{-- CONTRATO BASE --}}
        @include('filament.pages.partials.card-contrato', [
            'contrato' => $contratoBase,
            'esBase'   => true,
        ])

        {{-- ANEXOS --}}
        @if ($anexos->count())
            <div class="mt-8">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">
                    Anexos al contrato ({{ $anexos->count() }})
                </h2>
                <div class="space-y-4">
                    @foreach ($anexos as $anexo)
                        @include('filament.pages.partials.card-contrato', [
                            'contrato' => $anexo,
                            'esBase'   => false,
                        ])
                    @endforeach
                </div>
            </div>
        @endif

    @endif

</x-filament-panels::page>
