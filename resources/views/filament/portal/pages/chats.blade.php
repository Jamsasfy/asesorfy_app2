<x-filament-panels::page>
    @php
        $accent = '#41c0e9';
        $cliente = $this->cliente ?? null;
        $chat = $this->chat ?? null;
        $hasAsesor = (bool) ($this->hasAsesor ?? false);
        $isLinked = (bool) ($this->isLinked ?? false);
        $asesor = $cliente?->asesor;
        $asesorNombre = $asesor?->name ?? 'Sin asesor asignado';
        $asesorEmail = $asesor?->email ?? null;
        $clienteNombre = $cliente?->razon_social ?? $cliente?->nombre ?? 'Tu cuenta';
        $botUsername = $this->botUsername ?? null;
        $telegramState = $this->telegramState ?? 'no_iniciado';
        $tgApp = method_exists($this, 'getTelegramDeepLink') ? $this->getTelegramDeepLink() : null;
        $tgWeb = method_exists($this, 'getTelegramWebUrl') ? $this->getTelegramWebUrl() : null;
        $linkUrl = $this->telegramLinkUrl ?? null;
        $expiresAt = $this->telegramLinkExpiresAt ?? null;
        $expiresHuman = $expiresAt
            ? \Illuminate\Support\Carbon::parse($expiresAt)
                ->timezone(config('app.timezone', 'Europe/Madrid'))
                ->format('d/m/Y H:i')
            : null;
        $kpiCard = 'relative overflow-hidden rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 border border-sky-200/60 dark:border-sky-400/20';
    @endphp

    {{-- HERO --}}
    <div class="relative overflow-hidden rounded-3xl bg-white p-8 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-6">
        <div class="absolute -right-24 -top-24 h-56 w-56 rounded-full opacity-20 blur-3xl" style="background: {{ $accent }}"></div>
        <div class="relative flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-xl font-black tracking-tight text-gray-950 dark:text-white">Chats y mi Asesor</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    La comunicación principal se realiza por Telegram.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 mt-2 sm:mt-0">
                <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 px-3 py-1 text-xs font-bold text-gray-700 ring-1 ring-inset ring-gray-200 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10">
                    <x-filament::icon icon="heroicon-m-user-circle" class="h-4 w-4" />
                    {{ $clienteNombre }}
                </span>
                @if($hasAsesor)
                    @if($isLinked)
                        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Telegram conectado
                        </span>
                    @else
                        <span class="inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800 ring-1 ring-inset ring-amber-600/20">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                            Telegram pendiente
                        </span>
                    @endif
                @endif
            </div>
        </div>

        {{-- KPIs --}}
        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="{{ $kpiCard }}">
                <div class="flex items-center justify-between mb-3">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Estado</div>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 ring-1 ring-inset ring-sky-200">
                        <x-filament::icon icon="heroicon-m-link" class="h-5 w-5 text-sky-700" />
                    </div>
                </div>
                <div class="text-2xl font-black {{ !$hasAsesor ? 'text-gray-400' : ($isLinked ? '' : 'text-amber-500') }}"
                    style="{{ $isLinked ? 'color: '.$accent.';' : '' }}">
                    @if(!$hasAsesor) Sin asesor
                    @elseif($isLinked) Conectado ✓
                    @else Sin vincular
                    @endif
                </div>
                <div class="mt-1 text-xs text-gray-500">
                    @if(!$hasAsesor) Necesitas un asesor para usar Telegram.
                    @elseif($isLinked) Ya puedes escribir desde Telegram.
                    @else Vincula tu cuenta para empezar.
                    @endif
                </div>
            </div>

            <div class="{{ $kpiCard }}">
                <div class="flex items-center justify-between mb-3">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Última actividad</div>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 ring-1 ring-inset ring-sky-200">
                        <x-filament::icon icon="heroicon-m-clock" class="h-5 w-5 text-sky-700" />
                    </div>
                </div>
                <div class="text-lg font-black text-gray-900 dark:text-white">
                    {{ $chat?->last_message_at?->timezone(config('app.timezone','Europe/Madrid'))->format('d/m/Y H:i') ?? '—' }}
                </div>
                <div class="mt-1 text-xs text-gray-500">Último mensaje registrado.</div>
            </div>

            <div class="{{ $kpiCard }}">
                <div class="flex items-center justify-between mb-3">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Tu asesor</div>
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 ring-1 ring-inset ring-sky-200">
                        <x-filament::icon icon="heroicon-m-user" class="h-5 w-5 text-sky-700" />
                    </div>
                </div>
                <div class="text-base font-black text-gray-900 dark:text-white truncate">{{ $asesorNombre }}</div>
                <div class="mt-1 text-xs text-gray-500 truncate">{{ $asesorEmail ?? ($hasAsesor ? '—' : 'Contacta con atención al cliente') }}</div>
            </div>
        </div>
    </div>

    {{-- SIN ASESOR --}}
    @if(!$hasAsesor)
        <div class="rounded-3xl border border-amber-200 bg-amber-50 p-6 text-amber-900 dark:border-amber-800/50 dark:bg-amber-900/20 dark:text-amber-200">
            <div class="flex gap-4">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-100 ring-1 ring-amber-300 shrink-0">
                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-6 w-6 text-amber-700" />
                </div>
                <div>
                    <h4 class="text-sm font-black">Aún no tienes un asesor asignado</h4>
                    <p class="mt-2 text-sm">Para poder usar Telegram necesitas que te asignen un asesor. Contacta con nosotros en <a href="mailto:info@asesorfy.net" class="font-bold underline">info@asesorfy.net</a></p>
                </div>
            </div>
        </div>

    {{-- CON ASESOR --}}
    @else
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">

            {{-- IZQUIERDA: Acción principal --}}
            <div class="lg:col-span-7 space-y-4">

                @if($isLinked)
                    {{-- VINCULADO --}}
                    <div class="rounded-3xl bg-white p-8 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 text-center">
                        <div class="flex items-center justify-center w-16 h-16 rounded-full bg-emerald-50 ring-1 ring-emerald-200 mx-auto mb-4">
                            <x-filament::icon icon="heroicon-m-check-circle" class="h-9 w-9 text-emerald-500" />
                        </div>
                        <h3 class="text-lg font-black text-gray-900 dark:text-white">Telegram vinculado</h3>
                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                            Tu cuenta está conectada. Puedes escribir a tu asesor directamente desde Telegram.
                        </p>
                        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
                            @if($tgApp)
                                <a href="{{ $tgApp }}" target="_blank"
                                   class="inline-flex items-center justify-center gap-2 rounded-xl px-6 py-3 text-sm font-black text-white shadow-sm transition-all hover:opacity-90 active:scale-95"
                                   style="background-color: {{ $accent }};">
                                    <x-filament::icon icon="heroicon-m-chat-bubble-left-right" class="h-5 w-5" />
                                    Abrir Telegram
                                </a>
                            @endif
                            @if($tgWeb)
                                <a href="{{ $tgWeb }}" target="_blank"
                                   class="inline-flex items-center justify-center gap-2 rounded-xl bg-gray-100 px-6 py-3 text-sm font-black text-gray-700 transition-all hover:bg-gray-200 dark:bg-gray-800 dark:text-white">
                                    <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="h-5 w-5" />
                                    Abrir en navegador
                                </a>
                            @endif
                        </div>

                       {{-- Botón revinculación --}}
