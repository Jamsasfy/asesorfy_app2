{{-- resources/views/filament/resources/leads/partials/servicios-table.blade.php --}}

@php
    use App\Models\Servicio;

    // ⚠️ Mantenemos tu forma de cargar servicios
    $servicios = Servicio::query()->orderBy('nombre')->get();

    // Servicios ya seleccionados (para evitar duplicados)
    $selectedIds = collect($items ?? [])
        ->pluck('servicio_id')
        ->filter()
        ->values()
        ->all();

    // Hay alguna tarifa principal recurrente seleccionada (para ocultar otras)
    $hasBaseRecurrenteSelected = $servicios
        ->whereIn('id', $selectedIds)
        ->filter(fn ($s) => ($s->tipo?->value ?? $s->tipo) === 'recurrente' && (bool) ($s->es_tarifa_principal ?? false))
        ->isNotEmpty();

    $baseRecurrenteSelectedIds = $servicios
        ->whereIn('id', $selectedIds)
        ->filter(fn ($s) => ($s->tipo?->value ?? $s->tipo) === 'recurrente' && (bool) ($s->es_tarifa_principal ?? false))
        ->pluck('id')
        ->all();

    // Helper formato
    $fmt = fn ($n) => number_format((float) $n, 2, ',', '.');
@endphp

