{{-- resources/views/filament/resources/leads/partials/kpi.blade.php --}}

{{-- KPI 1: Pago Único --}}
<div class="relative overflow-hidden rounded-2xl border border-indigo-200 bg-white p-4 shadow-sm
            dark:border-indigo-900 dark:bg-gray-900">
    {{-- glow suave (más fuerte en normal) --}}
    <div class="pointer-events-none absolute -right-10 -top-10 h-36 w-36 rounded-full bg-indigo-400/20 blur-2xl
                dark:bg-indigo-400/10"></div>

    <p class="relative z-10 text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
        Pago Único
    </p>

    <p class="relative z-10 mt-1 text-2xl font-extrabold text-indigo-600 dark:text-indigo-400">
        {{ number_format($totales['unico'] ?? 0, 2, ',', '.') }} €
    </p>

    <div class="relative z-10 mt-2 inline-flex items-center rounded-md bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-800
                dark:bg-indigo-500/10 dark:text-indigo-300">
        Con IVA: {{ number_format($totales['unico_con_iva'] ?? 0, 2, ',', '.') }} €
    </div>

    @if(!empty($totales['lista_unicos'] ?? []))
        <div class="relative z-10 mt-3 space-y-1 text-[11px] text-indigo-800/80 dark:text-indigo-300/80">
            @foreach(($totales['lista_unicos'] ?? []) as $n)
                <div class="truncate">• {{ $n }}</div>
            @endforeach
        </div>
    @else
        <p class="relative z-10 mt-2 text-[10px] text-indigo-800/70 dark:text-indigo-300/70">
            Alta servicio
        </p>
    @endif
</div>

{{-- KPI 2: Mensual --}}
<div class="relative overflow-hidden rounded-2xl border border-sky-200 bg-white p-4 shadow-sm
            dark:border-sky-900 dark:bg-gray-900">
    <div class="pointer-events-none absolute -right-10 -top-10 h-36 w-36 rounded-full bg-sky-400/20 blur-2xl
                dark:bg-sky-400/10"></div>

    <p class="relative z-10 text-xs font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400">
        Mensual
    </p>

    <p class="relative z-10 mt-1 text-2xl font-extrabold text-sky-600 dark:text-sky-400">
        {{ number_format($totales['recurrente'] ?? 0, 2, ',', '.') }} €
    </p>

    <div class="relative z-10 mt-2 inline-flex items-center rounded-md bg-sky-100 px-2 py-0.5 text-[10px] font-bold text-sky-800
                dark:bg-sky-500/10 dark:text-sky-300">
        Con IVA: {{ number_format($totales['recurrente_con_iva'] ?? 0, 2, ',', '.') }} €
    </div>

    @if(!empty($totales['lista_recurrentes'] ?? []))
        <div class="relative z-10 mt-3 space-y-1 text-[11px] text-sky-800/80 dark:text-sky-300/80">
            @foreach(($totales['lista_recurrentes'] ?? []) as $n)
                <div class="truncate">• {{ $n }}</div>
            @endforeach
        </div>
    @else
        <p class="relative z-10 mt-2 text-[10px] text-sky-800/70 dark:text-sky-300/70">
            Desde día 1
        </p>
    @endif
</div>

{{-- KPI 3: Prorrata --}}
<div class="relative overflow-hidden rounded-2xl border border-amber-200 bg-white p-4 shadow-sm
            dark:border-amber-900 dark:bg-gray-900">
    <div class="pointer-events-none absolute -right-10 -top-10 h-36 w-36 rounded-full bg-amber-400/20 blur-2xl
                dark:bg-amber-400/10"></div>

    <p class="relative z-10 text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-500">
        Prorrata
    </p>

    <p class="relative z-10 mt-1 text-2xl font-extrabold text-amber-600 dark:text-amber-500">
        {{ number_format($totales['prorrata'] ?? 0, 2, ',', '.') }} €
    </p>

    <div class="relative z-10 mt-2 inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800
                dark:bg-amber-500/10 dark:text-amber-300">
        Con IVA: {{ number_format($totales['prorrata_con_iva'] ?? 0, 2, ',', '.') }} €
    </div>

    <p class="relative z-10 mt-2 text-[10px] text-amber-800/70 dark:text-amber-300/70">
       @if(!empty($totales['tiene_bloqueo_recurrente'] ?? false))
            Prorrata desactivada por bloqueo
        @else
            {{ $totales['dias_restantes'] ?? 0 }} días restantes
        @endif
    </p>
</div>

{{-- KPI 4: Total Hoy --}}
<div class="relative overflow-hidden rounded-2xl border border-emerald-200 bg-white p-4 shadow-sm
            dark:border-emerald-900 dark:bg-gray-900">
    <div class="pointer-events-none absolute -right-12 -top-12 h-40 w-40 rounded-full bg-emerald-400/20 blur-2xl
                dark:bg-emerald-400/10"></div>

    <p class="relative z-10 text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
        Total Hoy
    </p>

    <p class="relative z-10 mt-1 text-3xl font-black leading-none text-gray-900 dark:text-white">
        {{ number_format($totales['total_hoy_sin_iva'] ?? 0, 2, ',', '.') }} €
    </p>

    <div class="relative z-10 mt-2 inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800
                dark:bg-emerald-500/20 dark:text-emerald-300">
        IVA INCLUIDO: {{ number_format($totales['total_hoy_con_iva'] ?? 0, 2, ',', '.') }} €
    </div>
</div>
