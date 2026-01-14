@php
    use Illuminate\Support\Collection;

    $fmt = fn ($n) => number_format((float) $n, 2, ',', '.');

    $items = $items ?? [];
    $totales = $totales ?? [];

    // ✅ Usar el email que te pasa el Page; fallback por seguridad
    $emailDestino = $emailDestino
        ?? $lead?->cliente?->email_contacto
        ?? $lead?->email
        ?? $lead?->email_contacto
        ?? '—';

    $totalSin = (float) ($totales['total_hoy_sin_iva'] ?? 0);
    $totalCon = (float) ($totales['total_hoy_con_iva'] ?? 0);

    $listaUnicos = (array) ($totales['lista_unicos'] ?? []);
    $listaRec = (array) ($totales['lista_recurrentes'] ?? []);

    // ✅ Blindaje: si no nos pasan $servicios, no rompe el modal
    $servicios = $servicios ?? collect();
    if (! ($servicios instanceof Collection)) {
        $servicios = collect($servicios);
    }

    // Helper: etiqueta de descuento (solo texto)
    $dtoLabel = function (array $it, string $tipoServicio): ?string {
        $aplica = (bool) ($it['aplicar_descuento'] ?? false);
        if (! $aplica) return null;

        $tipo = $it['descuento_tipo'] ?? null;
        $valorRaw = $it['descuento_valor'] ?? null;
        $valorRawStr = is_null($valorRaw) ? '' : trim((string) $valorRaw);

        if (! filled($tipo) || $valorRawStr === '') {
            return null;
        }

        $meses = $it['descuento_duracion_meses'] ?? null;

        $base = match ($tipo) {
            'porcentaje'   => "Dto: -{$valorRawStr}%",
            'fijo'         => "Dto: -{$valorRawStr} €",
            'precio_final' => "Precio final: {$valorRawStr} €",
            default        => null,
        };

        if (! $base) return null;

        if ($tipoServicio === 'recurrente' && filled($meses)) {
            $base .= " · {$meses} meses";
        }

        return $base;
    };

    /*
    |--------------------------------------------------------------------------
    | ✅ LÓGICA REAL: proyecto vs prorrata (3 estados)
    |--------------------------------------------------------------------------
    | - Hay proyecto: cualquier línea con proyecto (editable o no)
    | - Bloquea prorrata: SOLO si
    |     a) el servicio NO editable requiere proyecto_activacion
    |     b) o el comercial marca bloquea_recurrente (checkbox)
    */
    $hayProyecto = false;
    $hayBloqueoProrrata = false;

    $serviciosProyecto = [];      // para info
    $serviciosBloqueantes = [];   // los que causan bloqueo real (checkbox o no editable con proyecto_activacion)

    // ✅ Buscar el recurrente BASE (tarifa principal) para “Afecta a:”
    $recurrenteBaseNombre = null;
    $recurrenteBaseId = null;

    $primerRecurrenteNombre = null;
    $primerRecurrenteId = null;

    foreach ($items as $it) {
        $svc = null;
        if (!empty($it['servicio_id'])) {
            $svc = $servicios->firstWhere('id', (int) $it['servicio_id']);
        }

        $tipo = (string) ($it['tipo'] ?? ($svc?->tipo?->value ?? 'unico'));

        $nombre = $svc?->nombre;
        if (!empty($it['es_editable'])) {
            $nombre = $it['nombre_personalizado'] ?: $nombre;
        }
        $nombre = $nombre ?: '—';

        $editable = (bool) ($it['es_editable'] ?? false);

        // Proyecto (solo para saber si “hay proyecto”)
        $tieneProyectoLinea = $editable
            ? (bool) ($it['requiere_proyecto'] ?? false)
            : (bool) ($it['servicio_requiere_proyecto'] ?? ($svc?->requiere_proyecto_activacion ?? false));

        if ($tieneProyectoLinea) {
            $hayProyecto = true;
            $serviciosProyecto[] = $nombre;
        }

        // Bloqueo por checkbox
        $bloqueaRec = (bool) ($it['bloquea_recurrente'] ?? ($svc?->bloquea_recurrente ?? false));
        if ($bloqueaRec) {
            $hayBloqueoProrrata = true;
            $serviciosBloqueantes[] = $nombre;
        }

        // Bloqueo por proyecto_activacion SOLO cuando NO es editable
        $proyectoBloqueanteNoEditable = (! $editable) && (bool) (
            $it['servicio_requiere_proyecto']
            ?? ($svc?->requiere_proyecto_activacion ?? false)
        );

        if ($proyectoBloqueanteNoEditable) {
            $hayBloqueoProrrata = true;
            $serviciosBloqueantes[] = $nombre;
        }

        // Detectar base recurrente (tarifa principal)
        if ($tipo === 'recurrente') {
            if ($primerRecurrenteId === null && $svc) {
                $primerRecurrenteId = (int) $svc->id;
                $primerRecurrenteNombre = $svc->nombre;
            }

            $esBase = (bool) ($svc?->es_tarifa_principal ?? false);
            if ($esBase && $svc) {
                $recurrenteBaseId = (int) $svc->id;
                $recurrenteBaseNombre = $svc->nombre;
            }
        }
    }

    // fallback si no hay “base” marcada
    if ($recurrenteBaseNombre === null) {
        $recurrenteBaseNombre = $primerRecurrenteNombre;
        $recurrenteBaseId = $primerRecurrenteId;
    }

    $serviciosProyecto = array_values(array_unique(array_filter($serviciosProyecto)));
    $serviciosBloqueantes = array_values(array_unique(array_filter($serviciosBloqueantes)));

    // Estado superior (3 opciones)
    $estadoProrrata = 'prorrata_activa';
    if ($hayProyecto && $hayBloqueoProrrata) {
        $estadoProrrata = 'proyecto_sin_prorrata';
    } elseif ($hayProyecto && ! $hayBloqueoProrrata) {
        $estadoProrrata = 'proyecto_prorrata_activa';
    } elseif (! $hayProyecto && $hayBloqueoProrrata) {
        $estadoProrrata = 'sin_prorrata';
    }
