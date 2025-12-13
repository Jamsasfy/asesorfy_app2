<div>
    {{-- CABECERA --}}
    <div class="grid grid-cols-12 gap-4 border-b border-gray-800 bg-gray-950 px-4 py-3 text-xs font-bold uppercase tracking-wider text-gray-400">
        <div class="col-span-5">Concepto / Servicio</div>
        <div class="col-span-2 text-right">Precio</div>
        <div class="col-span-1 text-center">Cant.</div>
        <div class="col-span-2 text-right">Dto.</div>
        <div class="col-span-2 text-right">Total</div>
    </div>

    {{-- CUERPO --}}
    <div class="divide-y divide-gray-800">
        @foreach($items as $i => $item)
            <div class="group grid grid-cols-12 items-center gap-4 px-4 py-2 hover:bg-white/5" wire:key="row-{{ $i }}">
                
                {{-- 1. SELECTOR (5 cols) --}}
                <div class="col-span-5">
                    <select wire:model.live="items.{{ $i }}.servicio_id" 
                            class="w-full cursor-pointer border-none bg-transparent p-0 text-sm font-medium text-white placeholder-gray-500 focus:ring-0">
                        <option value="" class="bg-gray-900 text-gray-500">Seleccionar...</option>
                        @foreach(\App\Models\Servicio::all() as $svc)
                            <option value="{{ $svc->id }}" class="bg-gray-900 text-white">{{ $svc->nombre }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- 2. PRECIO (2 cols) --}}
                <div class="col-span-2">
                    <input type="number" step="0.01" wire:model.live.debounce.500ms="items.{{ $i }}.precio"
                           class="w-full border-none bg-transparent p-0 text-right text-sm text-gray-300 focus:ring-0" 
                           placeholder="0.00">
                </div>

                {{-- 3. CANTIDAD (1 col) --}}
                <div class="col-span-1">
                    <input type="number" min="1" wire:model.live.debounce.500ms="items.{{ $i }}.cantidad"
                           class="w-full rounded bg-gray-800 py-0.5 text-center text-sm font-bold text-blue-400 focus:ring-1 focus:ring-blue-500 border-none">
                </div>

                {{-- 4. DESCUENTO (2 cols) --}}
                <div class="col-span-2">
                    <input type="number" step="0.01" wire:model.live.debounce.500ms="items.{{ $i }}.descuento"
                           class="w-full border-none bg-transparent p-0 text-right text-sm text-red-400 focus:ring-0 placeholder-gray-700" 
                           placeholder="-">
                </div>

                {{-- 5. TOTAL (2 cols - incluye botón borrar) --}}
                <div class="col-span-2 flex items-center justify-end gap-3">
                    <span class="font-mono text-sm font-bold text-emerald-400">
                        {{ number_format(max(0, ((float)($items[$i]['precio']??0) * (float)($items[$i]['cantidad']??1)) - (float)($items[$i]['descuento']??0)), 2, ',', '.') }} €
                    </span>
                    
                    {{-- Botón Borrar (Invisible hasta hover) --}}
                    <button wire:click="removeItem({{ $i }})" class="text-gray-600 opacity-0 transition hover:text-red-500 group-hover:opacity-100">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4">
                            <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>
        @endforeach
    </div>

    {{-- FOOTER --}}
    <div class="border-t border-gray-800 bg-gray-950/30 px-4 py-2">
        <button wire:click="addItem" class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gray-500 transition hover:text-white">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" /></svg>
            Añadir Línea
        </button>
    </div>
</div>