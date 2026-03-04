{{-- resources/views/filament/portal/pages/chats.blade.php --}}
<x-filament-panels::page>
    @php
        $accent = '#41c0e9';

        /** @var \App\Models\Cliente|null $cliente */
        $cliente = $cliente ?? ($this->cliente ?? null);

        /** @var \App\Models\ChatConversacion|null $chat */
        $chat = $chat ?? ($this->chat ?? null);

        // ✅ NUEVO: Verificar si tiene asesor
        $hasAsesor = (bool) ($this->hasAsesor ?? false);

        // Fuente de verdad: si hay telegram_chat_id, está vinculado
        $vinculado = (bool) ($chat?->telegram_chat_id);

        // Fallback por si tenías algo viejo en la Page (no manda por encima del chat_id)
        $isLinked = (bool) ($this->isLinked ?? false);
        $isLinked = $vinculado ?: $isLinked;

        $asesor = $cliente?->asesor;
        $asesorNombre = $asesor?->name ?? 'Sin asesor asignado';
        $asesorEmail = $asesor?->email ?? null;

        $clienteNombre = $cliente?->razon_social ?? $cliente?->nombre ?? 'Tu cuenta';

        // Datos de vinculación (si aplica)
        $botUsername = $this->botUsername ?? null;
        $linkUrl = $this->telegramLinkUrl ?? null;
        $expiresAt = $this->telegramLinkExpiresAt ?? null;

        $expiresHuman = $expiresAt
            ? \Illuminate\Support\Carbon::parse($expiresAt)->timezone(config('app.timezone','Europe/Madrid'))->format('d/m/Y H:i')
            : null;

        // Links "Abrir Telegram"
        $tgApp = method_exists($this, 'getTelegramDeepLink') ? $this->getTelegramDeepLink() : null;
        $tgWeb = method_exists($this, 'getTelegramWebUrl') ? $this->getTelegramWebUrl() : null;

        // UI helpers (mismo estilo que Suscripción / Método de pago)
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
                            Chats y mi Asesor
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            La comunicación principal se realiza por Telegram, para que lo tengas siempre en el móvil.
                        </p>
                    </div>

                    <div class="mt-4 sm:mt-0 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 px-3 py-1 text-xs font-bold text-gray-700 ring-1 ring-inset ring-gray-200 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10">
                            <x-filament::icon icon="heroicon-m-user-circle" class="h-4 w-4" />
                            {{ $clienteNombre }}
                        </span>

                        {{-- ✅ NUEVO: Mostrar estado solo si tiene asesor --}}
                        @if($hasAsesor)
                            @if($isLinked)
                                <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/20">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Telegram conectado
                                </span>
                            @else
                                <span class="inline-flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                    Telegram pendiente
                                </span>
                            @endif
                        @else
                            <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 px-3 py-1 text-xs font-bold text-gray-700 ring-1 ring-inset ring-gray-200 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10">
                                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-4 w-4" />
                                Sin asesor asignado
                            </span>
                        @endif
                    </div>
                </div>

                {{-- KPIs / Estado --}}
                <div class="mt-8 grid grid-cols-1 gap-4 md:grid-cols-3">
                    {{-- Estado --}}
                    <div class="{{ $kpiCardClass }} {{ $kpiGlow }} {{ $kpiTopLine }} {{ $kpiBorder }}">
                        <div class="relative">
                            <div class="flex items-center justify-between">
                                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                                    Estado de conexión
                                </div>
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 ring-1 ring-inset ring-sky-200 dark:bg-sky-950/40 dark:ring-sky-400/20">
                                    <x-filament::icon icon="heroicon-m-link" class="h-5 w-5 text-sky-700 dark:text-sky-200" />
                                </div>
                            </div>

                            <div class="mt-3 text-2xl font-black tabular-nums" style="color: {{ $accent }};">
                                @if(!$hasAsesor)
                                    Sin asesor
                                @else
                                    {{ $isLinked ? 'Conectado' : 'Sin vincular' }}
                                @endif
                            </div>

                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                @if(!$hasAsesor)
                                    Necesitas un asesor para usar Telegram.
                                @else
                                    {{ $isLinked ? 'Ya puedes escribir desde Telegram.' : 'Vincula tu cuenta para empezar.' }}
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Último mensaje --}}
                    <div class="{{ $kpiCardClass }} {{ $kpiGlow }} {{ $kpiTopLine }} {{ $kpiBorder }}">
                        <div class="relative">
                            <div class="flex items-center justify-between">
                                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                                    Última actividad
                                </div>
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 ring-1 ring-inset ring-sky-200 dark:bg-sky-950/40 dark:ring-sky-400/20">
                                    <x-filament::icon icon="heroicon-m-clock" class="h-5 w-5 text-sky-700 dark:text-sky-200" />
                                </div>
                            </div>

                            <div class="mt-3 text-lg font-black tabular-nums text-gray-900 dark:text-white">
                                @if($chat?->last_message_at)
                                    {{ $chat->last_message_at->timezone(config('app.timezone','Europe/Madrid'))->format('d/m/Y H:i') }}
                                @else
                                    —
                                @endif
                            </div>

                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Último mensaje registrado.
                            </div>
                        </div>
                    </div>

                    {{-- Asesor --}}
                    <div class="{{ $kpiCardClass }} {{ $kpiGlow }} {{ $kpiTopLine }} {{ $kpiBorder }}">
                        <div class="relative">
                            <div class="flex items-center justify-between">
                                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                                    Tu asesor asignado
                                </div>
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-sky-50 ring-1 ring-inset ring-sky-200 dark:bg-sky-950/40 dark:ring-sky-400/20">
                                    <x-filament::icon icon="heroicon-m-user" class="h-5 w-5 text-sky-700 dark:text-sky-200" />
                                </div>
                            </div>

                            <div class="mt-3 text-base font-black text-gray-900 dark:text-white truncate">
                                {{ $asesorNombre }}
                            </div>

                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $asesorEmail ?: ($hasAsesor ? 'Email no disponible' : 'Contacta con atención al cliente') }}
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ✅ NUEVO: Bloque de aviso si NO tiene asesor --}}
                @if(!$hasAsesor)
                    <div class="mt-8 rounded-3xl border border-amber-200 bg-amber-50 p-6 text-amber-900 dark:border-amber-800/50 dark:bg-amber-900/20 dark:text-amber-200">
                        <div class="flex gap-4">
                            <div class="shrink-0">
                                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-100 ring-1 ring-amber-300 dark:bg-amber-900/40 dark:ring-amber-700">
                                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-6 w-6 text-amber-700 dark:text-amber-400" />
                                </div>
                            </div>
                            <div>
                                <h4 class="text-sm font-black">Aún no tienes un asesor asignado</h4>
                                <p class="mt-2 text-sm">
                                    Para poder usar Telegram y comunicarte con tu asesor, primero necesitas que te asignen uno.
                                </p>
                                <p class="mt-2 text-sm font-semibold">
                                    Contacta con atención al cliente para que te asignen tu asesor personal.
                                </p>
                            </div>
                        </div>
                    </div>
                @else
                    {{-- BLOQUE VINCULACIÓN (solo si tiene asesor) --}}
                    <div class="mt-8 rounded-3xl bg-gray-50 p-6 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <h3 class="text-sm font-black text-gray-900 dark:text-white">
                                    Telegram
                                </h3>
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                                    @if($isLinked)
                                        Abre tu chat y escribe a tu asesor desde Telegram.
                                    @else
                                        Vincula tu cuenta para poder escribir desde el móvil (es lo más cómodo).
                                    @endif
                                </p>
                            </div>

                            <div class="shrink-0">
                                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                                    <x-filament::icon icon="heroicon-m-paper-airplane" class="h-6 w-6" style="color: {{ $accent }};" />
                                </div>
                            </div>
                        </div>

                        {{-- BLOQUE "ABRIR TELEGRAM" (APP / WEB) --}}
                        <div class="mt-6 rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h4 class="text-sm font-black text-gray-900 dark:text-white flex items-center gap-2">
                                        <x-filament::icon icon="heroicon-m-paper-airplane" class="h-5 w-5" />
                                        Abrir Telegram
                                    </h4>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        {{ $isLinked
                                            ? 'Abre tu chat con tu asesor en Telegram.'
                                            : 'Aún no has vinculado Telegram. Pulsa para vincular y empezar a escribir desde el móvil.' }}
                                    </p>
                                </div>

                                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-black ring-1 ring-inset
                                    {{ $isLinked
                                        ? 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/20'
                                        : 'bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-400/20' }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $isLinked ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500' }}"></span>
                                    {{ $isLinked ? 'Vinculado' : 'Pendiente' }}
                                </span>
                            </div>

                            <div class="mt-5 flex flex-col gap-3 sm:flex-row">
                                @if($tgApp)
                                    <x-filament::button
                                        tag="a"
                                        :href="$tgApp"
                                        target="_blank"
                                        icon="heroicon-m-chat-bubble-left-right"
                                        size="lg"
                                        class="w-full shadow-sm transition-transform active:scale-95"
                                        style="background-color: {{ $accent }};"
                                    >
                                        {{ $isLinked ? 'Abrir Telegram' : 'Vincular y abrir Telegram' }}
                                    </x-filament::button>
                                @endif

                                @if($tgWeb)
                                    <x-filament::button
                                        tag="a"
                                        :href="$tgWeb"
                                        target="_blank"
                                        color="gray"
                                        icon="heroicon-m-arrow-top-right-on-square"
                                        size="lg"
                                        class="w-full"
                                    >
                                        Abrir en navegador
                                    </x-filament::button>
                                @endif
                            </div>

                            <div class="mt-4 text-xs text-gray-500 dark:text-gray-400">
                                <span class="font-semibold" style="color: {{ $accent }};">Tip:</span>
                                En móvil, al abrir <span class="font-mono">t.me</span> normalmente se lanza la app de Telegram directamente.
                            </div>

                            {{-- Aviso solo si NO está vinculado y NO podemos construir ningún link --}}
                            @if(! $isLinked && blank($tgApp) && blank($tgWeb))
                                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800/50 dark:bg-amber-900/20 dark:text-amber-200">
                                    <div class="flex gap-3">
                                        <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                                        <div class="space-y-1">
                                            <p class="font-black">No se puede construir el enlace ahora</p>
                                            <p class="opacity-90">Falta información para generar el link de Telegram (bot / token). Dale a "Generar enlace nuevo".</p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- LINK "TÉCNICO" (solo si NO está vinculado) --}}
                        @if(! $isLinked)
                            @if(! filled($botUsername))
                                <div class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800/50 dark:bg-amber-900/20 dark:text-amber-200">
                                    <div class="flex gap-3">
                                        <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                                        <div class="space-y-1">
                                            <p class="font-black">Falta el usuario del bot</p>
                                            <p class="opacity-90">No puedo construir el enlace de vinculación si no se detecta el username del bot.</p>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="mt-6 space-y-3">
                                    <div class="rounded-2xl bg-white p-4 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                                        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">
                                            Enlace de vinculación (caduca)
                                        </div>

                                        <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div class="min-w-0">
                                                <div class="font-mono text-xs break-all text-gray-900 dark:text-white" id="telegram-link">
                                                    {{ $linkUrl ?? '—' }}
                                                </div>

                                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                    @if($expiresHuman)
                                                        Caduca: <span class="font-black text-gray-900 dark:text-white">{{ $expiresHuman }}</span>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="flex gap-2 shrink-0">
                                                @if($linkUrl)
                                                    <x-filament::button
                                                        tag="a"
                                                        :href="$linkUrl"
                                                        target="_blank"
                                                        icon="heroicon-m-arrow-top-right-on-square"
                                                        icon-position="after"
                                                        class="shadow-sm"
                                                        style="background-color: {{ $accent }};"
                                                    >
                                                        Vincular en Telegram
                                                    </x-filament::button>

                                                    <x-filament::button
                                                        color="gray"
                                                        icon="heroicon-m-clipboard-document"
                                                        onclick="navigator.clipboard?.writeText(document.getElementById('telegram-link')?.innerText || '')"
                                                    >
                                                        Copiar
                                                    </x-filament::button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex justify-end">
                                        <button
                                            type="button"
                                            wire:click="regenerateTelegramLink"
                                            class="text-xs font-semibold text-gray-600 hover:text-gray-900 dark:text-gray-300 dark:hover:text-white"
                                        >
                                            Generar un enlace nuevo
                                        </button>
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                @endif
            </div>

            {{-- "USAMOS TELEGRAM" (solo si tiene asesor) --}}
            @if($hasAsesor)
                <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <h3 class="text-sm font-black text-gray-900 dark:text-white">
                        Usamos Telegram
                    </h3>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Telegram nos permite responder rápido, enviar documentos y mantener un histórico de conversación.
                    </p>

                    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <a
                            href="https://apps.apple.com/app/telegram-messenger/id686449807"
                            target="_blank"
                            class="rounded-2xl bg-gray-50 p-4 ring-1 ring-gray-200 hover:ring-gray-300 dark:bg-white/5 dark:ring-white/10 dark:hover:ring-white/20"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                                    <x-filament::icon icon="heroicon-m-device-phone-mobile" class="h-5 w-5" style="color: {{ $accent }};" />
                                </div>
                                <div>
                                    <div class="text-sm font-black text-gray-900 dark:text-white">iPhone / iPad</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Descargar desde App Store</div>
                                </div>
                            </div>
                        </a>

                        <a
                            href="https://play.google.com/store/apps/details?id=org.telegram.messenger"
                            target="_blank"
                            class="rounded-2xl bg-gray-50 p-4 ring-1 ring-gray-200 hover:ring-gray-300 dark:bg-white/5 dark:ring-white/10 dark:hover:ring-white/20"
                        >
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                                    <x-filament::icon icon="heroicon-m-device-phone-mobile" class="h-5 w-5" style="color: {{ $accent }};" />
                                </div>
                                <div>
                                    <div class="text-sm font-black text-gray-900 dark:text-white">Android</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Descargar desde Google Play</div>
                                </div>
                            </div>
                        </a>
                    </div>
                </div>
            @endif
        </div>

        {{-- DERECHA --}}
        <div class="lg:col-span-5 xl:col-span-4 space-y-6">

            {{-- CARD ASESOR --}}
            <div class="rounded-3xl bg-gray-50 p-6 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                <h4 class="text-sm font-black text-gray-900 dark:text-white">
                    Tu asesor
                </h4>

                <div class="mt-4 rounded-2xl bg-white p-4 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="flex items-start gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-sky-50 ring-1 ring-inset ring-sky-200 dark:bg-sky-950/40 dark:ring-sky-400/20">
                            <x-filament::icon icon="heroicon-m-user" class="h-6 w-6 text-sky-700 dark:text-sky-200" />
                        </div>

                        <div class="min-w-0">
                            <div class="text-sm font-black text-gray-900 dark:text-white truncate">
                                {{ $asesorNombre }}
                            </div>
                            <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                @if($hasAsesor)
                                    Email de comunicaciones:
                                    <span class="font-black text-gray-900 dark:text-white">
                                        {{ $asesorEmail ?: '—' }}
                                    </span>
                                @else
                                    <span class="font-semibold text-amber-700 dark:text-amber-300">
                                        Contacta con atención al cliente para que te asignen un asesor.
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ✅ NUEVO: Normas de comunicación (solo si tiene asesor) --}}
                @if($hasAsesor)
                    <div class="mt-4 space-y-3 text-xs text-gray-600 dark:text-gray-400">
                        <div class="flex gap-3">
                            <div class="flex-none pt-1">
                                <div class="h-1.5 w-1.5 rounded-full" style="background: {{ $accent }}"></div>
                            </div>
                            <div>
                                <strong class="block font-semibold text-gray-900 dark:text-gray-200 mb-0.5">Canal principal</strong>
                                Telegram es el canal preferente para comunicaciones rápidas y envío de documentos.
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <div class="flex-none pt-1">
                                <div class="h-1.5 w-1.5 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                            </div>
                            <div>
                                <strong class="block font-semibold text-gray-900 dark:text-gray-200 mb-0.5">Horario de respuesta</strong>
                                Tu asesor te responderá en horario laboral (L-V, 9:00-18:00h).
                            </div>
                        </div>

                        <div class="flex gap-3">
                            <div class="flex-none pt-1">
                                <div class="h-1.5 w-1.5 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                            </div>
                            <div>
                                <strong class="block font-semibold text-gray-900 dark:text-gray-200 mb-0.5">Tipos de archivo</strong>
                                Puedes enviar PDF, Word, Excel e imágenes (máx. 25 MB).
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- INFO ÚTIL --}}
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h4 class="flex items-center gap-2 text-sm font-black text-gray-900 dark:text-white">
                    <x-filament::icon icon="heroicon-m-question-mark-circle" class="h-5 w-5 text-gray-400" />
                    Info útil
                </h4>

                <ul class="mt-4 space-y-4">
                    @if($hasAsesor)
                        <li class="flex gap-3">
                            <div class="flex-none pt-1">
                                <div class="h-1.5 w-1.5 rounded-full" style="background: {{ $accent }}"></div>
                            </div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                <strong class="block font-semibold text-gray-900 dark:text-gray-200 mb-0.5">Vinculación segura</strong>
                                El enlace caduca y solo sirve para asociar tu cuenta con tu conversación.
                            </div>
                        </li>

                        <li class="flex gap-3">
                            <div class="flex-none pt-1">
                                <div class="h-1.5 w-1.5 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                            </div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                <strong class="block font-semibold text-gray-900 dark:text-gray-200 mb-0.5">Si no abre Telegram</strong>
                                Usa "Abrir en navegador" desde el móvil o copia el enlace.
                            </div>
                        </li>
                    @else
                        <li class="flex gap-3">
                            <div class="flex-none pt-1">
                                <div class="h-1.5 w-1.5 rounded-full bg-amber-500"></div>
                            </div>
                            <div class="text-xs text-gray-600 dark:text-gray-400">
                                <strong class="block font-semibold text-gray-900 dark:text-gray-200 mb-0.5">Asignación de asesor</strong>
                                Contacta con atención al cliente para que te asignen tu asesor personal de AsesorFy.
                            </div>
                        </li>
                    @endif
                </ul>
            </div>

        </div>
    </div>
</x-filament-panels::page>