@endphp

<div class="space-y-5">
    {{-- CABECERA / CONTEXTO --}}
    <div class="relative overflow-hidden rounded-2xl border border-sky-200/70 bg-white p-5 shadow-sm
                dark:border-sky-900/40 dark:bg-gray-950/35">
        <div class="absolute -right-28 -top-28 h-64 w-64 rounded-full bg-sky-500/10 blur-2xl dark:bg-sky-400/10"></div>
        <div class="absolute -left-28 -bottom-28 h-64 w-64 rounded-full bg-emerald-500/10 blur-2xl dark:bg-emerald-400/10"></div>

        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            {{-- Lead --}}
            <div class="min-w-0">
                <div class="text-[11px] font-bold uppercase tracking-wider text-sky-700/70 dark:text-sky-300/70">
                    Lead
                </div>

                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <div class="truncate text-lg font-black text-gray-900 dark:text-white">
                        {{ $lead?->nombre ?? '—' }}
                    </div>

                    @if($estadoProrrata === 'proyecto_sin_prorrata')
                        <span class="inline-flex items-center gap-2 rounded-full bg-indigo-600/10 px-2.5 py-1 text-[11px] font-bold text-indigo-700 ring-1 ring-indigo-600/20
                                     dark:bg-indigo-500/10 dark:text-indigo-200 dark:ring-indigo-500/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                            Hay proyecto → sin prorrata
                        </span>
                    @elseif($estadoProrrata === 'proyecto_prorrata_activa')
                        <span class="inline-flex items-center gap-2 rounded-full bg-indigo-600/10 px-2.5 py-1 text-[11px] font-bold text-indigo-700 ring-1 ring-indigo-600/20
                                     dark:bg-indigo-500/10 dark:text-indigo-200 dark:ring-indigo-500/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                            Hay proyecto → prorrata activa
                        </span>
                    @elseif($estadoProrrata === 'sin_prorrata')
                        <span class="inline-flex items-center gap-2 rounded-full bg-rose-600/10 px-2.5 py-1 text-[11px] font-bold text-rose-700 ring-1 ring-rose-600/20
                                     dark:bg-rose-500/10 dark:text-rose-200 dark:ring-rose-500/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                            Sin prorrata
                        </span>
                    @else
                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-600/10 px-2.5 py-1 text-[11px] font-bold text-emerald-700 ring-1 ring-emerald-600/20
                                     dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Prorrata activa
                        </span>
                    @endif
                </div>

                @if($emailDestino && $emailDestino !== '—')
                    <div class="mt-2 inline-flex items-center gap-2 rounded-lg bg-amber-500/10 px-3 py-2 text-xs font-bold text-amber-700 ring-1 ring-amber-500/20
                                dark:bg-amber-400/10 dark:text-amber-200 dark:ring-amber-400/20">
                        <span class="text-[14px] font-black uppercase tracking-wider">
                            Se enviará el contrato para firma a:
                        </span>
                        <span class="text-base font-extrabold normal-case tracking-normal">
                            {{ $emailDestino }}
                        </span>
                    </div>
                @else
                    <div class="mt-2 text-xs font-bold text-amber-700/80 dark:text-amber-200/80">
                        No hay email destino configurado para este lead/cliente.
                    </div>
                @endif

                <div class="mt-2 max-w-2xl text-sm text-gray-600 dark:text-gray-300">
                    Vas a enviar un enlace de firma con el desglose de servicios y totales de hoy.
                </div>
            </div>

            {{-- TOTAL CON IVA --}}
            <div class="shrink-0">
                <div class="rounded-2xl border border-emerald-200/70 bg-gradient-to-br from-emerald-500/10 to-sky-500/5 px-5 py-4 shadow-sm
                            dark:border-emerald-900/45 dark:from-emerald-500/15 dark:to-sky-500/10">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-700/70 dark:text-emerald-300/70">
                        Total hoy (con IVA)
                    </div>
                    <div class="mt-1 text-3xl font-black text-gray-900 dark:text-white leading-none">
                        {{ $fmt($totalCon) }} €
                    </div>
                    <div class="mt-2 inline-flex items-center rounded-lg bg-emerald-600/10 px-2 py-1 text-[11px] font-bold text-emerald-700 ring-1 ring-emerald-600/20
                                dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/20">
                        IVA 21% (visual)
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- TOTALES --}}
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-sky-200/70 bg-white p-4 shadow-sm
                    dark:border-sky-900/40 dark:bg-gray-950/35">
            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Total hoy (sin IVA)
            </div>
            <div class="mt-1 flex items-end justify-between gap-3">
                <div class="text-3xl font-black text-gray-900 dark:text-white leading-none">
                    {{ $fmt($totalSin) }} €
                </div>
                <div class="rounded-xl bg-sky-600/10 px-3 py-2 text-xs font-bold text-sky-700 ring-1 ring-sky-600/20
                            dark:bg-sky-500/12 dark:text-sky-200 dark:ring-sky-500/20">
                    Base
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-emerald-200/70 bg-white p-4 shadow-sm
                    dark:border-emerald-900/45 dark:bg-gray-950/35">
            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                Total hoy (con IVA)
            </div>
            <div class="mt-1 flex items-end justify-between gap-3">
                <div class="text-3xl font-black text-emerald-600 dark:text-emerald-400 leading-none">
                    {{ $fmt($totalCon) }} €
                </div>
                <div class="rounded-xl bg-emerald-600/10 px-3 py-2 text-xs font-bold text-emerald-700 ring-1 ring-emerald-600/20
                            dark:bg-emerald-500/12 dark:text-emerald-200 dark:ring-emerald-500/20">
                    IVA 21% (visual)
                </div>
            </div>
        </div>
    </div>

    {{-- RESUMEN SERVICIOS --}}
    <div class="rounded-2xl border border-sky-200/70 bg-white shadow-sm
                dark:border-sky-900/40 dark:bg-gray-950/35">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-sky-200/70 px-5 py-4
                    dark:border-gray-800">
            <div>
                <div class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                    Resumen de servicios
                </div>
                <div class="mt-0.5 text-sm font-extrabold text-gray-900 dark:text-white">
                    Revisa nombre, unidades y total final por línea
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if(count($listaUnicos))
                    <span class="rounded-full bg-emerald-600/12 px-2.5 py-1 text-[11px] font-bold text-emerald-700 ring-1 ring-emerald-600/18
                                 dark:bg-emerald-500/12 dark:text-emerald-200 dark:ring-emerald-500/18">
                        Únicos: {{ count($listaUnicos) }}
                    </span>
                @endif

                @if(count($listaRec))
                    <span class="rounded-full bg-sky-600/12 px-2.5 py-1 text-[11px] font-bold text-sky-700 ring-1 ring-sky-600/18
                                 dark:bg-sky-500/12 dark:text-sky-200 dark:ring-sky-500/18">
                        Recurrentes: {{ count($listaRec) }}
                    </span>
                @endif
            </div>
        </div>

        <div class="px-5 py-4">
            <div class="space-y-3">
                @forelse($items as $it)
                    @php
                        $svc = null;
                        if (!empty($it['servicio_id'])) {
                            $svc = $servicios->firstWhere('id', (int) $it['servicio_id']);
                        }

                        $tipo = (string) ($it['tipo'] ?? ($svc?->tipo?->value ?? 'unico'));

                        $nombre = $svc?->nombre;
                        if (!empty($it['es_editable'])) {
                            $nombre = $it['nombre_personalizado'] ?: $nombre;
                        }

                        $cant = (int) ($it['cantidad'] ?? 1);
                        $precioFinalUnit = (float) ($it['precio_final_unit'] ?? 0);
                        $subtFinal = (float) ($it['subtotal_final'] ?? 0);

                        $editable = (bool) ($it['es_editable'] ?? false);
                        $proyecto = $editable
                            ? (bool) ($it['requiere_proyecto'] ?? false)
                            : (bool) ($it['servicio_requiere_proyecto'] ?? ($svc?->requiere_proyecto_activacion ?? false));

                        $dtoTxt = $dtoLabel($it, $tipo);

                        $esBase = (bool) ($svc?->es_tarifa_principal ?? false);

                        $bloqueaRecurrente = (bool) ($it['bloquea_recurrente'] ?? ($svc?->bloquea_recurrente ?? false));
                    @endphp

                    <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3
                                dark:border-gray-800 dark:bg-gray-950/25">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="truncate text-sm font-extrabold text-gray-900 dark:text-white">
                                        {{ $nombre ?: '—' }}
                                    </div>

                                    {{-- Chips --}}
                                    @if($tipo === 'recurrente')
                                        <span class="rounded-full bg-sky-600/12 px-2 py-0.5 text-[10px] font-bold text-sky-700 ring-1 ring-sky-600/18
                                                     dark:bg-sky-500/12 dark:text-sky-200 dark:ring-sky-500/18">
                                            Recurrente
                                        </span>
                                    @else
                                        <span class="rounded-full bg-emerald-600/12 px-2 py-0.5 text-[10px] font-bold text-emerald-700 ring-1 ring-emerald-600/18
                                                     dark:bg-emerald-500/12 dark:text-emerald-200 dark:ring-emerald-500/18">
                                            Único
                                        </span>
                                    @endif

                                    @if($editable)
                                        <span class="rounded-full bg-purple-600/12 px-2 py-0.5 text-[10px] font-bold text-purple-700 ring-1 ring-purple-600/18
                                                     dark:bg-purple-500/12 dark:text-purple-200 dark:ring-purple-500/18">
                                            Editable
                                        </span>
                                    @endif

                                    @if($proyecto)
                                        <span class="rounded-full bg-indigo-600/12 px-2 py-0.5 text-[10px] font-bold text-indigo-700 ring-1 ring-indigo-600/18
                                                     dark:bg-indigo-500/12 dark:text-indigo-200 dark:ring-indigo-500/18">
                                            Proyecto
                                        </span>
                                    @endif

                                    {{-- ✅ Base (tarifa principal) --}}
                                    @if($tipo === 'recurrente' && $esBase)
                                        <span class="rounded-full bg-amber-600/15 px-2 py-0.5 text-[10px] font-bold text-amber-700 ring-1 ring-amber-600/25
                                                     dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/25">
                                            Base
                                        </span>
                                    @endif

                                    {{-- ✅ Bloquea Rec. (si aplica) --}}
                                    @if($bloqueaRecurrente)
                                        <span class="rounded-full bg-rose-600/15 px-2 py-0.5 text-[10px] font-bold text-rose-700 ring-1 ring-rose-600/25
                                                     dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/25">
                                            Bloquea Rec.
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                                    Cant: <span class="font-bold">{{ $cant }}</span>
                                    · Precio final (ud.): <span class="font-bold text-sky-700 dark:text-sky-200">{{ $fmt($precioFinalUnit) }} €</span>
                                    @if($dtoTxt)
                                        · <span class="font-bold">{{ $dtoTxt }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="shrink-0 text-right">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Total línea
                                </div>
                                <div class="mt-0.5 text-lg font-black text-emerald-600 dark:text-emerald-400 leading-none">
                                    {{ $fmt($subtFinal) }} €
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600
                                dark:border-gray-800 dark:bg-gray-950/25 dark:text-gray-300">
                        No hay servicios seleccionados.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ✅ NUEVA SECCIÓN: BLOQUEO POR PROYECTO/BLOQUEA REC (antes de la nota final) --}}
    @if($hayBloqueoProrrata)
        <div class="rounded-2xl border border-rose-200 bg-rose-50/70 px-4 py-3
                    dark:border-rose-900/60 dark:bg-rose-950/25">
            <div class="text-sm font-extrabold text-rose-900 dark:text-rose-200">
                Bloqueo de servicios recurrentes
            </div>

            <div class="mt-1 text-sm text-rose-900/80 dark:text-rose-200/80">
                Se bloquearán los servicios recurrentes hasta que el proyecto de este servicio esté finalizado.
            </div>

            @if($recurrenteBaseNombre)
                <div class="mt-3 inline-flex flex-wrap items-center gap-2 rounded-lg border border-rose-400/30 bg-rose-50/60 px-3 py-2
                            dark:border-rose-500/25 dark:bg-gray-950/30">
                    <span class="text-xs font-bold text-rose-700 dark:text-rose-200">
                        Afecta a:
                    </span>

                    <span class="rounded-full bg-amber-600/15 px-2 py-0.5 text-[10px] font-bold text-amber-700 ring-1 ring-amber-600/25
                                 dark:bg-amber-500/15 dark:text-amber-300 dark:ring-amber-500/25">
                        Base
                    </span>

                    <span class="text-xs font-extrabold text-rose-700 dark:text-rose-200">
                        {{ $recurrenteBaseNombre }}
                    </span>
                </div>
            @endif

            @if(!empty($serviciosBloqueantes))
                <div class="mt-2 text-[11px] font-bold uppercase tracking-wider text-rose-700/70 dark:text-rose-300/70">
                    Motivo
                </div>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach($serviciosBloqueantes as $n)
                        <span class="rounded-full bg-rose-600/15 px-2 py-0.5 text-[10px] font-bold text-rose-700 ring-1 ring-rose-600/25
                                     dark:bg-rose-500/15 dark:text-rose-300 dark:ring-rose-500/25">
                            {{ $n }}
                        </span>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Nota final --}}
    <div class="rounded-2xl border border-gray-200 bg-white/70 px-4 py-3 text-xs text-gray-600
                dark:border-gray-800 dark:bg-gray-950/25 dark:text-gray-300">
        Si detectas un error, cancela y corrige antes de enviar. Una vez enviado, el cliente recibirá el enlace de firma.
    </div>
</div>