<div>
    {{-- CABECERA --}}
    <div
        class="grid grid-cols-16 gap-4 border-b border-sky-200/70 bg-sky-100/70 px-4 py-3
               text-xs font-bold uppercase tracking-wider text-gray-600
               dark:border-gray-800 dark:bg-gray-950 dark:text-gray-400"
    >
        <div class="col-span-7">Concepto / Servicio</div>
        <div class="col-span-2 text-right">Precio base</div>
        <div class="col-span-2 text-right">Precio final</div>
        <div class="col-span-2 text-center">Cant.</div>
        <div class="col-span-1 text-right">Subt. base</div>
        <div class="col-span-2 text-right">Subt. final</div>
    </div>

    {{-- CUERPO --}}
    <div class="divide-y divide-gray-200 dark:divide-gray-800">
        @foreach(($items ?? []) as $i => $item)
            @php
                $rowIsEven = $i % 2 === 1;

                $servicioId = $item['servicio_id'] ?? null;
                $svc = $servicioId ? $servicios->firstWhere('id', $servicioId) : null;

                $tipo = $svc?->tipo?->value ?? ($item['tipo'] ?? 'unico');
                $esEditable = (bool) ($svc?->es_editable ?? ($item['es_editable'] ?? false));
                $requiereProyectoServicio = (bool) ($svc?->requiere_proyecto_activacion ?? false);
                $esTarifaPrincipal = (bool) ($svc?->es_tarifa_principal ?? false);

                // ✅ bloquea recurrente (desde servicio o item)
                $bloqueaRecurrente = (bool) (
                    ($item['bloquea_recurrente'] ?? false)
                    || ($svc?->bloquea_recurrente ?? false)
                );

                $cantidad = (float) ($item['cantidad'] ?? 1);

                // ✅ Base unitario (viene del item porque lo rellena Livewire)
                $precioBase = (float) ($item['precio_base'] ?? ($svc?->precio_base ?? 0));

                // ✅ FINAL unitario y subtotales: vienen DEL COMPONENTE (ya incluyen descuento aplicado)
                $precioFinalUnit = (float) ($item['precio_final_unit'] ?? $precioBase);
                $subtBase = (float) ($item['subtotal_base'] ?? max(0, $precioBase * max(1, $cantidad)));
                $subtFinal = (float) ($item['subtotal_final'] ?? max(0, $precioFinalUnit * max(1, $cantidad)));

                $aplicarDescuento = (bool) ($item['aplicar_descuento'] ?? false);
                $descuentoTipo = $item['descuento_tipo'] ?? null;
                $descuentoValor = $item['descuento_valor'] ?? null;
                $descuentoDuracion = $item['descuento_duracion_meses'] ?? null;

                // Para filtrar opciones: ids seleccionados en otras filas
                $selectedOtherIds = collect($selectedIds)->filter(fn ($id) => (int) $id !== (int) $servicioId)->all();
            @endphp

            <div
                class="px-4 py-3
                       {{ $rowIsEven ? 'bg-sky-100/40' : 'bg-transparent' }}
                       hover:bg-sky-200/40
                       dark:bg-transparent dark:hover:bg-white/5"
                wire:key="row-{{ $i }}"
            >
                {{-- FILA PRINCIPAL --}}
                <div class="grid grid-cols-16 items-center gap-4">
                    {{-- 1) SERVICIO --}}
                    <div class="col-span-7">
                        <div class="relative">
                            <select
                                wire:model.live="items.{{ $i }}.servicio_id"
                                class="w-full cursor-pointer rounded-lg border border-gray-200 bg-white px-3 py-2
                                       text-sm font-extrabold text-gray-900
                                       focus:border-sky-500/60 focus:outline-none focus:ring-2 focus:ring-sky-500/15
                                       dark:border-gray-800 dark:bg-transparent dark:text-white dark:focus:ring-sky-500/30"
                            >
                                <option value="" class="bg-white text-gray-500 dark:bg-gray-950 dark:text-gray-500">
                                    Seleccionar...
                                </option>

                                @foreach($servicios as $s)
                                    @php
                                        $sId = (int) $s->id;
                                        $sTipo = $s->tipo?->value ?? $s->tipo;
                                        $sIsBaseRec = $sTipo === 'recurrente' && (bool) ($s->es_tarifa_principal ?? false);

                                        // 1) Nunca permitir duplicados (si está en otra fila)
                                        $isDuplicate = in_array($sId, $selectedOtherIds, true);

                                        // 2) Si ya hay una tarifa principal recurrente seleccionada, ocultar las otras
                                        //    (pero permitir la que ya está seleccionada en ESTA fila)
                                        $hideOtherBaseRec = $hasBaseRecurrenteSelected && $sIsBaseRec && !in_array($sId, $baseRecurrenteSelectedIds, true);

                                        $shouldHide = $isDuplicate || $hideOtherBaseRec;
                                    @endphp

                                    @if(! $shouldHide || $sId === (int) $servicioId)
                                        <option value="{{ $s->id }}" class="bg-white text-gray-900 dark:bg-gray-950 dark:text-white">
                                            {{ $s->nombre }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>

                          {{-- Chip tipo --}}
                            @if($svc)
                                @php
                                    $chip = ($tipo === 'recurrente')
                                        ? 'bg-sky-600/15 text-sky-700 ring-1 ring-sky-600/25 dark:bg-sky-500/15 dark:text-sky-300 dark:ring-sky-500/25'
                                        : 'bg-emerald-600/15 text-emerald-700 ring-1 ring-emerald-600/25 dark:bg-emerald-500/15 dark:text-emerald-300 dark:ring-emerald-500/25';

                                    // ✅ AQUÍ ESTÁ EL CAMBIO
                                    // Si el comercial marca el checkbox => badge ON aunque en el Servicio esté false
                                    // Si el Servicio viene con bloquea_recurrente=true => badge ON aunque el checkbox esté sin marcar
                                    $bloqueaRecurrente = (bool) (
                                        ($item['bloquea_recurrente'] ?? false)
                                        || ($svc?->bloquea_recurrente ?? false)
                                    );
                                @endphp

                                <div class="mt-2 inline-flex items-center gap-2">
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $chip }}">
                                        {{ $tipo === 'recurrente' ? 'Recurrente' : 'Único' }}
                                    </span>

                                    @if($esEditable)
                                        <span class="rounded-full bg-purple-600/15 px-2 py-0.5 text-[10px] font-bold text-purple-700 ring-1 ring-purple-600/25
                                                    dark:bg-purple-500/15 dark:text-purple-300 dark:ring-purple-500/25">
                                            Editable
                                        </span>
                                    @endif

                                    @if($requiereProyectoServicio || ($item['requiere_proyecto'] ?? false))
                                        <span class="rounded-full bg-indigo-600/15 px-2 py-0.5 text-[10px] font-bold text-indigo-700 ring-1 ring-indigo-600/25
                                                    dark:bg-indigo-500/15 dark:text-indigo-300 dark:ring-indigo-500/25">
                                            Proyecto
                                        </span>
                                    @endif

                                   @if($bloqueaRecurrente)
                                        <span class="rounded-full bg-rose-600/15 px-2 py-0.5 text-[10px] font-bold text-rose-700 ring-1 ring-rose-600/25
                                                    dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/25">
                                            Bloquea Rec.
                                        </span>
                                    @endif

                                    @if($esTarifaPrincipal)
                                        <span class="rounded-full bg-amber-600/15 px-2 py-0.5 text-[10px] font-bold text-amber-700 ring-1 ring-amber-600/25
                                                    dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/25">
                                            Base
                                        </span>
                                    @endif
                                </div>
                            @endif

                        </div>
                    </div>

                    {{-- 2) PRECIO BASE --}}
                    <div class="col-span-2 text-right">
                        @if($esEditable)
                            <input
                                type="text"
                                inputmode="decimal"
                                autocomplete="off"
                                wire:model.live.debounce.500ms="items.{{ $i }}.precio_base"
                                class="w-full rounded-lg border border-sky-300/60 bg-white px-3 py-2 text-right text-sm font-bold text-gray-900
                                       focus:border-sky-500/60 focus:outline-none focus:ring-2 focus:ring-sky-500/15
                                       dark:border-sky-500/25 dark:bg-gray-950/30 dark:text-white"
                                placeholder="0,00"
                            />
                        @else
                            <input
                                type="text"
                                inputmode="decimal"
                                autocomplete="off"
                                readonly
                                value="{{ $fmt($precioBase) }}"
                                class="w-full border-none bg-transparent p-0 text-right text-sm font-bold text-gray-900
                                       dark:text-gray-200"
                            />
                        @endif
                    </div>

                    {{-- 3) PRECIO FINAL --}}
                    <div class="col-span-2 text-right">
                        <span class="text-sm font-extrabold text-sky-600 dark:text-sky-400">
                            {{ $fmt($precioFinalUnit) }} €
                        </span>
                    </div>

                    {{-- 4) CANTIDAD --}}
                    <div class="col-span-2">
                        <input
                            type="number"
                            min="1"
                            wire:model.live.debounce.500ms="items.{{ $i }}.cantidad"
                            class="w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-center text-sm font-extrabold text-sky-600
                                   focus:border-sky-500/60 focus:outline-none focus:ring-2 focus:ring-sky-500/15
                                   dark:border-gray-800 dark:bg-gray-950/30 dark:text-sky-300"
                        />
                    </div>

                    {{-- 5) SUBT BASE --}}
                    <div class="col-span-1 text-right">
                        <span class="text-sm font-bold text-gray-900 dark:text-gray-300">
                            {{ $fmt($subtBase) }}
                        </span>
                    </div>

                    {{-- 6) SUBT FINAL + BORRAR --}}
                    <div class="col-span-2 flex items-center justify-end gap-3">
                        <span class="text-sm font-extrabold text-emerald-600 dark:text-emerald-400">
                            {{ $fmt($subtFinal) }} €
                        </span>

                        <button
                            type="button"
                            wire:click="removeItem({{ $i }})"
                            class="text-gray-400 transition hover:text-red-500"
                            title="Eliminar"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                                <path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- FILA EXTRA: EDITABLE + OPCIONES RECURRENTES + DESCUENTO --}}
                <div class="mt-3 grid grid-cols-16 gap-4">
                    {{-- BLOQUE IZQ --}}
                    <div class="col-span-7">
                        @if($esEditable)
                        @php
                            // ✅ Si el servicio ya trae bloquea_recurrente o el comercial lo marca
                            $bloqueaRecurrente = (bool) ($item['bloquea_recurrente'] ?? false) || (bool) ($svc?->bloquea_recurrente ?? false);
                        @endphp

                        <div class="flex flex-wrap items-center gap-3">
                            {{-- ✅ CHECK: BLOQUEA RECURRENTE (lo que marca el comercial) --}}
                            <label
                                class="inline-flex items-center gap-2 rounded-lg border border-rose-400/30 bg-rose-50/60 px-3 py-2
                                    dark:border-rose-500/25 dark:bg-gray-950/30"
                                title="Si está activo, NO se cobra prorrata hoy (0€) y el recurrente se activará tras onboarding."
                            >
                                <input
                                    type="checkbox"
                                    wire:model.live="items.{{ $i }}.bloquea_recurrente"
                                    class="h-4 w-4 rounded border-gray-300 text-rose-600 focus:ring-rose-500/30"
                                />
                                <span class="text-xs font-bold text-rose-700 dark:text-rose-200">
                                    Bloquea recurrente
                                </span>
                                <span class="text-[10px] font-bold text-rose-700/60 dark:text-rose-200/60">
                                    (prorrata 0)
                                </span>
                            </label>

                            <div class="flex-1 min-w-[260px]">
                                <div class="mb-1 text-[10px] font-bold uppercase tracking-wider text-sky-700/70 dark:text-sky-300/70">
                                    Nombre del servicio (editable)
                                </div>

                                <input
                                    type="text"
                                    wire:model.live.debounce.500ms="items.{{ $i }}.nombre_personalizado"
                                    class="w-full rounded-lg border border-sky-400/40 bg-white px-3 py-2
                                        text-sm font-semibold text-gray-900 placeholder:text-gray-400
                                        focus:border-sky-500/60 focus:outline-none focus:ring-2 focus:ring-sky-500/15
                                        dark:border-sky-500/25 dark:bg-gray-950/30 dark:text-gray-100 dark:placeholder:text-gray-500"
                                    placeholder="{{ $svc?->nombre ?? 'Nombre...' }}"
                                />
                            </div>
                        </div>

                        {{-- ✅ AVISO debajo (mismo look & feel que el badge de bloquea recurrente) --}}
                        @if($bloqueaRecurrente)
                            <div class="mt-2 rounded-lg border border-rose-400/30 bg-rose-50/60 px-3 py-2
                                        dark:border-rose-500/25 dark:bg-gray-950/30">
                                <div class="text-xs font-bold text-rose-700 dark:text-rose-200">
                                    Se bloqueará los servicios recurrentes de esta venta hasta que el proyecto de este servicio este finalizado.
                                </div>
                            </div>
                        @endif
                    @endif

                    </div>

                    {{-- BLOQUE DERECHA --}}
                    <div class="col-span-9">
                        <div class="flex flex-wrap items-end justify-end gap-3">

                            {{-- ✅ 1) SELECTOR COBRO PRIMER MES (Solo Recurrente) --}}
                            @if($tipo === 'recurrente' && $svc)
                                <div class="flex items-center gap-2">
                                    <div class="relative">
                                        <select
                                            wire:model.live="items.{{ $i }}.cobro_primer_mes"
                                            class="appearance-none cursor-pointer rounded-lg border border-slate-300 bg-white py-1.5 pl-3 pr-8 text-xs font-bold text-slate-700 shadow-sm
                                                focus:border-sky-500 focus:outline-none focus:ring-1 focus:ring-sky-500
                                                dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200"
                                            title="Cómo facturar el primer mes"
                                        >
                                            <option value="prorrata">📅 Prorrata</option>
                                            <option value="completo">🌕 Mes completo</option>
                                            <option value="gratis">🎁 Primer mes gratis (0€)</option>
                                        </select>
                                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-500">
                                            <svg class="h-3 w-3 fill-current" viewBox="0 0 20 20"><path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/></svg>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- 2) DESCUENTO --}}
                            <div class="flex flex-col items-start h-[38px] justify-center">
                                <label
                                    class="inline-flex h-full items-center gap-2 rounded-lg border border-sky-400/35 bg-sky-50/60 px-3
                                           dark:border-sky-500/20 dark:bg-gray-950/20"
                                >
                                    <input
                                        type="checkbox"
                                        wire:model.live="items.{{ $i }}.aplicar_descuento"
                                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500/30"
                                    />
                                    <span class="text-xs font-bold text-sky-700 dark:text-sky-200">Descuento</span>
                                </label>
                            </div>

                            @if($aplicarDescuento)
                                <div class="flex items-center gap-2">
                                    <select
                                        wire:model.live="items.{{ $i }}.descuento_tipo"
                                        class="h-[38px] min-w-[140px] rounded-lg border border-sky-400/40 bg-white px-3 py-1
                                               text-sm font-semibold text-gray-900
                                               focus:border-sky-500/60 focus:outline-none focus:ring-2 focus:ring-sky-500/15
                                               dark:border-sky-500/25 dark:bg-gray-950/30 dark:text-gray-100"
                                    >
                                        <option value="">Sin dto</option>

                                        @if($tipo === 'recurrente')
                                            <option value="porcentaje">Porcentaje (%)</option>
                                        @else
                                            <option value="porcentaje">Porcentaje (%)</option>
                                            <option value="fijo">Cantidad fija (€)</option>
                                            <option value="precio_final">Precio final (€)</option>
                                        @endif
                                    </select>

                                    <input
                                        type="text"
                                        inputmode="decimal"
                                        autocomplete="off"
                                        wire:model.live.debounce.500ms="items.{{ $i }}.descuento_valor"
                                        class="h-[38px] w-[100px] rounded-lg border border-sky-400/40 bg-white px-3 py-1 text-sm font-bold text-gray-900
                                               focus:border-sky-500/60 focus:outline-none focus:ring-2 focus:ring-sky-500/15
                                               dark:border-sky-500/25 dark:bg-gray-950/30 dark:text-gray-100"
                                        placeholder="{{ ($descuentoTipo === 'porcentaje') ? 'Ej: 25' : 'Ej: 10,00' }}"
                                    />

                                    @if($tipo === 'recurrente')
                                        <input
                                            type="number"
                                            min="1"
                                            wire:model.live="items.{{ $i }}.descuento_duracion_meses"
                                            class="h-[38px] w-[80px] rounded-lg border border-sky-400/40 bg-white px-3 py-1 text-sm font-bold text-gray-900
                                                   focus:border-sky-500/60 focus:outline-none focus:ring-2 focus:ring-sky-500/15
                                                   dark:border-sky-500/25 dark:bg-gray-950/30 dark:text-gray-100"
                                            placeholder="Meses"
                                            title="Duración (periodos de facturación)"
                                        />
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- FOOTER --}}
    <div class="border-t border-sky-200/70 bg-sky-50/40 px-4 py-3 dark:border-gray-800 dark:bg-gray-950/30">
        <button
            type="button"
            wire:click="addItem"
            class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-sky-700 transition hover:text-sky-900
                   dark:text-gray-400 dark:hover:text-white"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4">
                <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
            </svg>
            Añadir línea
        </button>
    </div>
</div>
