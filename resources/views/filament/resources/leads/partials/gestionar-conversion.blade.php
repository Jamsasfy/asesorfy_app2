<x-filament-panels::page>
    <div class="flex flex-col gap-6">

        {{-- GRID SUPERIOR DE 5 COLUMNAS EXACTAS --}}
        <div class="grid grid-cols-5 gap-4">
            
            {{-- COL 1: SELECTOR CLIENTE (Arreglado color texto) --}}
            <div class="rounded-xl border border-gray-700 bg-gray-900 p-4 shadow-sm">
                <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-gray-400">
                    Cliente
                </label>
                <div class="relative">
                    <select wire:model.live="tipoClienteId" 
                            class="w-full appearance-none rounded-lg border border-gray-700 bg-gray-800 px-3 py-2 text-sm font-bold text-white focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        @foreach(\App\Models\TipoCliente::all() as $tipo)
                            <option value="{{ $tipo->id }}" class="bg-gray-800 text-white">
                                {{ $tipo->nombre }}
                            </option>
                        @endforeach
                    </select>
                    {{-- Flechita custom para asegurar que se ve --}}
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-gray-400">
                        <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/></svg>
                    </div>
                </div>
            </div>

            {{-- COLS 2-5: LOS KPIs --}}
            @include('filament.resources.leads.partials.kpi', ['totales' => $this->totales])
            
        </div>

        {{-- TABLA DE SERVICIOS --}}
        <div class="overflow-hidden rounded-xl border border-gray-700 bg-gray-900 shadow-xl">
            @include('filament.resources.leads.partials.servicios-table', ['items' => $items])
        </div>

        {{-- BOTÓN --}}
        <div class="flex justify-end">
            <x-filament::button wire:click="enviarPropuesta" size="lg" color="primary">
                Generar Propuesta
            </x-filament::button>
        </div>

    </div>
</x-filament-panels::page>