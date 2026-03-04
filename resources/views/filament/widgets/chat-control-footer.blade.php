<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4">
        <div class="font-semibold mb-2">🔥 Más rápidos · {{ $month }}/{{ $year }}</div>
        <ul class="space-y-1 text-sm">
            @forelse($fast as $r)
                <li class="flex justify-between">
                    <span class="truncate">{{ $r['name'] }}</span>
                    <span class="text-gray-600 dark:text-gray-300">{{ $r['avg_min'] }} min · {{ $r['n'] }}</span>
                </li>
            @empty
                <li class="text-gray-500">Sin datos suficientes.</li>
            @endforelse
        </ul>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-800 p-4">
        <div class="font-semibold mb-2">🐢 Más lentos · {{ $month }}/{{ $year }}</div>
        <ul class="space-y-1 text-sm">
            @forelse($slow as $r)
                <li class="flex justify-between">
                    <span class="truncate">{{ $r['name'] }}</span>
                    <span class="text-gray-600 dark:text-gray-300">{{ $r['avg_min'] }} min · {{ $r['n'] }}</span>
                </li>
            @empty
                <li class="text-gray-500">Sin datos suficientes.</li>
            @endforelse
        </ul>
    </div>
</div>
