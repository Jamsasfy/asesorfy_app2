<x-filament-panels::page>
    <div class="flex flex-col items-center justify-center min-h-[60vh] gap-8">

        <div class="text-center">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
                ¿Con qué empresa quieres acceder?
            </h2>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Tu usuario tiene acceso a varias empresas. Selecciona con cuál quieres trabajar ahora.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-4 w-full max-w-2xl sm:grid-cols-2">
            @foreach ($this->getClientes() as $cliente)
                <button
                    wire:click="seleccionar({{ (int) $cliente->id }})"
                    class="group flex flex-col gap-2 rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 text-left shadow-sm transition-all duration-200 hover:border-primary-500 hover:shadow-md hover:-translate-y-0.5 cursor-pointer"
                >
                    {{-- Icono / Inicial --}}
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300 font-bold text-lg">
                            {{ strtoupper(substr($cliente->razon_social ?? '?', 0, 1)) }}
                        </div>
                        <div>
                            <div class="font-semibold text-gray-900 dark:text-white group-hover:text-primary-600 transition-colors">
                                {{ $cliente->razon_social }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $cliente->dni_cif }}
                            </div>
                        </div>
                    </div>

                    {{-- Estado --}}
                    <div class="flex items-center gap-1.5 mt-1">
                        <span class="inline-block w-2 h-2 rounded-full {{ $cliente->estado->value === 'activo' ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                        <span class="text-xs text-gray-500 dark:text-gray-400 capitalize">
                            {{ $cliente->estado->value }}
                        </span>
                    </div>
                </button>
            @endforeach
        </div>

    </div>
</x-filament-panels::page>