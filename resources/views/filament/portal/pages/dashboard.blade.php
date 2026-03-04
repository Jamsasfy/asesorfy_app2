{{-- resources/views/filament/portal/pages/dashboard.blade.php --}}
<x-filament-panels::page>

 {{-- SELECTOR DE EMPRESA (solo si tiene varios clientes) --}}
    @if(auth()->user()->clientes()->count() > 1)
        @php $clienteActivo = auth()->user()->clientes()->find(session('cliente_activo_id')); @endphp
        @if($clienteActivo)
        <div x-data="{ open: false }" class="flex items-center gap-3 rounded-xl border border-primary-200 dark:border-primary-800 bg-primary-50 dark:bg-primary-950 px-4 py-3 mb-2">
            
            <div class="flex items-center justify-center w-9 h-9 rounded-full bg-primary-500 text-white font-bold text-base shrink-0">
                {{ strtoupper(substr($clienteActivo->razon_social ?? $clienteActivo->nombre ?? '?', 0, 1)) }}
            </div>

            <div class="flex-1 min-w-0">
                <div class="text-xs font-medium text-primary-600 dark:text-primary-400 uppercase tracking-wide">Empresa activa</div>
                <div class="text-sm font-semibold text-gray-900 dark:text-white truncate">
                    {{ $clienteActivo->razon_social ?? $clienteActivo->nombre . ' ' . $clienteActivo->apellidos }}
                </div>
            </div>

            <div class="relative shrink-0">
                <button @click="open = !open" class="flex items-center gap-1 text-xs text-primary-600 dark:text-primary-400 hover:text-primary-800 font-medium transition-colors">
                    Cambiar
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="open" @click.outside="open = false" x-transition
                     class="absolute right-0 top-7 z-50 min-w-[220px] rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg py-1">
                    @foreach(auth()->user()->clientes()->get() as $c)
                        <a href="{{ url('/portal/seleccionar-empresa?cambiar=' . $c->id) }}"
                           class="w-full flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ $c->id === session('cliente_activo_id') ? 'bg-primary-50 dark:bg-primary-950' : '' }}">
                            <div class="flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold shrink-0 {{ $c->id === session('cliente_activo_id') ? 'bg-primary-500 text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-600' }}">
                                {{ strtoupper(substr($c->razon_social ?? $c->nombre ?? '?', 0, 1)) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                    {{ $c->razon_social ?? $c->nombre . ' ' . $c->apellidos }}
                                </div>
                                <div class="text-xs text-gray-500">{{ $c->dni_cif }}</div>
                            </div>
                            @if($c->id === session('cliente_activo_id'))
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-primary-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    @endif

    
    @php
        $accent = '#41c0e9';
        
        $cliente = $this->cliente;
        $clienteNombre = $cliente?->razon_social ?? $cliente?->nombre ?? 'Tu cuenta';
        
        // KPIs
        $docsPendientes = $this->documentosPendientes ?? 0;
        $facturasPendientes = $this->facturasPendientes ?? 0;
        $proximoCobroFecha = $this->proximoCobroFecha;
        $proximoCobroImporte = $this->proximoCobroImporte;
        $ultimaConv = $this->ultimaConversacion;
        $tgVinculado = $this->telegramVinculado ?? false;
        
        // Asesor
        $asesorNombre = $this->asesorNombre ?? 'Sin asesor';
        $asesorEmail = $this->asesorEmail;
        $tieneAsesor = $this->tieneAsesor ?? false;
        
        // Telegram
        $botUsername = config('services.telegram.bot_username') ?? env('TELEGRAM_BOT_USERNAME');
        $tgDeepLink = $botUsername ? 'tg://resolve?domain=' . ltrim($botUsername, '@') : null;
        
        // Estilos comunes
        $kpiCardClass = 'group relative overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 transition-all hover:shadow-md';
        $kpiBorder = 'border border-sky-200/60 dark:border-sky-400/20';
        $kpiGlow = 'before:content-[\'\'] before:absolute before:-inset-0.5 before:rounded-[1.25rem] before:bg-gradient-to-r before:from-sky-400/20 before:via-cyan-300/10 before:to-purple-400/10 before:opacity-0 hover:before:opacity-100 before:blur-xl before:transition';
        $kpiTopLine = 'after:content-[\'\'] after:absolute after:left-6 after:right-6 after:top-0 after:h-[2px] after:rounded-full after:bg-gradient-to-r after:from-transparent after:via-sky-400/60 after:to-transparent after:opacity-70';
    @endphp

    {{-- HERO --}}
    <div class="relative overflow-hidden rounded-3xl bg-white p-8 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-6">
        <div class="absolute -right-24 -top-24 h-56 w-56 rounded-full opacity-20 blur-3xl"
             style="background: {{ $accent }}"></div>

        <div class="relative">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-gray-950 dark:text-white">
                        ¡Hola! 👋
                    </h1>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Bienvenido a tu portal de AsesorFy. Aquí tienes un resumen de tu cuenta.
                    </p>
                </div>

                <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 px-4 py-2 text-sm font-bold text-gray-700 ring-1 ring-inset ring-gray-200 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10">
                    <x-filament::icon icon="heroicon-m-user-circle" class="h-5 w-5" />
                    {{ $clienteNombre }}
                </span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        {{-- COLUMNA IZQUIERDA --}}
        <div class="lg:col-span-8 space-y-6">
            
            {{-- KPIs PRINCIPALES --}}
            <div>
                <h2 class="text-base font-black text-gray-900 dark:text-white mb-4">
                    Estado de tu cuenta
                </h2>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    
                    {{-- Documentos pendientes --}}
                    <a href="{{ url('/portal/documentos?tab=requiere_atencion') }}"
                       class="{{ $kpiCardClass }} {{ $kpiGlow }} {{ $kpiTopLine }} {{ $kpiBorder }} hover:scale-[1.02]">
                        <div class="relative">
                            <div class="flex items-center justify-between mb-4">
                                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                    Documentos pendientes
                                </div>
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 ring-1 ring-inset ring-sky-200 dark:bg-sky-950/40 dark:ring-sky-400/20">
                                    <x-filament::icon icon="heroicon-m-document-text" class="h-5 w-5 text-sky-700 dark:text-sky-200" />
                                </div>
                            </div>

                            <div class="flex items-end justify-between">
                                <div>
                                    <div class="text-3xl font-black tabular-nums {{ $docsPendientes > 0 ? 'text-amber-600' : '' }}" 
                                         style="color: {{ $docsPendientes > 0 ? '#d97706' : $accent }};">
                                        {{ $docsPendientes }}
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $docsPendientes === 1 ? 'Requiere tu respuesta' : 'Requieren tu respuesta' }}
                                    </div>
                                </div>

                                @if($docsPendientes > 0)
                                    <div class="flex items-center gap-1 text-xs font-semibold text-amber-700 dark:text-amber-300">
                                        Ver ahora
                                        <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" />
                                    </div>
                                @endif
                            </div>
                        </div>
                    </a>

                    {{-- Facturas pendientes --}}
                    <a href="{{ url('/portal/facturas') }}"
                       class="{{ $kpiCardClass }} {{ $kpiGlow }} {{ $kpiTopLine }} {{ $kpiBorder }} hover:scale-[1.02]">
                        <div class="relative">
                            <div class="flex items-center justify-between mb-4">
                                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                    Facturas pendientes AsesorFy
                                </div>
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 ring-1 ring-inset ring-sky-200 dark:bg-sky-950/40 dark:ring-sky-400/20">
                                    <x-filament::icon icon="heroicon-m-document-currency-euro" class="h-5 w-5 text-sky-700 dark:text-sky-200" />
                                </div>
                            </div>

                            <div class="flex items-end justify-between">
                                <div>
                                    <div class="text-3xl font-black tabular-nums {{ $facturasPendientes > 0 ? 'text-red-600' : '' }}"
                                         style="color: {{ $facturasPendientes > 0 ? '#dc2626' : $accent }};">
                                        {{ $facturasPendientes }}
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Pendientes de pago
                                    </div>
                                </div>

                                @if($facturasPendientes > 0)
                                    <div class="flex items-center gap-1 text-xs font-semibold text-red-700 dark:text-red-300">
                                        Ver ahora
                                        <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4" />
                                    </div>
                                @endif
                            </div>
                        </div>
                    </a>

                    {{-- Próximo cobro --}}
                    <a href="{{ url('/portal/suscripcion') }}"
                       class="{{ $kpiCardClass }} {{ $kpiGlow }} {{ $kpiTopLine }} {{ $kpiBorder }} hover:scale-[1.02]">
                        <div class="relative">
                            <div class="flex items-center justify-between mb-4">
                                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                    Próximo cobro
                                </div>
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 ring-1 ring-inset ring-sky-200 dark:bg-sky-950/40 dark:ring-sky-400/20">
                                    <x-filament::icon icon="heroicon-m-calendar-days" class="h-5 w-5 text-sky-700 dark:text-sky-200" />
                                </div>
                            </div>

                            <div>
                                <div class="text-2xl font-black tabular-nums" style="color: {{ $accent }};">
                                    {{ $proximoCobroFecha ?? '—' }}
                                </div>
                                @if($proximoCobroImporte)
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Aprox. <span class="font-black text-gray-900 dark:text-white">{{ number_format($proximoCobroImporte, 2, ',', '.') }} €</span> + IVA
                                    </div>
                                @else
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        No hay cobros programados
                                    </div>
                                @endif
                            </div>
                        </div>
                    </a>

                    {{-- Última conversación --}}
                    <a href="{{ url('/portal/chats') }}"
                       class="{{ $kpiCardClass }} {{ $kpiGlow }} {{ $kpiTopLine }} {{ $kpiBorder }} hover:scale-[1.02]">
                        <div class="relative">
                            <div class="flex items-center justify-between mb-4">
                                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                    Última conversación
                                </div>
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-sky-50 ring-1 ring-inset ring-sky-200 dark:bg-sky-950/40 dark:ring-sky-400/20">
                                    <x-filament::icon icon="heroicon-m-chat-bubble-left-right" class="h-5 w-5 text-sky-700 dark:text-sky-200" />
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                @if($tgVinculado)
                                    <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                @else
                                    <span class="h-2 w-2 rounded-full bg-gray-300"></span>
                                @endif
                                
                                <div class="text-lg font-black text-gray-900 dark:text-white">
                                    {{ $ultimaConv ?? 'Sin conversaciones' }}
                                </div>
                            </div>
                            
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $tgVinculado ? 'Telegram vinculado' : 'Telegram no vinculado' }}
                            </div>
                        </div>
                    </a>

                </div>
            </div>

            {{-- ACCESOS RÁPIDOS --}}
            <div>
                <h2 class="text-base font-black text-gray-900 dark:text-white mb-4">
                    Accesos rápidos
                </h2>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    
                    {{-- Subir documento --}}
                    <a href="{{ url('/portal/documentos') }}"
                       class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 hover:ring-sky-300 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-sky-400/30 transition-all hover:scale-[1.02]">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl" style="background-color: {{ $accent }}20;">
                                <x-filament::icon icon="heroicon-m-arrow-up-tray" class="h-6 w-6" style="color: {{ $accent }};" />
                            </div>
                            <div>
                                <div class="text-sm font-black text-gray-900 dark:text-white">Subir documento</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Adjunta ficheros para tu asesor</div>
                            </div>
                        </div>
                    </a>

                    {{-- Abrir Telegram --}}
                    @if($tgDeepLink)
                        <a href="{{ $tgDeepLink }}"
                           class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 hover:ring-sky-300 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-sky-400/30 transition-all hover:scale-[1.02]">
                            <div class="flex items-center gap-4">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl" style="background-color: {{ $accent }}20;">
                                    <x-filament::icon icon="heroicon-m-paper-airplane" class="h-6 w-6" style="color: {{ $accent }};" />
                                </div>
                                <div>
                                    <div class="text-sm font-black text-gray-900 dark:text-white">
                                        {{ $tgVinculado ? 'Abrir Telegram' : 'Vincular Telegram' }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Habla con tu asesor</div>
                                </div>
                            </div>
                        </a>
                    @else
                        <a href="{{ url('/portal/chats') }}"
                           class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 hover:ring-sky-300 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-sky-400/30 transition-all hover:scale-[1.02]">
                            <div class="flex items-center gap-4">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl" style="background-color: {{ $accent }}20;">
                                    <x-filament::icon icon="heroicon-m-paper-airplane" class="h-6 w-6" style="color: {{ $accent }};" />
                                </div>
                                <div>
                                    <div class="text-sm font-black text-gray-900 dark:text-white">Ir a Chats</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Configura Telegram</div>
                                </div>
                            </div>
                        </a>
                    @endif

                    {{-- Ver facturas --}}
                    <a href="{{ url('/portal/facturas') }}"
                       class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 hover:ring-sky-300 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-sky-400/30 transition-all hover:scale-[1.02]">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl" style="background-color: {{ $accent }}20;">
                                <x-filament::icon icon="heroicon-m-document-currency-euro" class="h-6 w-6" style="color: {{ $accent }};" />
                            </div>
                            <div>
                                <div class="text-sm font-black text-gray-900 dark:text-white">Ver facturas</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Descargar y consultar</div>
                            </div>
                        </div>
                    </a>

                    {{-- Ver suscripción --}}
                    <a href="{{ url('/portal/suscripcion') }}"
                       class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 hover:ring-sky-300 dark:bg-gray-900 dark:ring-white/10 dark:hover:ring-sky-400/30 transition-all hover:scale-[1.02]">
                        <div class="flex items-center gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl" style="background-color: {{ $accent }}20;">
                                <x-filament::icon icon="heroicon-m-arrow-path" class="h-6 w-6" style="color: {{ $accent }};" />
                            </div>
                            <div>
                                <div class="text-sm font-black text-gray-900 dark:text-white">Mi suscripción</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Servicios contratados</div>
                            </div>
                        </div>
                    </a>

                </div>
            </div>

        </div>

        {{-- COLUMNA DERECHA --}}
        <div class="lg:col-span-4 space-y-6">
            
            {{-- Tu asesor --}}
            <div class="rounded-3xl bg-gradient-to-br from-sky-50 to-cyan-50 p-6 ring-1 ring-sky-200/50 dark:from-sky-950/20 dark:to-cyan-950/20 dark:ring-sky-400/20">
                <div class="flex items-center gap-2 mb-4">
                    <x-filament::icon icon="heroicon-m-user" class="h-5 w-5" style="color: {{ $accent }};" />
                    <h3 class="text-sm font-black text-gray-900 dark:text-white">
                        Tu asesor
                    </h3>
                </div>

                @if($tieneAsesor)
                    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <div class="flex items-start gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-sky-50 ring-1 ring-inset ring-sky-200 dark:bg-sky-950/40 dark:ring-sky-400/20">
                                <x-filament::icon icon="heroicon-m-user" class="h-6 w-6 text-sky-700 dark:text-sky-200" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-black text-gray-900 dark:text-white truncate">
                                    {{ $asesorNombre }}
                                </div>
                                @if($asesorEmail)
                                    <div class="mt-1 text-xs text-gray-600 dark:text-gray-400 truncate">
                                        {{ $asesorEmail }}
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Botones de contacto --}}
                        <div class="mt-4 flex flex-col gap-2">
                            {{-- Contactar por Email --}}
                            @if($asesorEmail)
                                <a href="mailto:{{ $asesorEmail }}"
                                   class="flex items-center justify-center gap-2 rounded-xl bg-gray-100 px-4 py-2 text-sm font-bold text-gray-900 transition-all hover:bg-gray-200 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700">
                                    <x-filament::icon icon="heroicon-m-envelope" class="h-4 w-4" />
                                    Email
                                </a>
                            @endif

                            {{-- Contactar por Telegram --}}
                            @if($tgDeepLink)
                                <a href="{{ $tgDeepLink }}"
                                   class="flex items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-bold text-white transition-all hover:scale-105"
                                   style="background-color: {{ $accent }};">
                                    <x-filament::icon icon="heroicon-m-paper-airplane" class="h-4 w-4" />
                                    Telegram
                                </a>
                            @else
                                <a href="{{ url('/portal/chats') }}"
                                   class="flex items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-bold text-white transition-all hover:scale-105"
                                   style="background-color: {{ $accent }};">
                                    <x-filament::icon icon="heroicon-m-chat-bubble-left-right" class="h-4 w-4" />
                                    Ir a Chats
                                </a>
                            @endif
                        </div>
                    </div>
                @else
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800/50 dark:bg-amber-900/20 dark:text-amber-200">
                        <div class="flex gap-3">
                            <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-5 w-5 shrink-0" />
                            <div class="space-y-1">
                                <p class="font-black">Sin asesor asignado</p>
                                <p class="text-xs opacity-90">Contacta con atención al cliente.</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Info útil --}}
            <div class="rounded-3xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="flex items-center gap-2 text-sm font-black text-gray-900 dark:text-white mb-4">
                    <x-filament::icon icon="heroicon-m-information-circle" class="h-5 w-5 text-gray-400" />
                    ¿Necesitas ayuda?
                </h3>

                <ul class="space-y-3 text-xs text-gray-600 dark:text-gray-400">
                    <li class="flex gap-3">
                        <div class="flex-none pt-1">
                            <div class="h-1.5 w-1.5 rounded-full" style="background: {{ $accent }}"></div>
                        </div>
                        <div>
                            <strong class="block font-semibold text-gray-900 dark:text-gray-200 mb-0.5">Telegram preferente</strong>
                            Es el canal más rápido para comunicarte con tu asesor.
                        </div>
                    </li>

                    <li class="flex gap-3">
                        <div class="flex-none pt-1">
                            <div class="h-1.5 w-1.5 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                        </div>
                        <div>
                            <strong class="block font-semibold text-gray-900 dark:text-gray-200 mb-0.5">Notificaciones activas</strong>
                            Recibirás avisos cuando haya documentos pendientes.
                        </div>
                    </li>

                    <li class="flex gap-3">
                        <div class="flex-none pt-1">
                            <div class="h-1.5 w-1.5 rounded-full bg-gray-300 dark:bg-gray-600"></div>
                        </div>
                        <div>
                            <strong class="block font-semibold text-gray-900 dark:text-gray-200 mb-0.5">Horario atención</strong>
                            Lunes a viernes, 9:00 - 18:00h.
                        </div>
                    </li>
                </ul>
            </div>

        </div>
    </div>
</x-filament-panels::page>