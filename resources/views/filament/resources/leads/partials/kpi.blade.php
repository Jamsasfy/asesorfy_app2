{{-- KPI 1 --}}
<div class="rounded-xl border border-gray-700 bg-gray-900 p-4 shadow-sm">
    <p class="text-xs font-bold uppercase tracking-wider text-gray-500">Pago Único</p>
    <p class="mt-1 text-2xl font-bold text-white">{{ number_format($totales['unico'], 2, ',', '.') }} €</p>
    <p class="text-[10px] text-gray-500">Alta servicio</p>
</div>

{{-- KPI 2 --}}
<div class="rounded-xl border border-gray-700 bg-gray-900 p-4 shadow-sm">
    <p class="text-xs font-bold uppercase tracking-wider text-blue-400">Mensual</p>
    <p class="mt-1 text-2xl font-bold text-blue-400">{{ number_format($totales['recurrente'], 2, ',', '.') }} €</p>
    <p class="text-[10px] text-gray-500">Desde día 1</p>
</div>

{{-- KPI 3 --}}
<div class="rounded-xl border border-gray-700 bg-gray-900 p-4 shadow-sm">
    <p class="text-xs font-bold uppercase tracking-wider text-amber-500">Prorrata</p>
    <p class="mt-1 text-2xl font-bold text-amber-500">{{ number_format($totales['prorrata'], 2, ',', '.') }} €</p>
    <p class="text-[10px] text-gray-500">{{ $totales['dias_restantes'] }} días restantes</p>
</div>

{{-- KPI 4 (TOTAL) --}}
<div class="relative overflow-hidden rounded-xl border border-emerald-900 bg-gradient-to-br from-emerald-900/50 to-gray-900 p-4 shadow-sm">
    <p class="relative z-10 text-xs font-bold uppercase tracking-wider text-emerald-400">Total Hoy</p>
    <p class="relative z-10 mt-1 text-3xl font-black text-white leading-none">
        {{ number_format($totales['total_hoy'], 2, ',', '.') }} €
    </p>
    <div class="relative z-10 mt-2 inline-block rounded bg-emerald-500/20 px-1.5 py-0.5 text-[10px] font-bold text-emerald-300">
        IVA INCLUIDO
    </div>
</div>