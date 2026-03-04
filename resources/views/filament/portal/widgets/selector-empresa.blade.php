<div class="fi-wi-selector-empresa">
    @php
        $clienteActivo = $this->getClienteActivo();
        $clientes = $this->getClientes();
    @endphp

    @if($clienteActivo)
    <div class="flex items-center gap-3 rounded-xl border border-primary-200 dark:border-primary-800 bg-primary-50 dark:bg-primary-950 px-4 py-3 mb-4">
        
        {{-- Icono empresa activa --}}
        <div class="flex items-center justify-center w-9 h-9 rounded-full bg-primary-500 text-white font-bold text-base shrink-0">
            {{ strtoupper(substr($clienteActivo->razon_social ?? $clienteActivo->nombre ?? '?', 0, 1)) }}
        </div>

        {{-- Info empresa activa --}}
        <div class="flex-1 min-w-0">
            <div class="text-xs font-medium text-primary-600 dark:text-primary-400 uppercase tracking-wide">
                Empresa activa
            </div>
            <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                {{ $clienteActivo->razon_social ?? $clienteActivo->nombre . ' ' . $clienteActivo->apellidos }}
            </div>
        </div>

        {{-- Dropdown para cambiar --}}
        <div x-data="{ open: false }" class="relative shrink-0">
            <button
                @click="open = !open"
                class="flex items-center gap-1 text-xs text-primary-600 dark:text-primary-400 hover:text-primary-800 dark:hover:text-primary-200 font-medium transition-colors"
            >
                Cambiar
                <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            {{-- Listado de empresas --}}
            <div
                x-show="open"
                @click.outside="open = false"
                x-transition
                class="absolute right-0 top-7 z-50 min-w-[220px] rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg py-1"
            >
                @foreach($clientes as $cliente)
                    <button
                        wire:click="cambiar({{ $cliente->id }})"
                        @click="open = false"
                        class="w-full flex items-center gap-3 px-4 py-2.5 text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors
                            {{ $cliente->id === session('cliente_activo_id') ? 'bg-primary-50 dark:bg-primary-950' : '' }}"
                    >
                        <div class="flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold shrink-0
                            {{ $cliente->id === session('cliente_activo_id') ? 'bg-primary-500 text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-600 dark:text-gray-300' }}">
                            {{ strtoupper(substr($cliente->razon_social ?? $cliente->nombre ?? '?', 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                {{ $cliente->razon_social ?? $cliente->nombre . ' ' . $cliente->apellidos }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $cliente->dni_cif }}
                            </div>
                        </div>
                        @if($cliente->id === session('cliente_activo_id'))
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-primary-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        @endif
                    </button>
                @endforeach
            </div>
        </div>

    </div>
    @endif
</div>