<div class="mt-6 border-t border-gray-100 dark:border-gray-800 pt-4 flex justify-center">
    <button wire:click="abrirModalRevincular"
            class="inline-flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs font-semibold text-amber-800 hover:bg-amber-100 transition-all dark:border-amber-800/50 dark:bg-amber-900/20 dark:text-amber-300">
        <x-filament::icon icon="heroicon-m-arrow-path" class="h-4 w-4" />
        ¿Has cambiado de móvil o cuenta de Telegram? Si te ha dejado de funcionar solicita revinculación a tu asesor
    </button>
</div>
                    </div>

                @else
                    {{-- NO VINCULADO --}}
                    <div class="rounded-3xl bg-white p-8 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <div class="flex items-center gap-3 mb-4">
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl shrink-0" style="background-color: {{ $accent }}20;">
                                <x-filament::icon icon="heroicon-m-paper-airplane" class="h-6 w-6" style="color: {{ $accent }};" />
                            </div>
                            <div>
                                <h3 class="text-base font-black text-gray-900 dark:text-white">Vincula tu Telegram</h3>
                                <p class="text-xs text-gray-500">Solo tienes que pulsar el botón y aceptar en Telegram.</p>
                            </div>
                        </div>

                        {{-- Pasos --}}
                        <div class="mb-6 space-y-3">
                            <div class="flex items-start gap-3">
                                <div class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-black text-white shrink-0 mt-0.5" style="background-color: {{ $accent }};">1</div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Pulsa el botón <strong class="text-gray-900 dark:text-white">"Vincular con Telegram"</strong></p>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-black text-white shrink-0 mt-0.5" style="background-color: {{ $accent }};">2</div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Se abrirá Telegram con el bot de AsesorFy.</p>
                            </div>
                            <div class="flex items-start gap-3">
                                <div class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-black text-white shrink-0 mt-0.5" style="background-color: {{ $accent }};">3</div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Pulsa <strong class="text-gray-900 dark:text-white">START</strong> en Telegram y listo. Ya puedes escribir a tu asesor.</p>
                            </div>
                        </div>

                        {{-- Botones principales --}}
                        <div class="flex flex-col gap-3 sm:flex-row">
                            @if($tgApp)
                                <a href="{{ $tgApp }}" target="_blank"
                                   class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl px-6 py-3.5 text-sm font-black text-white shadow-sm transition-all hover:opacity-90 active:scale-95"
                                   style="background-color: {{ $accent }};">
                                    <x-filament::icon icon="heroicon-m-paper-airplane" class="h-5 w-5" />
                                    Vincular con Telegram
                                </a>
                            @else
                                <button wire:click="regenerateTelegramLink"
                                        class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl px-6 py-3.5 text-sm font-black text-white shadow-sm transition-all hover:opacity-90 active:scale-95"
                                        style="background-color: {{ $accent }};">
                                    <x-filament::icon icon="heroicon-m-paper-airplane" class="h-5 w-5" />
                                    Generar enlace y vincular
                                </button>
                            @endif
                            @if($tgWeb)
                                <a href="{{ $tgWeb }}" target="_blank"
                                   class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-gray-100 px-6 py-3.5 text-sm font-black text-gray-700 transition-all hover:bg-gray-200 dark:bg-gray-800 dark:text-white">
                                    <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="h-5 w-5" />
                                    Abrir en navegador
                                </a>
                            @endif
                        </div>

                        {{-- Enlace caducado --}}
                        @if($telegramState === 'expirado')
                            <div class="mt-4 flex items-center justify-between rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                                <p class="text-xs text-amber-800 font-medium">El enlace de vinculación ha caducado.</p>
                                <button wire:click="regenerateTelegramLink"
                                        class="text-xs font-black text-amber-900 underline hover:no-underline">
                                    Generar nuevo
                                </button>
                            </div>
                        @elseif($telegramState === 'no_iniciado')
                            <div class="mt-4 flex justify-end">
                                <button wire:click="regenerateTelegramLink"
                                        class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors">
                                    Generar enlace nuevo
                                </button>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Descargar Telegram --}}
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <h3 class="text-sm font-black text-gray-900 dark:text-white mb-4">¿No tienes Telegram instalado?</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <a href="https://apps.apple.com/app/telegram-messenger/id686449807" target="_blank"
                           class="flex items-center gap-3 rounded-2xl bg-gray-50 p-4 ring-1 ring-gray-200 hover:ring-gray-300 transition-all">
                            <x-filament::icon icon="heroicon-m-device-phone-mobile" class="h-6 w-6 shrink-0" style="color: {{ $accent }};" />
                            <div>
                                <div class="text-xs font-black text-gray-900 dark:text-white">iPhone / iPad</div>
                                <div class="text-xs text-gray-500">App Store</div>
                            </div>
                        </a>
                        <a href="https://play.google.com/store/apps/details?id=org.telegram.messenger" target="_blank"
                           class="flex items-center gap-3 rounded-2xl bg-gray-50 p-4 ring-1 ring-gray-200 hover:ring-gray-300 transition-all">
                            <x-filament::icon icon="heroicon-m-device-phone-mobile" class="h-6 w-6 shrink-0" style="color: {{ $accent }};" />
                            <div>
                                <div class="text-xs font-black text-gray-900 dark:text-white">Android</div>
                                <div class="text-xs text-gray-500">Google Play</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            {{-- DERECHA: Asesor + info --}}
            <div class="lg:col-span-5 space-y-4">

                {{-- Card asesor --}}
                <div class="rounded-3xl bg-gradient-to-br from-sky-50 to-cyan-50 p-6 ring-1 ring-sky-200/50 dark:from-sky-950/20 dark:to-cyan-950/20 dark:ring-sky-400/20">
                    <h4 class="text-sm font-black text-gray-900 dark:text-white mb-4">Tu asesor</h4>
                    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <div class="flex items-start gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-sky-50 ring-1 ring-inset ring-sky-200 shrink-0">
                                <x-filament::icon icon="heroicon-m-user" class="h-6 w-6 text-sky-700" />
                            </div>
                            <div class="min-w-0">
                                <div class="text-sm font-black text-gray-900 dark:text-white truncate">{{ $asesorNombre }}</div>
                                @if($asesorEmail)
                                    <div class="mt-1 text-xs text-gray-500 truncate">{{ $asesorEmail }}</div>
                                @endif
                            </div>
                        </div>
                        @if($asesorEmail)
                            <a href="mailto:{{ $asesorEmail }}?subject={{ rawurlencode('CONSULTA DESDE ' . ($cliente?->razon_social ?? $cliente?->nombre . ' ' . $cliente?->apellidos) . ' ' . ($cliente?->dni_cif ?? '')) }}"
                               class="mt-4 flex items-center justify-center gap-2 rounded-xl bg-gray-100 px-4 py-2 text-sm font-bold text-gray-900 hover:bg-gray-200 transition-all dark:bg-gray-800 dark:text-white">
                                <x-filament::icon icon="heroicon-m-envelope" class="h-4 w-4" />
                                Enviar email
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Info --}}
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <h4 class="flex items-center gap-2 text-sm font-black text-gray-900 dark:text-white mb-4">
                        <x-filament::icon icon="heroicon-m-information-circle" class="h-5 w-5 text-gray-400" />
                        Cómo funciona
                    </h4>
                    <ul class="space-y-3 text-xs text-gray-600 dark:text-gray-400">
                        <li class="flex gap-3">
                            <div class="h-1.5 w-1.5 rounded-full mt-1.5 shrink-0" style="background: {{ $accent }}"></div>
                            <div><strong class="block font-semibold text-gray-900 dark:text-gray-200 mb-0.5">Canal preferente</strong>Telegram es el canal más rápido para hablar con tu asesor.</div>
                        </li>
                        <li class="flex gap-3">
                            <div class="h-1.5 w-1.5 rounded-full mt-1.5 shrink-0 bg-gray-300"></div>
                            <div><strong class="block font-semibold text-gray-900 dark:text-gray-200 mb-0.5">Horario de respuesta</strong>Lunes a viernes, 9:00 - 18:00h.</div>
                        </li>
                        <li class="flex gap-3">
                            <div class="h-1.5 w-1.5 rounded-full mt-1.5 shrink-0 bg-gray-300"></div>
                            <div><strong class="block font-semibold text-gray-900 dark:text-gray-200 mb-0.5">Archivos admitidos</strong>PDF, Word, Excel e imágenes (máx. 25 MB).</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- MODAL REVINCULACIÓN --}}
    @if($this->mostrarModalRevincular)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
             style="background: rgba(0,0,0,0.5);">
            <div class="w-full max-w-md rounded-3xl bg-white dark:bg-gray-900 shadow-xl p-6">

                <div class="flex items-start gap-3 mb-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-50 ring-1 ring-amber-200 shrink-0">
                        <x-filament::icon icon="heroicon-m-arrow-path" class="h-5 w-5 text-amber-600" />
                    </div>
                    <div>
                        <h3 class="text-base font-black text-gray-900 dark:text-white">Solicitar revinculación</h3>
                        <p class="text-xs text-gray-500 mt-0.5">Tu asesor recibirá una notificación en Telegram y te enviará el nuevo enlace de vinculación <strong>por email</strong>.</p>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                        Motivo <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        wire:model="motivoRevincular"
                        rows="3"
                        maxlength="300"
                        placeholder="Ej: He cambiado de móvil y no puedo acceder a mi cuenta de Telegram anterior..."
                        class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
                    ></textarea>
                    @error('motivoRevincular')
                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex gap-3">
                    <button wire:click="solicitarRevincular"
                            class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-black text-white transition-all hover:opacity-90"
                            style="background-color: {{ $accent }};">
                        <x-filament::icon icon="heroicon-m-paper-airplane" class="h-4 w-4" />
                        Enviar solicitud
                    </button>
                    <button wire:click="cerrarModalRevincular"
                            class="flex-1 inline-flex items-center justify-center rounded-xl bg-gray-100 dark:bg-gray-800 px-4 py-2.5 text-sm font-black text-gray-700 dark:text-white hover:bg-gray-200 transition-all">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    @endif

</x-filament-panels::page>