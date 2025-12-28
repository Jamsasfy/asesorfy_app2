{{-- resources/views/filament/resources/leads/partials/gestionar-conversion.blade.php --}}

<x-filament-panels::page>
    @php
        $conversionInfo = $this->conversionInfo ?? null;

        $estado = $lead->estado?->value ?? null;

        $conversionEnviada = in_array($estado, ['convertido_espera_firma', 'convertido_espera_datos'], true);
        $conversionFinalizada = ($estado === 'convertido_firmado');
        $conversionCorreccion = ($estado === 'convertido_correccion');

        $accionesBloqueadas = $conversionFinalizada;


        $expiresAt = !empty($conversionInfo['expires_at'])
            ? \Illuminate\Support\Carbon::parse($conversionInfo['expires_at'])
            : null;

        $createdAt = !empty($conversionInfo['created_at'])
            ? \Illuminate\Support\Carbon::parse($conversionInfo['created_at'])
            : null;

        $restanteSeg = $expiresAt ? now()->diffInSeconds($expiresAt, false) : null;

        $caducaEn = $expiresAt
            ? $expiresAt->diffForHumans(now(), [
                'parts' => 2,
                'short' => true,
                'syntax' => \Illuminate\Support\Carbon::DIFF_ABSOLUTE,
            ])
            : null;

        $caducadoHace = $expiresAt
            ? $expiresAt->diffForHumans(now(), [
                'parts' => 2,
                'short' => true,
                'syntax' => \Illuminate\Support\Carbon::DIFF_RELATIVE_TO_NOW,
            ])
            : null;

        $isExpired = !empty($conversionInfo['is_expired']) || ($restanteSeg !== null && $restanteSeg <= 0);
        $isUsed = !empty($conversionInfo['is_used']);

        // URL a ver Lead (nueva pestaña)
        $leadViewUrl = \App\Filament\Resources\LeadResource::getUrl('view', ['record' => $lead->id]);

        // Resumen servicios agrupado (con descuentos)
        $resumen = collect($items ?? [])
            ->filter(fn ($it) => !empty($it['servicio_id']))
            ->map(function ($it) {
                $tipo = (string) ($it['tipo'] ?? 'unico');
                $cant = (int) ($it['cantidad'] ?? 1);

                $nombre = null;
                if (!empty($it['servicio_id'])) {
                    $svc = $this->servicios?->firstWhere('id', (int) $it['servicio_id']);
                    $nombre = $svc?->nombre;
                }

                if (!empty($it['es_editable'])) {
                    $nombre = $it['nombre_personalizado'] ?: $nombre;
                }

                $base = (float) ($it['subtotal_base'] ?? 0);
                $final = (float) ($it['subtotal_final'] ?? 0);

                $dtoAplicar = (bool) ($it['aplicar_descuento'] ?? false);
                $dtoTipo = $dtoAplicar ? ($it['descuento_tipo'] ?? null) : null;
                $dtoValor = $dtoAplicar ? ($it['descuento_valor'] ?? null) : null;
                $dtoMeses = ($tipo === 'recurrente' && $dtoAplicar) ? ($it['descuento_duracion_meses'] ?? null) : null;

                $dtoLabel = null;
                if ($dtoAplicar && $dtoTipo) {
                    if ($dtoTipo === 'porcentaje') {
                        $dtoLabel = '-' . (string) $dtoValor . '%';
                    } elseif ($dtoTipo === 'fijo') {
                        $dtoLabel = '-' . (string) $dtoValor . '€';
                    } elseif ($dtoTipo === 'precio_final') {
                        $dtoLabel = 'Precio final';
                    }
                }

                return [
                    'nombre' => $nombre ?: 'Servicio',
                    'tipo'   => $tipo,
                    'cant'   => $cant,
                    'base'   => $base,
                    'final'  => $final,
                    'dto'    => $dtoLabel,
                    'meses'  => $dtoMeses,
                    'tiene_dto' => $dtoAplicar && $dtoLabel,
                ];
            })
            ->values();

        $rec = $resumen->where('tipo', 'recurrente')->values();
        $uni = $resumen->where('tipo', '!=', 'recurrente')->values();

        $totalRec = round((float) $rec->sum('final'), 2);
        $totalUni = round((float) $uni->sum('final'), 2);
    @endphp

    <div class="flex flex-col gap-6">

        {{-- GRID SUPERIOR DE 5 COLUMNAS EXACTAS --}}
        <div class="grid grid-cols-5 gap-4">

            {{-- COL 1: CLIENTE / TIPO + NOMBRE DEL LEAD + EMAIL --}}
            <div class="rounded-xl border border-sky-200/60 bg-white/85 p-4 shadow-sm backdrop-blur
                        dark:border-sky-900/60 dark:bg-gray-900/70">
                <label class="mb-2 block text-xs font-bold uppercase tracking-wider text-sky-700/80
                              dark:text-sky-300/80">
                    Cliente
                </label>

                <div class="relative">
                    <select
                        wire:model.live="tipoClienteId"
                        class="w-full appearance-none rounded-lg border border-sky-200 bg-white px-3 py-2 text-base font-bold text-gray-900
                               focus:border-sky-600 focus:outline-none focus:ring-2 focus:ring-sky-600/25
                               dark:border-sky-900/70 dark:bg-gray-900 dark:text-white dark:focus:ring-sky-500/30"
                    >
                        @foreach(\App\Models\TipoCliente::all() as $tipo)
                            <option value="{{ $tipo->id }}" class="bg-white text-gray-900 dark:bg-gray-900 dark:text-white">
                                {{ $tipo->nombre }}
                            </option>
                        @endforeach
                    </select>

                    {{-- Flecha --}}
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-sky-600/60
                                dark:text-sky-300/60">
                        <svg class="h-4 w-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                            <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/>
                        </svg>
                    </div>
                </div>

                {{-- Lead debajo --}}
                <div class="mt-3 rounded-lg border border-sky-200/70
                            bg-gradient-to-br from-sky-100/90 via-white/80 to-sky-50/80
                            px-3 py-2 shadow-sm
                            dark:border-sky-900/60 dark:bg-sky-950/30 dark:bg-none">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-sky-700/70
                                dark:text-sky-300/70">
                        Lead
                    </div>

                    <div class="mt-0.5 text-base font-extrabold text-gray-900
                                dark:text-white">
                        {{ $lead?->nombre ?? '—' }}
                    </div>

                    <div class="mt-1 text-sm font-extrabold text-yellow-600
                                dark:text-yellow-300">
                        {{ $lead?->email ?? '—' }}
                    </div>
                </div>
            </div>

            {{-- COLS 2-5: LOS KPIs --}}
            @include('filament.resources.leads.partials.kpi', ['totales' => $this->totales])

        </div>

        {{-- PANEL: PROPUESTA ENVIADA / FINALIZADA --}}
        @if($conversionEnviada || $conversionFinalizada)
            <div class="rounded-2xl border border-blue-200/80 bg-blue-50/70 p-5 shadow-sm backdrop-blur
                        dark:border-blue-900/60 dark:bg-blue-950/25">
                <div class="flex items-start justify-between gap-6">
                    <div class="min-w-0 flex-1">

                        <div class="flex flex-wrap items-center gap-2">
                            <div class="text-base font-extrabold text-blue-900 dark:text-blue-100">
                                @if($conversionFinalizada)
                                    Conversión finalizada
                                @else
                                    Propuesta enviada · Esperando firma
                                @endif
                            </div>

                            @if($conversionFinalizada)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800
                                             dark:bg-emerald-900/40 dark:text-emerald-200">
                                    Cerrado / firmado
                                </span>
                            @else
                                @if($isUsed)
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800
                                                 dark:bg-emerald-900/40 dark:text-emerald-200">
                                        Link usado
                                    </span>
                                @elseif($isExpired)
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-rose-100 text-rose-800
                                                 dark:bg-rose-900/40 dark:text-rose-200">
                                        Link caducado
                                    </span>
                                @else
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-800
                                                 dark:bg-blue-900/40 dark:text-blue-200">
                                        Link activo
                                    </span>
                                @endif
                            @endif
                        </div>

                        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                            {{-- Email --}}
                            <div class="rounded-xl border border-slate-200 bg-white/80 p-3
                                        dark:border-slate-800 dark:bg-gray-900/60">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Email destino
                                </div>
                                <div class="mt-1 text-base font-extrabold text-slate-900 dark:text-white break-all">
                                    {{ $conversionInfo['last_sent_to'] ?? ($this->emailDestino ?? '—') }}
                                </div>
                                <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    Enviado: {{ $conversionInfo['last_sent_at'] ?? '—' }}
                                </div>
                            </div>

                           {{-- Caducidad --}}
                            <div class="rounded-xl border border-slate-200 bg-white/80 p-3
                                        dark:border-slate-800 dark:bg-gray-900/60">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Caducidad del enlace
                                </div>

                                @php
                                    $isUsed = !empty($conversionInfo['is_used']);     // firmado / usado
                                    $isRevoked = !empty($conversionInfo['is_revoked']); // cancelado (si lo metes en conversionInfo)
                                @endphp

                                {{-- 1) Si está usado (firmado), NO mostramos "caduca en X" aunque expires_at sea futuro --}}
                                @if($isUsed)
                                   <div class="mt-1 flex flex-wrap items-center gap-2">
                                    <div class="text-base font-extrabold text-slate-900 dark:text-white">
                                        Enlace caducado (firmado)
                                    </div>

                                    <span class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-2.5 py-0.5 text-[12px] font-extrabold text-rose-800
                                                dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-200">
                                        Invalidado
                                    </span>
                                </div>
                                    <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        El enlace queda invalidado automáticamente tras la firma.
                                    </div>

                                {{-- 2) Si está revocado/cancelado --}}
                                @elseif($isRevoked)
                                    <div class="mt-1 text-base font-extrabold text-slate-900 dark:text-white">
                                        Enlace cancelado
                                    </div>
                                    <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        Genera uno nuevo con “Reiniciar token” o vuelve a enviar la propuesta.
                                    </div>

                                {{-- 3) Caso normal (activo/caducado por tiempo) --}}
                                @elseif($expiresAt)
                                    <div class="mt-1 text-base font-extrabold text-slate-900 dark:text-white">
                                        {{ $expiresAt->format('d/m/Y H:i') }}
                                    </div>

                                    <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        @if($restanteSeg !== null && $restanteSeg > 0)
                                            Quedan ~{{ $caducaEn }}
                                        @else
                                            Caducado ({{ $caducadoHace }})
                                        @endif
                                    </div>

                                @else
                                    <div class="mt-1 text-base font-extrabold text-slate-900 dark:text-white">—</div>
                                    <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                        No hay caducidad configurada.
                                    </div>
                                @endif
                            </div>


                            {{-- Token / creado --}}
                            <div class="rounded-xl border border-slate-200 bg-white/80 p-3
                                        dark:border-slate-800 dark:bg-gray-900/60">
                                <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                    Identificador
                                </div>
                                <div class="mt-1 text-base font-extrabold text-slate-900 dark:text-white break-all">
                                    {{ $conversionInfo['token'] ?? '—' }}
                                </div>
                                <div class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                                    Creado: {{ $createdAt ? $createdAt->format('d/m/Y H:i') : ($conversionInfo['created_at'] ?? '—') }}
                                </div>
                            </div>
                        </div>

                        {{-- Link + copiar --}}
                        <div class="mt-4 rounded-xl border border-blue-200/70 bg-white/60 p-3
                                    dark:border-blue-900/50 dark:bg-gray-900/40">
                            <div class="text-[10px] font-bold uppercase tracking-wider text-blue-700/70 dark:text-blue-300/70">
                                Enlace público
                            </div>

                            @if(!empty($conversionInfo['url']))
                                <div class="mt-1 flex flex-wrap items-center gap-2">
                                    <a href="{{ $conversionInfo['url'] }}" target="_blank"
                                       class="text-base font-bold underline text-blue-700 dark:text-blue-200 break-all">
                                        {{ $conversionInfo['url'] }}
                                    </a>

                                    <button type="button"
                                            class="text-sm px-2 py-1 rounded-lg border border-blue-200 dark:border-blue-900 bg-white/60 dark:bg-gray-900/40"
                                            x-data
                                            x-on:click="navigator.clipboard.writeText(@js($conversionInfo['url']))">
                                        Copiar
                                    </button>

                                    <a href="{{ $conversionInfo['url'] }}" target="_blank"
                                       class="text-sm px-2 py-1 rounded-lg border border-slate-200 dark:border-slate-800 bg-white/60 dark:bg-gray-900/40">
                                        Abrir
                                    </a>
                                </div>
                            @else
                                <div class="mt-1 text-base font-bold text-slate-900 dark:text-white">—</div>
                            @endif
                        </div>

                        {{-- Resumen servicios --}}
                        <div class="mt-5">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="text-sm font-extrabold uppercase tracking-wider text-slate-600 dark:text-slate-300">
                                    Resumen de servicios enviados
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <span class="rounded-full border border-slate-200 bg-white/70 px-3 py-1 text-sm font-extrabold text-slate-800
                                                 dark:border-slate-800 dark:bg-gray-900/60 dark:text-slate-100">
                                        Únicos: {{ number_format($totalUni, 2, ',', '.') }} €
                                    </span>
                                    <span class="rounded-full border border-slate-200 bg-white/70 px-3 py-1 text-sm font-extrabold text-slate-800
                                                 dark:border-slate-800 dark:bg-gray-900/60 dark:text-slate-100">
                                        Recurrente: {{ number_format($totalRec, 2, ',', '.') }} €/mes
                                    </span>
                                </div>
                            </div>

                            @if($resumen->isEmpty())
                                <div class="mt-2 text-base text-slate-600 dark:text-slate-300">
                                    No se pudo generar el resumen de servicios.
                                </div>
                            @else
                                <div class="mt-3 grid grid-cols-1 lg:grid-cols-2 gap-4">

                                    {{-- Recurrentes --}}
                                    <div class="rounded-2xl border border-slate-200 bg-white/60 p-4
                                                dark:border-slate-800 dark:bg-gray-900/40">
                                        <div class="flex items-center justify-between">
                                            <div class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl
                                                             bg-sky-100 text-sky-900 dark:bg-sky-500/15 dark:text-sky-200">
                                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586l-1.707 1.707a1 1 0 001.414 1.414l2-2A1 1 0 0011 11V7z" />
                                                    </svg>
                                                </span>
                                                Recurrentes (mensual)
                                            </div>
                                            <div class="text-base font-extrabold text-slate-900 dark:text-white">
                                                {{ number_format($totalRec, 2, ',', '.') }} €/mes
                                            </div>
                                        </div>

                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @forelse($rec as $s)
                                                @php
                                                    $precioTxt = number_format((float) $s['final'], 2, ',', '.');
                                                    $baseTxt = number_format((float) $s['base'], 2, ',', '.');
                                                    $tieneDto = !empty($s['tiene_dto']);
                                                    $dto = $s['dto'] ?? null;
                                                    $meses = $s['meses'] ?? null;
                                                @endphp

                                                <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/70 px-3 py-2 text-sm font-extrabold text-slate-800
                                                             dark:border-slate-800 dark:bg-slate-950/35 dark:text-slate-100">
                                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full
                                                                 bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200">
                                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                            <path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V7.414A2 2 0 0017.414 6L14 2.586A2 2 0 0012.586 2H4z" />
                                                        </svg>
                                                    </span>

                                                    <span>{{ $s['nombre'] }}</span>

                                                    <span class="opacity-80">· {{ $precioTxt }} €/mes</span>

                                                    @if($tieneDto)
                                                        <span class="ml-1 rounded-full border border-amber-500/40 bg-amber-200/80 px-2 py-0.5 text-[12px] font-extrabold text-amber-950
                                                                     dark:border-amber-400/30 dark:bg-amber-500/15 dark:text-amber-200">
                                                            {{ $dto }}@if($meses) · {{ $meses }}@endif
                                                        </span>

                                                        <span class="text-[12px] font-bold line-through opacity-50">
                                                            {{ $baseTxt }}
                                                        </span>
                                                    @endif
                                                </span>
                                            @empty
                                                <div class="text-base text-slate-600 dark:text-slate-300">
                                                    No hay servicios recurrentes.
                                                </div>
                                            @endforelse
                                        </div>
                                    </div>

                                    {{-- Únicos --}}
                                    <div class="rounded-2xl border border-slate-200 bg-white/60 p-4
                                                dark:border-slate-800 dark:bg-gray-900/40">
                                        <div class="flex items-center justify-between">
                                            <div class="text-base font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
                                                <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl
                                                             bg-indigo-100 text-indigo-900 dark:bg-indigo-500/15 dark:text-indigo-200">
                                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V7.414A2 2 0 0017.414 6L14 2.586A2 2 0 0012.586 2H4z" />
                                                    </svg>
                                                </span>
                                                Únicos (inicio)
                                            </div>
                                            <div class="text-base font-extrabold text-slate-900 dark:text-white">
                                                {{ number_format($totalUni, 2, ',', '.') }} €
                                            </div>
                                        </div>

                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @forelse($uni as $s)
                                                @php
                                                    $precioTxt = number_format((float) $s['final'], 2, ',', '.');
                                                    $baseTxt = number_format((float) $s['base'], 2, ',', '.');
                                                    $tieneDto = !empty($s['tiene_dto']);
                                                    $dto = $s['dto'] ?? null;
                                                @endphp

                                                <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/70 px-3 py-2 text-sm font-extrabold text-slate-800
                                                             dark:border-slate-800 dark:bg-slate-950/35 dark:text-slate-100">
                                                    <span class="inline-flex h-7 w-7 items-center justify-center rounded-full
                                                                 bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-slate-200">
                                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                            <path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V7.414A2 2 0 0017.414 6L14 2.586A2 2 0 0012.586 2H4z" />
                                                        </svg>
                                                    </span>

                                                    <span>{{ $s['nombre'] }}</span>

                                                    <span class="opacity-80">· {{ $precioTxt }} €</span>

                                                    @if($tieneDto)
                                                        <span class="ml-1 rounded-full border border-amber-500/40 bg-amber-200/70 px-2 py-0.5 text-[12px] font-extrabold text-amber-950
                                                                     dark:border-amber-400/30 dark:bg-amber-500/15 dark:text-amber-200">
                                                            {{ $dto }}
                                                        </span>

                                                        <span class="text-[12px] font-bold line-through opacity-50">
                                                            {{ $baseTxt }}
                                                        </span>
                                                    @endif
                                                </span>
                                            @empty
                                                <div class="text-base text-slate-600 dark:text-slate-300">
                                                    No hay servicios únicos.
                                                </div>
                                            @endforelse
                                        </div>
                                    </div>

                                </div>
                            @endif
                        </div>

                        @if(($lead->estado?->value ?? null) === 'convertido_firmado')
                            <div class="mt-4 text-sm text-slate-300">
                                Conversión finalizada: el cliente ya ha firmado. Desde aquí solo se muestra el resumen; no se pueden reenviar enlaces ni reiniciar tokens.
                            </div>
                        @else
                                                <div class="mt-4 text-sm text-slate-600 dark:text-slate-300">
                                                    El cliente debe completar sus datos y firmar el contrato para finalizar la conversión.
                                                    <div class="mt-2">
                                                        <span class="font-extrabold">Reenviar email</span>: úsalo si el cliente no encuentra el correo o necesita que se lo volvamos a mandar.
                                                        <br>
                                                        <span class="font-extrabold">Reiniciar token</span>: úsalo si el enlace ha caducado, se ha compartido por error o quieres generar un enlace nuevo por seguridad.
                                                        <br>
                                                        <span class="font-extrabold">Cancelar conversión</span>: revoca el enlace actual y vuelve a modo edición para corregir servicios/condiciones antes de enviar de nuevo.
                                                    </div>
                                                </div>
                        @endif



                    </div>

                    {{-- Acciones (bloqueadas si finalizada) --}}
                    <div class="flex flex-col gap-2 shrink-0">

                        @if(! $accionesBloqueadas)
                            <button type="button"
                                    wire:click="abrirConfirmarReenviar"
                                    class="px-3 py-2 text-sm rounded-xl border border-blue-200 dark:border-blue-900 bg-white/70 dark:bg-gray-900/40 font-extrabold">
                                Reenviar email
                            </button>

                            <button type="button"
                                    wire:click="abrirConfirmarReiniciarToken"
                                    class="px-3 py-2 text-sm rounded-xl border border-rose-200 dark:border-rose-900 bg-white/70 dark:bg-gray-900/40 font-extrabold">
                                Reiniciar token
                            </button>

                            {{-- Cancelar conversión (IA rojo moderno) --}}
                            <button type="button"
                                    wire:click="abrirConfirmarCancelarConversion"
                                    class="group relative px-3 py-2 text-sm rounded-xl font-extrabold text-white
                                           border border-rose-200/30 dark:border-rose-400/20
                                           bg-gradient-to-r from-rose-600 via-fuchsia-600 to-indigo-600
                                           shadow-[0_10px_25px_-18px_rgba(244,63,94,.9)]
                                           hover:shadow-[0_16px_35px_-22px_rgba(217,70,239,.9)]
                                           hover:brightness-[1.06] active:brightness-[.98]
                                           transition">
                                <span class="pointer-events-none absolute -inset-0.5 rounded-xl opacity-40 blur
                                             bg-gradient-to-r from-rose-500 via-fuchsia-500 to-indigo-500
                                             group-hover:opacity-60 transition"></span>

                                <span class="relative flex items-center gap-2">
                                    <svg class="h-4 w-4 opacity-95" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm2.707-10.707a1 1 0 00-1.414-1.414L10 7.172 8.707 5.879A1 1 0 107.293 7.293L8.586 8.586 7.293 9.879a1 1 0 101.414 1.414L10 10l1.293 1.293a1 1 0 001.414-1.414L11.414 8.586l1.293-1.293z" clip-rule="evenodd" />
                                    </svg>
                                    Cancelar conversión
                                </span>
                            </button>
                        @endif

                        {{-- Ver Lead (IA style azul) --}}
                        <button
                            type="button"
                            onclick="window.open(@js($leadViewUrl), '_blank')"
                            class="group relative px-3 py-2 text-sm rounded-xl font-extrabold text-white
                                   border border-sky-200/40 dark:border-sky-400/20
                                   bg-gradient-to-r from-sky-500 via-indigo-500 to-fuchsia-500
                                   shadow-[0_10px_25px_-18px_rgba(56,189,248,.9)]
                                   hover:shadow-[0_16px_35px_-22px_rgba(168,85,247,.9)]
                                   hover:brightness-[1.06] active:brightness-[.98]
                                   transition"
                        >
                            <span class="pointer-events-none absolute -inset-0.5 rounded-xl opacity-40 blur
                                         bg-gradient-to-r from-sky-400 via-indigo-400 to-fuchsia-400
                                         group-hover:opacity-60 transition"></span>

                            <span class="relative flex items-center gap-2">
                                <svg class="h-4 w-4 opacity-95" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M11 3a1 1 0 011 1v3h3a1 1 0 110 2h-3v3a1 1 0 11-2 0V9H7a1 1 0 110-2h3V4a1 1 0 011-1z" />
                                </svg>
                                Ver Lead
                            </span>
                        </button>

                    </div>
                </div>
            </div>
        @endif

        {{-- TABLA + BOTÓN SOLO SI NO ESTÁ ENVIADA NI FINALIZADA --}}
        @if(! $conversionEnviada && ! $conversionFinalizada)
            <div class="overflow-hidden rounded-xl border border-sky-400/70 bg-white/80 shadow-[0_12px_35px_-20px_rgba(2,132,199,.45)]
                        dark:border-sky-500/25 dark:bg-gray-900 dark:shadow-xl">
                @include('filament.resources.leads.partials.servicios-table', ['items' => $items])
            </div>

            <div class="flex justify-end">
                <x-filament::button
                    wire:click="abrirConfirmarPropuesta"
                    size="lg"
                    color="primary"
                >
                    Generar Propuesta
                </x-filament::button>
            </div>

            {{-- MODAL 1: RESUMEN --}}
            <x-filament::modal id="confirmar-propuesta" width="5xl">
                @include('filament.resources.leads.partials.modals.confirmar-propuesta', [
                    'lead' => $lead,
                    'items' => $items,
                    'totales' => $this->totales,
                    'servicios' => $this->servicios,
                    'emailDestino' => $this->emailDestino,
                ])

                <x-slot name="footer">
                    <div class="flex w-full items-center justify-between gap-3">
                        <x-filament::button
                            color="gray"
                            x-on:click="$dispatch('close-modal', { id: 'confirmar-propuesta' })"
                        >
                            Cancelar
                        </x-filament::button>

                        <x-filament::button color="primary" wire:click="confirmarDesdeResumen">
                            Confirmar y revisar email
                        </x-filament::button>
                    </div>
                </x-slot>
            </x-filament::modal>

            {{-- MODAL 2: CONFIRMAR EMAIL --}}
            <x-filament::modal id="confirmar-email" width="xl">
                @include('filament.resources.leads.partials.modals.confirmar-email', [
                    'lead' => $lead,
                    'emailDestino' => $this->emailDestino,
                ])

                <x-slot name="footer">
                    <div class="flex w-full items-center justify-between gap-3">
                        <x-filament::button
                            color="gray"
                            x-on:click="$dispatch('close-modal', { id: 'confirmar-email' })"
                        >
                            Volver
                        </x-filament::button>

                        <x-filament::button color="success" wire:click="enviarPropuestaConfirmada">
                            Enviar ahora
                        </x-filament::button>
                    </div>
                </x-slot>
            </x-filament::modal>
        @endif

        {{-- MODAL: CONFIRMAR REENVIAR --}}
        <x-filament::modal id="confirmar-reenviar-email" width="lg">
            <div class="space-y-3">
                <div class="text-base font-extrabold text-slate-900 dark:text-white">Reenviar email</div>
                <div class="text-sm text-slate-600 dark:text-slate-300">
                    Reenvía el mismo enlace (mismo token) al email de destino. Útil si el cliente no lo encuentra.
                    Si el enlace estuviera caducado, el sistema generará uno nuevo automáticamente.
                </div>

                <div class="rounded-xl border border-slate-200 bg-white/60 p-3 dark:border-slate-800 dark:bg-gray-900/40">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Destinatario</div>
                    <div class="mt-1 text-base font-extrabold text-slate-900 dark:text-white break-all">
                        {{ $conversionInfo['last_sent_to'] ?? ($this->emailDestino ?? '—') }}
                    </div>
                </div>
            </div>

            <x-slot name="footer">
                <div class="flex w-full items-center justify-between gap-3">
                    <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'confirmar-reenviar-email' })">
                        Cancelar
                    </x-filament::button>
                    <x-filament::button color="primary" wire:click="reenviarPropuestaVisual">
                        Sí, reenviar
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>

        {{-- MODAL: CONFIRMAR REINICIAR TOKEN --}}
        <x-filament::modal id="confirmar-reiniciar-token" width="lg">
            <div class="space-y-3">
                <div class="text-base font-extrabold text-slate-900 dark:text-white">Reiniciar token</div>
                <div class="text-sm text-slate-600 dark:text-slate-300">
                    Genera un enlace completamente nuevo y revoca el anterior. Útil si el enlace se ha compartido por error
                    o quieres aumentar la seguridad.
                </div>

                <div class="rounded-xl border border-rose-200 bg-rose-50/70 p-3 dark:border-rose-900/60 dark:bg-rose-950/25">
                    <div class="text-sm font-extrabold text-rose-900 dark:text-rose-200">
                        Esto invalidará el enlace actual.
                    </div>
                </div>
            </div>

            <x-slot name="footer">
                <div class="flex w-full items-center justify-between gap-3">
                    <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'confirmar-reiniciar-token' })">
                        Cancelar
                    </x-filament::button>
                    <x-filament::button color="danger" wire:click="reiniciarTokenVisual">
                        Sí, reiniciar
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>

        {{-- MODAL: CONFIRMAR CANCELAR CONVERSIÓN --}}
        <x-filament::modal id="confirmar-cancelar-conversion" width="2xl">
            <div class="space-y-3">
                <div class="text-base font-extrabold text-slate-900 dark:text-white">Cancelar conversión</div>

                <div class="text-sm text-slate-600 dark:text-slate-300">
                    Revoca el enlace actual y vuelve a modo edición para corregir servicios/condiciones.
                    Después podrás generar una nueva propuesta.
                </div>

                <div class="rounded-xl border border-rose-200 bg-rose-50/70 p-3 dark:border-rose-900/60 dark:bg-rose-950/25">
                    <div class="text-sm font-extrabold text-rose-900 dark:text-rose-200">
                        Ojo: el cliente ya no podrá usar el enlace actual.
                    </div>
                </div>

                <div class="rounded-xl border border-slate-200 bg-white/60 p-3 dark:border-slate-800 dark:bg-gray-900/40">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        Motivo (opcional)
                    </div>
                    <textarea
                        wire:model.defer="cancelReason"
                        rows="3"
                        class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-base
                               dark:border-slate-800 dark:bg-gray-900 dark:text-white"
                        placeholder="Ej.: Servicio mal seleccionado / descuento incorrecto / email equivocado..."
                    ></textarea>
                </div>
            </div>

            <x-slot name="footer">
                <div class="flex w-full items-center justify-between gap-3">
                    <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'confirmar-cancelar-conversion' })">
                        Volver
                    </x-filament::button>

                    <x-filament::button color="danger" wire:click="cancelarConversionConfirmada">
                        Sí, cancelar conversión
                    </x-filament::button>
                </div>
            </x-slot>
        </x-filament::modal>

    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
