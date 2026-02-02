{{-- resources/views/filament/portal/pages/suscripcion.blade.php --}}
<x-filament-panels::page>
    @php
        $accent = '#41c0e9';

        $cliente = $cliente ?? ($this->cliente ?? null);

        $suscripciones = $suscripciones
            ?? ($this->suscripciones ?? null)
            ?? ($this->suscripcion ?? null);

        if ($suscripciones instanceof \Illuminate\Support\Collection) {
            $suscripciones = $suscripciones;
        } elseif (is_array($suscripciones)) {
            $suscripciones = collect($suscripciones);
        } elseif ($suscripciones) {
            $suscripciones = collect([$suscripciones]);
        } else {
            $suscripciones = collect();
        }

        $suscripcionesRec = $suscripciones->filter(fn ($s) => $s !== null)->values();

        $clienteNombre = $cliente?->razon_social ?? $cliente?->nombre ?? 'Tu cuenta';

        $ivaPct = $cliente
            ? \App\Models\Cliente::getPorcentajeImpuesto($cliente->codigo_postal ?? null, $cliente->provincia ?? null)
            : 21.00;

        $fmtMoney = fn (float $n) => number_format($n, 2, ',', '.') . ' €';

        $ivaLabel = $ivaPct > 0
            ? '+ IVA (' . rtrim(rtrim(number_format($ivaPct, 2, ',', '.'), '0'), ',') . '%)'
            : 'Sin IVA';

        $cleanServicioName = function (?string $name): string {
            $name = trim((string) $name);
            if ($name === '') return '—';

            if (\Illuminate\Support\Str::contains($name, '·')) {
                $parts = array_values(array_filter(array_map('trim', explode('·', $name))));
                if (count($parts) >= 2) {
                    return $parts[1];
                }
            }

            if (preg_match('/^[A-Z]{2,6}\s*[-—:]\s*(.+)$/u', $name, $m)) {
                return trim($m[1]);
            }

            return $name;
        };

        /**
         * ✅ UI estado SOLO local (no Stripe).
         * - Activa: verde
         * - Impago: naranja
         * - Baja: rojo
         */
        $statusUi = function ($sus) {
            $estado = data_get($sus, 'estado');

            $localValue = '';
            $localLabel = '—';

            if ($estado instanceof \BackedEnum) {
                $localValue = (string) $estado->value;
                $localLabel = method_exists($estado, 'getLabel')
                    ? (string) $estado->getLabel()
                    : (string) $estado->value;
            } elseif ($estado instanceof \UnitEnum) {
                $localValue = (string) $estado->name;
                $localLabel = (string) $estado->name;
            } else {
                $localValue = is_string($estado) ? $estado : (string) ($estado ?? '');
                $localLabel = $localValue !== '' ? $localValue : '—';
            }

            $val = strtolower(trim((string) $localValue));

            $isActiva = in_array($val, ['activa', 'activo', 'active'], true);
            $isImpago = in_array($val, ['impago', 'pendiente_pago', 'pendiente de pago', 'moroso'], true);
            $isBaja   = in_array($val, ['cancelada', 'finalizada', 'baja', 'rescindido', 'rescindida'], true);

            if ($isActiva) return ['label' => 'Activa', 'tone' => 'success'];
            if ($isImpago) return ['label' => 'Impago', 'tone' => 'warning'];
            if ($isBaja)   return ['label' => 'Baja',   'tone' => 'danger'];

            return ['label' => ($localLabel ?: '—'), 'tone' => 'gray'];
        };

        $lineas = $suscripcionesRec->map(function ($s) {
            $precio = (float) (data_get($s, 'precio_acordado') ?? 0);
            $qty    = (int) (data_get($s, 'cantidad') ?? 1);
            if ($qty <= 0) $qty = 1;

            return [
                'sus'    => $s,
                'precio' => $precio,
                'qty'    => $qty,
                'base'   => $precio * $qty,
            ];
        });

        $totalBase = (float) $lineas->sum('base');
        $totalIva  = $ivaPct > 0 ? round($totalBase * ($ivaPct / 100), 2) : 0.0;
        $totalConIva = $totalBase + $totalIva;

        $nextDate = null;
        $candidates = [];

        foreach ($suscripcionesRec as $s) {
            $pf = data_get($s, 'proxima_fecha_facturacion');
            if (! blank($pf)) {
                try { $candidates[] = \Illuminate\Support\Carbon::parse($pf); } catch (\Throwable $e) {}
            } else {
                $ts = data_get($s, 'stripe_current_period_end');
                if (! blank($ts)) {
                    try {
                        $candidates[] = \Illuminate\Support\Carbon::createFromTimestamp((int) $ts)
                            ->timezone(config('app.timezone', 'Europe/Madrid'));
                    } catch (\Throwable $e) {}
                }
            }
        }

        if (! empty($candidates)) {
            $nextDate = collect($candidates)->sort()->first();
        }

        // ✅ Estilos “pro” para los KPI cards
        $kpiCardClass = 'group relative overflow-hidden rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10';
        $kpiBorder = 'border border-sky-200/60 dark:border-sky-400/20';
        $kpiGlow = 'before:content-[\'\'] before:absolute before:-inset-0.5 before:rounded-[1.25rem] before:bg-gradient-to-r before:from-sky-400/20 before:via-cyan-300/10 before:to-purple-400/10 before:opacity-0 hover:before:opacity-100 before:blur-xl before:transition';
        $kpiTopLine = 'after:content-[\'\'] after:absolute after:left-5 after:right-5 after:top-0 after:h-[2px] after:rounded-full after:bg-gradient-to-r after:from-transparent after:via-sky-400/60 after:to-transparent after:opacity-70';
    @endphp

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 items-start">
        {{-- IZQUIERDA --}}
        <div class="lg:col-span-7 xl:col-span-8 space-y-6">
            {{-- HERO --}}
            <div class="relative overflow-hidden rounded-3xl bg-white p-8 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="absolute -right-24 -top-24 h-56 w-56 rounded-full opacity-20 blur-3xl"
                     style="background: {{ $accent }}"></div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-xl font-black tracking-tight text-gray-950 dark:text-white">
                            Suscripción
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Resumen de tus servicios mensuales y próximo cobro.
                        </p>
                    </div>

                    <div class="mt-4 sm:mt-0 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-bold ring-1 ring-inset
                            {{ $ivaPct > 0
                                ? 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-950/40 dark:text-sky-200 dark:ring-sky-400/20'
                                : 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-400/20' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $ivaPct > 0 ? 'bg-sky-500' : 'bg-emerald-500' }}"></span>
                            {{ $ivaLabel }}
                        </span>

                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 px-3 py-1 text-xs font-bold text-gray-700 ring-1 ring-inset ring-gray-200 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10">
                            <x-filament::icon icon="heroicon-m-user-circle" class="h-4 w-4" />
                            {{ $clienteNombre }}
                        </span>
                    </div>
                </div>

                {{-- KPIs --}}
                <div class="mt-8 grid grid-cols-1 gap-4 md:grid-cols-3">
                    {{-- Cuota base --}}
                    <div class="{{ $kpiCardClass }} {{ $kpiGlow }} {{ $kpiTopLine }} {{ $kpiBorder }}">
                        <div class="relative">
                            <div class="flex items-center justify-between">
                                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                                    Cuota mensual (base)
                                </div>
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 ring-1 ring-inset ring-sky-200 dark:bg-sky-950/40 dark:ring-sky-400/20">
                                    <x-filament::icon icon="heroicon-m-currency-euro" class="h-5 w-5 text-sky-700 dark:text-sky-200" />
                                </div>
                            </div>

                            {{-- ✅ Precio en azul AsesorFy --}}
                            <div class="mt-3 text-2xl font-black tabular-nums" style="color: {{ $accent }};">
                                {{ $fmtMoney($totalBase) }}
                            </div>

                            @if($ivaPct > 0)
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Total aprox. con IVA:
                                    <span class="font-black text-gray-900 dark:text-white">{{ $fmtMoney($totalConIva) }}</span>
                                </div>
                            @else
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    <span class="font-black text-emerald-700 dark:text-emerald-300">Sin IVA</span> por ubicación fiscal.
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Próximo cobro --}}
                    <div class="{{ $kpiCardClass }} {{ $kpiGlow }} {{ $kpiTopLine }} {{ $kpiBorder }}">
                        <div class="relative">
                            <div class="flex items-center justify-between">
                                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                                    Próximo cobro
                                </div>
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 ring-1 ring-inset ring-sky-200 dark:bg-sky-950/40 dark:ring-sky-400/20">
                                    <x-filament::icon icon="heroicon-m-calendar-days" class="h-5 w-5 text-sky-700 dark:text-sky-200" />
                                </div>
                            </div>

                            <div class="mt-3 text-2xl font-black tabular-nums" style="color: {{ $accent }};">
                                {{ $nextDate ? $nextDate->format('d/m/Y') : '—' }}
                            </div>

                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Fecha estimada en horario local.
                            </div>
                        </div>
                    </div>

                    {{-- Servicios recurrentes --}}
                    <div class="{{ $kpiCardClass }} {{ $kpiGlow }} {{ $kpiTopLine }} {{ $kpiBorder }}">
                        <div class="relative">
                            <div class="flex items-center justify-between">
                                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                                    Servicios recurrentes
                                </div>
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 ring-1 ring-inset ring-sky-200 dark:bg-sky-950/40 dark:ring-sky-400/20">
                                    <x-filament::icon icon="heroicon-m-squares-2x2" class="h-5 w-5 text-sky-700 dark:text-sky-200" />
                                </div>
                            </div>

                            <div class="mt-3 text-2xl font-black tabular-nums" style="color: {{ $accent }};">
                                {{ (string) $suscripcionesRec->count() }}
                            </div>

                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Fiscal, laboral u otros.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- LISTA --}}
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-black tracking-tight text-gray-950 dark:text-white">
                        Servicios mensuales
                    </h3>
                    <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                        Detalle por servicio
                    </span>
                </div>

                @if($suscripcionesRec->isEmpty())
                    <div class="mt-6 rounded-2xl bg-gray-50 p-5 text-sm text-gray-600 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
                        No hay suscripciones mensuales configuradas en este momento.
                    </div>
                @else
                    <div class="mt-6 space-y-3">
                        @foreach($lineas as $l)
                            @php
                                $sus = $l['sus'];
                                $ui = $statusUi($sus);
                                $tone = $ui['tone'];

                                $nameRaw = (string) (data_get($sus, 'nombre_final') ?? data_get($sus, 'servicio.nombre') ?? '—');
                                $serviceName = $cleanServicioName($nameRaw);

                                $qty = (int) $l['qty'];
                                $precio = (float) $l['precio'];
                                $base = (float) $l['base'];

                                $badgeClass = match($tone) {
                                    'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/20',
                                    'warning' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20',
                                    'danger'  => 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-400/20',
                                    default   => 'bg-gray-50 text-gray-700 ring-gray-200 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10',
                                };
                            @endphp

                            <div class="rounded-2xl bg-gray-50 p-5 ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <div class="text-base font-black text-gray-950 dark:text-white truncate">
                                                {{ $serviceName }}
                                            </div>

                                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-black ring-1 ring-inset {{ $badgeClass }}">
                                                {{ $ui['label'] }}
                                            </span>
                                        </div>

                                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                            @if($qty > 1)
                                                Unidades: <span class="font-black text-gray-900 dark:text-white">{{ $qty }}</span>
                                                · Precio unidad: <span class="font-black text-gray-900 dark:text-white">{{ $fmtMoney($precio) }}</span>
                                            @else
                                                Precio: <span class="font-black text-gray-900 dark:text-white">{{ $fmtMoney($precio) }}</span>
                                            @endif
                                            <span class="ml-2">{{ $ivaLabel }}</span>
                                        </div>
                                    </div>

                                    <div class="shrink-0 text-right">
                                        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">Subtotal (base)</div>
                                        <div class="mt-1 text-lg font-black text-gray-950 dark:text-white tabular-nums">
                                            {{ $fmtMoney($base) }}
                                        </div>
                                        @if($ivaPct > 0)
                                            @php
                                                $baseIva = round($base * ($ivaPct / 100), 2);
                                                $baseTotal = $base + $baseIva;
                                            @endphp
                                            <div class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                                                Total aprox.:
                                                <span class="font-black text-gray-900 dark:text-white">{{ $fmtMoney($baseTotal) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                {{-- Próximo cobro por servicio --}}
                                @php
                                    $pf = data_get($sus, 'proxima_fecha_facturacion');
                                    $pfFmt = null;

                                    if (! blank($pf)) {
                                        try { $pfFmt = \Illuminate\Support\Carbon::parse($pf)->format('d/m/Y'); } catch (\Throwable $e) {}
                                    } else {
                                        $ts = data_get($sus, 'stripe_current_period_end');
                                        if (! blank($ts)) {
                                            try {
                                                $pfFmt = \Illuminate\Support\Carbon::createFromTimestamp((int) $ts)
                                                    ->timezone(config('app.timezone', 'Europe/Madrid'))
                                                    ->format('d/m/Y');
                                            } catch (\Throwable $e) {}
                                        }
                                    }
                                @endphp

                                <div class="mt-4 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                    <span class="inline-flex items-center gap-1.5">
                                        <x-filament::icon icon="heroicon-m-calendar-days" class="h-4 w-4" />
                                        Próximo cobro: <span class="font-black text-gray-900 dark:text-white">{{ $pfFmt ?? '—' }}</span>
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- DERECHA --}}
        <div class="lg:col-span-5 xl:col-span-4 space-y-6">
            <div class="rounded-3xl bg-gray-50 p-6 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                <h4 class="text-sm font-black text-gray-900 dark:text-white">
                    Cómo funciona tu facturación
                </h4>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Aquí ves el estado del servicio en AsesorFy y el importe mensual estimado. No mostramos estados internos de Stripe para evitar confusiones.
                </p>

                <div class="mt-5 space-y-3 text-xs text-gray-600 dark:text-gray-400">
                    <div class="flex gap-3">
                        {{-- ✅ Activa en VERDE (antes era $accent -> azul) --}}
                        <span class="mt-1 h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        <div><span class="font-black text-gray-900 dark:text-white">Activa</span>: tu servicio está en curso.</div>
                    </div>
                    <div class="flex gap-3">
                        <span class="mt-1 h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                        <div><span class="font-black text-gray-900 dark:text-white">Impago</span>: hay un pago pendiente.</div>
                    </div>
                    <div class="flex gap-3">
                        <span class="mt-1 h-1.5 w-1.5 rounded-full bg-red-500"></span>
                        <div><span class="font-black text-gray-900 dark:text-white">Baja</span>: el servicio ya no está activo.</div>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h4 class="flex items-center gap-2 text-sm font-black text-gray-900 dark:text-white">
                    <x-filament::icon icon="heroicon-m-question-mark-circle" class="h-5 w-5 text-gray-400" />
                    ¿Necesitas ayuda?
                </h4>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Si algo no cuadra, habla con tu asesor y lo revisamos.
                </p>
            </div>
        </div>
    </div>
</x-filament-panels::page>
