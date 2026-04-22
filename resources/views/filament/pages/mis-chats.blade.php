<x-filament-panels::page>

@php
    $kpis = $this->kpis ?? [
        'chats_total' => 0,
        'sin_vincular' => 0,
        'sin_contestar' => 0,
        'top_sin_contestar_asesor' => null,
        'tiempo_medio_respuesta_min' => null,
        'peor_tiempo_asesor' => null,
        'sin_contestar_24h' => 0,
    ];

    // Admin/Coord -> ven breakdown por asesor
    $showAdvisorBreakdown = auth()->user()?->can('Chats:ViewAll') || auth()->user()?->can('Chats:ViewTeam');

    $avgMin = $kpis['tiempo_medio_respuesta_min'];
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-4">

    {{-- 1) CHATS --}}
    <div class="rounded-3xl border border-white/10 dark:border-white/5 bg-white/75 dark:bg-slate-950/55 p-4
                shadow-[0_20px_60px_-30px_rgba(0,0,0,.55)]">
        <div class="flex items-center justify-between">
            <div class="text-[11px] font-extrabold text-gray-600 dark:text-gray-300 uppercase tracking-wide">Chats</div>
            <x-heroicon-o-chat-bubble-left-right class="h-5 w-5 text-cyan-600 dark:text-cyan-300" />
        </div>

        <div class="mt-2 text-3xl font-black tracking-tight text-gray-900 dark:text-white">
            {{ $kpis['chats_total'] }}
        </div>

        <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
            Total visibles según permisos
        </div>
<div class="mt-3 flex flex-wrap gap-2">
    <span class="inline-flex items-center gap-2 text-[11px] font-extrabold px-3 py-1 rounded-full
        bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/20">
        Sin vincular: {{ $kpis['sin_vincular'] }}
    </span>

    <span class="inline-flex items-center gap-2 text-[11px] font-extrabold px-3 py-1 rounded-full
        bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20">
        Vinculados: {{ $kpis['vinculados'] ?? max(0, ($kpis['chats_total'] ?? 0) - ($kpis['sin_vincular'] ?? 0)) }}
    </span>
</div>

    </div>

    {{-- 2) SIN CONTESTAR --}}
    <div class="rounded-3xl border border-white/10 dark:border-white/5 bg-white/75 dark:bg-slate-950/55 p-4
                shadow-[0_20px_60px_-30px_rgba(0,0,0,.55)]">
        <div class="flex items-center justify-between">
            <div class="text-[11px] font-extrabold text-gray-600 dark:text-gray-300 uppercase tracking-wide">Sin contestar</div>
            <x-heroicon-o-inbox-arrow-down class="h-5 w-5 text-amber-600 dark:text-amber-300" />
        </div>

        <div class="mt-2 text-3xl font-black tracking-tight text-gray-900 dark:text-white">
            {{ $kpis['sin_contestar'] }}
        </div>

        <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
            Último mensaje: cliente
        </div>

        @if($showAdvisorBreakdown && !empty($kpis['top_sin_contestar_asesor']))
            <div class="mt-3 text-[11px] font-extrabold px-3 py-1 rounded-full inline-flex items-center gap-2
                        bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/20">
                <x-heroicon-o-user class="h-4 w-4" />
                <span class="truncate">
                    Más carga: {{ $kpis['top_sin_contestar_asesor']['name'] }} ({{ $kpis['top_sin_contestar_asesor']['count'] }})
                </span>
            </div>
        @endif
    </div>

    {{-- 3) TIEMPO MEDIO RESPUESTA --}}
    <div class="rounded-3xl border border-white/10 dark:border-white/5 bg-white/75 dark:bg-slate-950/55 p-4
                shadow-[0_20px_60px_-30px_rgba(0,0,0,.55)]">
        <div class="flex items-center justify-between">
            <div class="text-[11px] font-extrabold text-gray-600 dark:text-gray-300 uppercase tracking-wide">Tiempo medio</div>
            <x-heroicon-o-clock class="h-5 w-5 text-cyan-700 dark:text-cyan-300" />
        </div>

        <div class="mt-2 text-3xl font-black tracking-tight text-gray-900 dark:text-white">
            {{ $avgMin !== null ? $avgMin . ' min' : '—' }}
        </div>

        <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
            Último msg cliente → primera respuesta asesor
        </div>

        @if($showAdvisorBreakdown && !empty($kpis['peor_tiempo_asesor']))
            <div class="mt-3 text-[11px] font-extrabold px-3 py-1 rounded-full inline-flex items-center gap-2
                        bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/20">
                <x-heroicon-o-exclamation-triangle class="h-4 w-4" />
                <span class="truncate">
                    Peor: {{ $kpis['peor_tiempo_asesor']['name'] }} ({{ $kpis['peor_tiempo_asesor']['minutes'] }} min)
                </span>
            </div>
        @endif
    </div>

    {{-- 4) +24H SIN RESPUESTA --}}
    <div class="rounded-3xl border border-white/10 dark:border-white/5 bg-white/75 dark:bg-slate-950/55 p-4
                shadow-[0_20px_60px_-30px_rgba(0,0,0,.55)]">
        <div class="flex items-center justify-between">
            <div class="text-[11px] font-extrabold text-gray-600 dark:text-gray-300 uppercase tracking-wide">+24h sin respuesta</div>
            <x-heroicon-o-fire class="h-5 w-5 text-rose-600 dark:text-rose-300" />
        </div>

        <div class="mt-2 text-3xl font-black tracking-tight text-gray-900 dark:text-white">
            {{ $kpis['sin_contestar_24h'] }}
        </div>

        <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
            Desde el último mensaje del cliente
        </div>

        <div class="mt-3">
            <span class="inline-flex items-center gap-2 text-[11px] font-extrabold px-3 py-1 rounded-full
                bg-rose-500/10 text-rose-700 dark:text-rose-300 border border-rose-500/20">
                Prioridad alta
            </span>
        </div>
    </div>

</div>





    <div class="grid grid-cols-12 gap-4 h-[calc(100vh-10rem)] min-h-0">

        {{-- SIDEBAR --}}
        <div class="col-span-12 lg:col-span-3 h-full min-h-0">
            <div class="h-full min-h-0 rounded-3xl border border-white/10 dark:border-white/5 bg-white/75 dark:bg-slate-950/55 shadow-[0_20px_60px_-30px_rgba(0,0,0,.55)] overflow-hidden flex flex-col">
                {{-- Top bar --}}
                <div class="px-4 py-3 border-b border-black/5 dark:border-white/5 flex items-center justify-between shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 rounded-2xl bg-white dark:bg-slate-100 shadow-md flex items-center justify-center overflow-hidden ring-1 ring-black/5">
                            <img
                                src="{{ asset('images/logo_boot.png') }}"
                                alt="AsesorFyBot"
                                class="h-9 w-9 object-contain"
                            />
                        </div>

                        <div>
                            <div class="font-extrabold tracking-tight text-gray-900 dark:text-white leading-4">
                                AsesorFy
                            </div>
                            <div class="text-[11px] text-gray-500 dark:text-gray-400">
                                @AsesorFyBot
                            </div>
                        </div>
                    </div>

                    <div class="text-[11px] px-2 py-1 rounded-full bg-cyan-500/10 text-cyan-700 dark:text-cyan-300 border border-cyan-500/20">
                        Inbox
                    </div>
                </div>

                {{-- Search --}}
                <div class="p-3 border-b border-black/5 dark:border-white/5 shrink-0">
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">⌕</span>
                        <input
                            wire:model.live.debounce.250ms="search"
                            type="text"
                            placeholder="Buscar…"
                            class="w-full pl-10 pr-4 py-2.5 rounded-2xl border border-black/5 dark:border-white/10
                                bg-white/90 dark:bg-slate-950/40 text-sm
                                outline-none focus:outline-none focus:ring-0 focus:ring-offset-0
                                focus:border-cyan-500/40 focus:shadow-[0_0_0_4px_rgba(34,211,238,.16)]"
                        />
                    </div>
                </div>

                {{-- Tabs (solo si NO hay búsqueda) --}}
                @if(trim($search) === '')
                    <div class="px-3 pb-3 border-b border-black/5 dark:border-white/5 shrink-0">
                        <div class="flex items-center gap-2">
                            @php
                                $pillBase = 'text-[11px] font-extrabold px-3 py-1.5 rounded-full border transition';
                            @endphp

                            <button type="button"
                                wire:click="setTab('all')"
                                class="{{ $pillBase }} {{ ($tab ?? 'all') === 'all'
                                    ? 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-300 border-cyan-500/20'
                                    : 'bg-white/60 dark:bg-slate-950/30 text-gray-600 dark:text-gray-300 border-black/5 dark:border-white/10 hover:bg-white/90 dark:hover:bg-slate-950/50'
                                }}">
                                Todos
                            </button>

                            <button type="button"
                                wire:click="setTab('vinculados')"
                                class="{{ $pillBase }} {{ ($tab ?? 'all') === 'vinculados'
                                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20'
                                    : 'bg-white/60 dark:bg-slate-950/30 text-gray-600 dark:text-gray-300 border-black/5 dark:border-white/10 hover:bg-white/90 dark:hover:bg-slate-950/50'
                                }}">
                                Vinculados
                            </button>

                            <button type="button"
                                wire:click="setTab('sin_vincular')"
                                class="{{ $pillBase }} {{ ($tab ?? 'all') === 'sin_vincular'
                                    ? 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/20'
                                    : 'bg-white/60 dark:bg-slate-950/30 text-gray-600 dark:text-gray-300 border-black/5 dark:border-white/10 hover:bg-white/90 dark:hover:bg-slate-950/50'
                                }}">
                                Sin vincular
                            </button>
                        </div>
                    </div>
                @endif

                {{-- Chat list (con botón "Cargar más") --}}
                <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain">
                    @forelse($this->chats as $chat)
                        @php
                            $c = $chat->cliente;
                            $nombre = $c?->razon_social ?? 'Cliente';

                            $iniciales = mb_strtoupper(mb_substr($nombre, 0, 2));
                            $preview = trim((string) ($chat->last_text ?? ''));
                            if ($preview === '') $preview = '—';

                            $active = $selectedChatId === $chat->id;
                            $noVinculado = empty($chat->telegram_chat_id);

                            $isOver24 = (bool) ($chat->pendiente_respuesta
                                && $chat->pendiente_since_at
                                && \Carbon\Carbon::parse($chat->pendiente_since_at)->lte(now()->subHours(24))
                            );
                        @endphp

                        <div
                            wire:click="selectChat({{ $chat->id }})"
                            class="px-3 py-2.5 transition cursor-pointer select-none"
                        >
                            <div class="rounded-2xl p-3 border
                                {{ $active
                                    ? 'border-cyan-500/30 bg-gradient-to-r from-cyan-500/10 to-sky-500/5 shadow-[0_10px_30px_-18px_rgba(34,211,238,.6)]'
                                    : 'border-transparent hover:border-black/5 dark:hover:border-white/10 hover:bg-black/[0.02] dark:hover:bg-white/[0.03]' }}"
                            >
                                <div class="flex items-center gap-3">
                                    {{-- Avatar --}}
                                    <div class="h-11 w-11 rounded-2xl flex items-center justify-center font-extrabold text-xs
                                                bg-gradient-to-br from-slate-100 to-slate-200 text-slate-700
                                                dark:from-slate-800 dark:to-slate-900 dark:text-slate-200">
                                        {{ $iniciales }}
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="min-w-0">
                                                <div class="font-bold truncate text-gray-900 dark:text-white">
                                                    {{ $nombre }}
                                                </div>

                                                @if($noVinculado)
                                                    <div class="mt-1">
                                                        <span class="text-[10px] px-2 py-0.5 rounded-full border
                                                            {{ $isOver24
                                                                ? 'bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-500/20'
                                                                : 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/20'
                                                            }}">
                                                            Sin vincular
                                                        </span>
                                                    </div>
                                                @endif
                                            </div>

                                            <div class="text-[11px] text-gray-500 dark:text-gray-400 shrink-0">
                                                {{ $chat->last_message_at?->format('H:i') ?? '' }}
                                            </div>
                                        </div>

                                        <div class="flex items-center justify-between gap-2 mt-1">
                                            <div class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                {{ $preview }}
                                            </div>

                                            <div class="flex items-center gap-2 shrink-0">
                                                @if($noVinculado)
                                                    <button
                                                        type="button"
                                                        wire:click.stop="invite({{ $chat->id }})"
                                                        class="text-[11px] font-extrabold px-2 py-1 rounded-full
                                                            bg-amber-500/10 text-amber-700 dark:text-amber-300
                                                            border border-amber-500/20 hover:bg-amber-500/15 transition"
                                                        title="Enviar invitación por email"
                                                    >
                                                        Invitar
                                                    </button>
                                                @endif

                                                @if($chat->unread_count > 0)
                                                    <span class="text-[11px] font-extrabold px-2 py-0.5 rounded-full text-white shadow
                                                        {{ $isOver24
                                                            ? 'bg-gradient-to-r from-rose-500 to-rose-600'
                                                            : 'bg-gradient-to-r from-cyan-500 to-sky-600'
                                                        }}">
                                                        {{ $chat->unread_count }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-6">
                            <div class="rounded-3xl border border-dashed border-black/10 dark:border-white/10 p-6 text-center bg-white/60 dark:bg-slate-950/30">
                                <div class="text-4xl mb-2">💬</div>
                                <div class="font-extrabold text-gray-900 dark:text-white">Aún no tienes chats</div>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    Cuando un cliente vincule Telegram, aparecerá aquí.
                                </div>
                            </div>
                        </div>
                    @endforelse

                    {{-- Botón "Cargar más" (queda al final de la lista) --}}
                    @if($this->hasMoreChats)
                        <div class="px-3 pb-3">
                            <button
                                type="button"
                                wire:click="loadMoreChats"
                                wire:loading.attr="disabled"
                                wire:target="loadMoreChats"
                                class="w-full rounded-2xl p-3 border border-black/5 dark:border-white/10
                                    bg-white/60 dark:bg-slate-950/30 hover:bg-white/90 dark:hover:bg-slate-950/50 transition
                                    text-xs font-extrabold text-gray-700 dark:text-gray-200"
                            >
                                <span wire:loading.remove wire:target="loadMoreChats">Cargar más chats</span>
                                <span wire:loading wire:target="loadMoreChats">Cargando…</span>
                            </button>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- CHAT WINDOW --}}
        <div class="col-span-12 lg:col-span-9 h-full min-h-0">
            <div class="h-full min-h-0 rounded-3xl border border-white/10 dark:border-white/5 bg-white/75 dark:bg-slate-950/55 shadow-[0_20px_60px_-30px_rgba(0,0,0,.55)] overflow-hidden flex flex-col">

                {{-- Header --}}
                <div class="px-5 py-4 border-b border-black/5 dark:border-white/5 flex items-center justify-between shrink-0">
                    <div class="min-w-0 flex items-center gap-3">
                        @php
                            $cSel = $this->selectedChat?->cliente;
                            $nombreSel = $cSel?->razon_social ?? trim(($cSel?->nombre ?? '') . ' ' . ($cSel?->apellidos ?? '')) ?? 'Cliente';
                            $iniSel = mb_strtoupper(mb_substr($nombreSel, 0, 2));
                        @endphp

                        <div class="h-10 w-10 rounded-2xl flex items-center justify-center font-extrabold text-sm
                            bg-gradient-to-br from-slate-100 to-slate-200 text-slate-700
                            dark:from-slate-800 dark:to-slate-900 dark:text-slate-200 shadow-md ring-1 ring-black/5 dark:ring-white/10">
                            {{ $iniSel }}
                        </div>

                        <div class="min-w-0">
                            <div class="font-extrabold truncate text-gray-900 dark:text-white flex items-center gap-2">
                                    @if($this->selectedChat?->cliente)
                                        @php
                                            $cliente = $this->selectedChat->cliente;
                                            $clienteNombre = $cliente->razon_social;
                                            $clienteUrl = \App\Filament\Resources\ClienteResource::getUrl('view', ['record' => $cliente->id]);
                                        @endphp

                                        <a
                                            href="{{ $clienteUrl }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="truncate hover:underline"
                                            title="Abrir ficha del cliente"
                                        >
                                            {{ $clienteNombre }}
                                        </a>

                                        <a
                                            href="{{ $clienteUrl }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="shrink-0 opacity-70 hover:opacity-100"
                                            title="Abrir ficha del cliente"
                                        >
                                            <x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" />
                                        </a>
                                    @else
                                        Selecciona un chat
                                    @endif
                                </div>


                            <div class="text-[12px] text-gray-500 dark:text-gray-400 flex items-center gap-2">
    @if($this->selectedChat && $this->selectedChat->telegram_chat_id)
        <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400" title="Vinculado"></span>
        <span>Telegram</span>
        
        @if($this->selectedChat->telegram_first_name || $this->selectedChat->telegram_username)
            <span class="opacity-50 text-[10px]">•</span>
            <span class="font-medium text-gray-700 dark:text-gray-300">
                <x-heroicon-m-user class="h-3 w-3 inline-block -mt-0.5 opacity-70" />
                {{ $this->selectedChat->telegram_first_name }} 
                @if($this->selectedChat->telegram_username)
                    <span class="opacity-75">({{ '@' . $this->selectedChat->telegram_username }})</span>
                @endif
            </span>
        @endif
    @else
        <span class="inline-flex h-2 w-2 rounded-full bg-amber-400" title="Sin vincular"></span>
        <span>Sin vincular</span>
    @endif
</div>
                        </div>
                    </div>

                    {{-- Acciones + estado --}}
                    <div class="flex items-center gap-2 shrink-0">
                        @if($this->selectedChatId)
                            <button
                                type="button"
                                wire:click="markSelectedChatAsRead"
                                class="text-[11px] font-extrabold px-3 py-1.5 rounded-full
                                    bg-white/70 dark:bg-slate-900/50 border border-black/5 dark:border-white/10
                                    text-gray-700 dark:text-gray-200 hover:bg-white/90 dark:hover:bg-slate-900/70 transition"
                            >
                                Marcar leído
                            </button>
                        @endif

                        @if($this->selectedChatId && $this->selectedChat?->telegram_chat_id && auth()->user()?->can('Chats:ReLinkTelegram'))
                            <button
                                type="button"
                                wire:click="relinkSelectedTelegram"
                                class="text-[11px] font-extrabold px-3 py-1.5 rounded-full
                                    bg-rose-500/10 text-rose-700 dark:text-rose-300 border border-rose-500/20
                                    hover:bg-rose-500/15 transition"
                                title="Permite vincular de nuevo si el cliente cambió de Telegram"
                            >
                                Re-vincular
                            </button>
                        @endif

                        @php $isLinked = (bool) ($this->selectedChat?->telegram_chat_id); @endphp

                        <div class="text-[11px] font-extrabold px-3 py-1.5 rounded-full border
                            {{ $isLinked
                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border-emerald-500/20'
                                : 'bg-rose-500/10 text-rose-700 dark:text-rose-300 border-rose-500/20'
                            }}">
                            {{ $isLinked ? 'Vinculado' : 'Sin vincular' }}
                        </div>
                    </div>
                </div>

                {{-- Messages --}}
                <div
                    class="flex-1 min-h-0 p-3 overflow-y-auto overscroll-contain relative
                        [--chatbg:#D7EEF8] dark:[--chatbg:#071620]
                        [--dotAlpha:0.28] dark:[--dotAlpha:0.20]
                        [--dotRgb:148,163,184] dark:[--dotRgb:255,255,255]"
                    style="
                        background-color: var(--chatbg);
                        background-image: radial-gradient(circle at 1px 1px, rgba(var(--dotRgb), var(--dotAlpha)) 1px, transparent 0);
                        background-size: 18px 18px;
                        background-position: 0 0;
                    "
                    wire:poll.2s.visible
                    x-data="{
                                auto: true,
                                loadingOlder: false,
                                lastCount: 0,
                                _lastTop: null,

                                nearBottom() {
                                    const el = this.$refs.box
                                    return (el.scrollHeight - el.scrollTop - el.clientHeight) < 140
                                },

                                scrollToBottom() {
                                    this.$nextTick(() => {
                                        const el = this.$refs.box
                                        el.scrollTop = el.scrollHeight
                                    })
                                },

                                init() {
                                    this.lastCount = this.$refs.box.querySelectorAll('[data-msg]').length
                                    this.scrollToBottom()
                                },

                                updated() {
                                    const nowCount = this.$refs.box.querySelectorAll('[data-msg]').length
                                    if (this.auto && nowCount > this.lastCount) {
                                        this.scrollToBottom()
                                    }
                                    this.lastCount = nowCount
                                },

                                async tryLoadOlder() {
                                    if (this.loadingOlder) return
                                    if (! this.$wire.$get('hasOlderMessages')) return

                                    const el = this.$refs.box
                                    if (el.scrollTop > 10) return

                                    this.loadingOlder = true
                                    this.auto = false

                                    const prevHeight = el.scrollHeight
                                    await this.$wire.loadOlderMessages()

                                    this.$nextTick(() => {
                                        el.scrollTop = (el.scrollHeight - prevHeight) + 10
                                        this.loadingOlder = false
                                    })
                                },

                               onScroll() {
                                const el = this.$refs.box;

                                // Solo activamos/desactivamos auto si el usuario realmente se movió
                                // (evita que cambios de altura / morph / reset del composer te lo apaguen)
                                const userScroll = el && (el.scrollTop !== (this._lastTop ?? el.scrollTop));

                                if (userScroll) {
                                    this.auto = this.nearBottom();
                                    this._lastTop = el.scrollTop;
                                }

                                this.tryLoadOlder();
                            },
                            }"
                  x-ref="box"
                   x-init="
                            init();
                            this.auto = true;

                            // Re-ejecuta updated() cuando Livewire re-renderiza (para auto-scroll)
                            (() => {
                                const self = $data;
                                if (!window.Livewire?.hook) return;

                                Livewire.hook('morph.updated', ({ el }) => {
                                    if (!self.$refs?.box || !el) return;

                                    if (
                                        el === self.$refs.box ||
                                        el.contains(self.$refs.box) ||
                                        self.$refs.box.contains(el)
                                    ) {
                                        self.updated();
                                    }
                                });
                            })();
                        "
                     x-on:scroll.passive="onScroll()" 
                         x-effect="updated()"

                >
                    @php
                        $lastDateKey = null;

                        $mensajes = collect();
                        if ($this->selectedChat && $this->selectedChat->relationLoaded('mensajes')) {
                            $mensajes = $this->selectedChat->mensajes;
                        }

                        $mensajes = $mensajes->sortBy('id');
                    @endphp

                    <div class="relative z-10 min-h-full flex flex-col justify-end">
                        <div class="space-y-1.5">

                            {{-- Cargar anteriores --}}
                            @if($this->hasOlderMessages)
                                <div class="flex justify-center pb-2">
                                    <button
                                        type="button"
                                        wire:click="loadOlderMessages"
                                        class="text-[11px] font-extrabold px-3 py-1 rounded-full
                                            bg-white/70 dark:bg-slate-900/50 border border-black/5 dark:border-white/10
                                            text-gray-700 dark:text-gray-200 hover:bg-white/90 dark:hover:bg-slate-900/70"
                                    >
                                        Cargar mensajes anteriores
                                    </button>
                                </div>
                            @endif

                          @forelse($mensajes as $m)
    @php
        $isOut = $m->origen === 'asesor';
        $isSystem = $m->origen === 'sistema';

        $asesorAsignadoId = (int) ($this->selectedChat?->asesor_id ?? 0);
        $msgUserId = (int) ($m->user_id ?? 0);
        $respondioOtro = $isOut && $msgUserId > 0 && $asesorAsignadoId > 0 && $msgUserId !== $asesorAsignadoId;

        $dt = $m->created_at ? \Carbon\Carbon::parse($m->created_at) : null;
        $dateKey = $dt ? $dt->toDateString() : null;

        $dateLabel = $dt
            ? ($dt->isToday() ? 'Hoy' : ($dt->isYesterday() ? 'Ayer' : $dt->format('d/m/Y')))
            : null;

        $timeLabel = $dt?->format('H:i');

        $tipo = (string) ($m->tipo ?? '');
        $hasFile = ! empty($m->file_path);
        $mime = (string) ($m->file_mime ?? '');
        $isImageDoc = ($tipo === 'document' && $mime !== '' && str_starts_with($mime, 'image/'));
        $isPhotoLike = ($tipo === 'photo' || $isImageDoc);
        $isAttachment = ($hasFile && ($isPhotoLike || $tipo === 'document'));

        // ✅ Adjuntos: NO reservamos hueco para hora absoluta (porque va debajo).
        // ✅ Texto: SÍ reservamos hueco para hora absoluta (esquina).
        $bubblePad = $isAttachment
            ? 'pl-2.5 pr-3 pt-1 pb-2'
            : 'pl-2.5 pr-16 pt-1 pb-5';
    @endphp

    {{-- Separador por fecha --}}
    @if(! $isSystem && $dateKey && $dateKey !== $lastDateKey)
        <div class="flex justify-center py-2">
            <div class="text-[11px] px-3 py-1 rounded-full
                        bg-white/70 dark:bg-slate-900/50 border border-black/5 dark:border-white/10
                        text-gray-600 dark:text-gray-300">
                {{ $dateLabel }}
            </div>
        </div>
        @php $lastDateKey = $dateKey; @endphp
    @endif

    @if($isSystem)
        <div class="flex justify-center">
            <div class="text-[11px] px-3 py-1 rounded-full
                        bg-white/70 dark:bg-slate-900/50 border border-black/5 dark:border-white/10
                        text-gray-600 dark:text-gray-300">
                {{ $m->contenido ?? '—' }}
            </div>
        </div>
    @else
        <div wire:key="msg-{{ $m->id }}" data-msg="{{ $m->id }}" class="flex {{ $isOut ? 'justify-end' : 'justify-start' }} mb-1 relative">
            <div class="relative {{ $bubblePad }} rounded-lg shadow-sm text-[15px] leading-snug max-w-[90%] xl:max-w-[85%]
                {{ $isOut
                    ? 'bg-[#EEFFDE] dark:bg-[#2b5233] text-gray-900 dark:text-gray-100 rounded-br-none ml-12'
                    : 'bg-[#94E2F2] dark:bg-[#155E75] text-gray-900 dark:text-white rounded-bl-none mr-12'
                }}">

                {{-- CONTENIDO: foto / documento / texto --}}
                @if($isPhotoLike && $hasFile)
                    <div class="relative z-10">
                        <a href="{{ route('chat-mensajes.file', $m->id) }}" target="_blank" class="block">
                            <img
                                src="{{ route('chat-mensajes.file', $m->id) }}"
                                alt="Imagen"
                                loading="lazy"
                                class="rounded-lg max-w-[320px] w-full h-auto shadow-sm"
                            />
                        </a>

                        @if(!empty($m->caption))
                            <div class="mt-1 whitespace-pre-wrap break-words">{{ $m->caption }}</div>
                        @endif
                    </div>

                @elseif($tipo === 'document' && $hasFile)
                    <div class="relative z-10">
                        <div class="flex items-center gap-3 min-w-0">
                            {{-- Abrir --}}
                            <a
                                href="{{ route('chat-mensajes.file', $m->id) }}"
                                target="_blank"
                                title="Abrir"
                                class="min-w-0 inline-flex items-center gap-2 font-extrabold underline"
                            >
                                <x-heroicon-o-document-text class="h-4 w-4 text-cyan-600 dark:text-cyan-300 shrink-0" />
                                <span class="truncate">{{ $m->file_original_name ?? 'Documento' }}</span>
                            </a>

                            {{-- Descargar --}}
                            <a
                                href="{{ route('chat-mensajes.file', ['chatMensaje' => $m->id, 'download' => 1]) }}"
                                title="Descargar"
                                class="inline-flex items-center gap-1 text-[12px] font-extrabold opacity-80 hover:opacity-100 underline shrink-0"
                            >
                                <x-heroicon-o-arrow-down-tray class="h-4 w-4 text-gray-700 dark:text-gray-200" />
                                <span>Descargar</span>
                            </a>
                        </div>

                        @if(!empty($m->caption))
                            <div class="mt-1 whitespace-pre-wrap break-words">{{ $m->caption }}</div>
                        @endif
                    </div>

                @else
                    <span class="whitespace-pre-wrap break-words relative z-10">{{ trim((string) ($m->contenido ?? '—')) }}</span>
                @endif

                {{-- Hora + estados envío --}}
                @if($timeLabel)
                    @if($isAttachment)
                        <div class="mt-0.5 flex justify-end">
                            <span class="text-[11px] select-none flex items-center gap-1
                                {{ $isOut
                                    ? 'text-[#4fae6a] dark:text-[#86efac] font-medium'
                                    : 'text-cyan-800/60 dark:text-cyan-200/60'
                                }}">
                                @include('filament.pages._mis-chats-meta', [
                                    'm' => $m,
                                    'timeLabel' => $timeLabel,
                                    'respondioOtro' => $respondioOtro,
                                    'isOut' => $isOut,
                                ])
                            </span>
                        </div>
                    @else
                        <span class="absolute right-2 bottom-1 text-[11px] select-none z-20 flex items-center gap-1
                            {{ $isOut
                                ? 'text-[#4fae6a] dark:text-[#86efac] font-medium'
                                : 'text-cyan-800/60 dark:text-cyan-200/60'
                            }}">
                            @include('filament.pages._mis-chats-meta', [
                                'm' => $m,
                                'timeLabel' => $timeLabel,
                                'respondioOtro' => $respondioOtro,
                                'isOut' => $isOut,
                            ])
                        </span>
                    @endif
                @endif

                {{-- Pico --}}
                @if($isOut)
                    <svg class="absolute -right-[7px] bottom-0 w-[10px] h-[10px] text-[#EEFFDE] dark:text-[#2b5233] fill-current" viewBox="0 0 10 10">
                        <path d="M0,0 L10,10 L0,10 Z" />
                    </svg>
                @else
                    <svg class="absolute -left-[7px] bottom-0 w-[10px] h-[10px] text-[#94E2F2] dark:text-[#155E75] fill-current transform scale-x-[-1]" viewBox="0 0 10 10">
                        <path d="M0,0 L10,10 L0,10 Z" />
                    </svg>
                @endif
            </div>
        </div>
    @endif
@empty
    <div class="h-full flex items-center justify-center">
        <div class="text-sm text-gray-200/80">
            Selecciona un chat para ver los mensajes.
        </div>
    </div>
@endforelse


                        </div>
                    </div>
                </div>

                {{-- Composer --}}
<div class="px-5 py-4 border-t border-black/5 dark:border-white/5 shrink-0">
    @php
        $canSend  = (bool) ($this->selectedChat && $this->selectedChat->telegram_chat_id);
        $hasFiles = isset($pendingUploads) && is_array($pendingUploads) && count($pendingUploads) > 0;
    @endphp

    {{-- Wrapper para cubrir “subiendo” + “procesando” sin huecos --}}
    <div
        class="flex flex-col gap-2 {{ $canSend ? '' : 'opacity-60' }}"
        x-data="{ postUpload: false, postUploadTimer: null }"
        x-on:upload-picked.window="postUpload = true"
        x-init="
            if (window.Livewire?.hook) {
                Livewire.hook('morph.updated', () => {
                    // si ya existe la lista de pendientes, apagamos el loader post-upload (con delay fijo)
                    if ($root.querySelector('[data-pending-list]')) {
                        if (postUploadTimer) clearTimeout(postUploadTimer);
                        postUploadTimer = setTimeout(() => { postUpload = false }, 1000);
                    }
                });
            }
        "
    >
        {{-- Input hidden múltiple (TEMP: $upload) --}}
        <input
            type="file"
            multiple
            wire:model="upload"
            wire:key="chat-upload-input-{{ $uploadInputKey }}"
            class="hidden"
            id="chat-upload-input"
            accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain,image/jpeg,image/png,image/webp"
            x-on:change="$dispatch('upload-picked')"
            {{ $canSend ? '' : 'disabled' }}
        />

        {{-- Fila principal --}}
        <div class="flex items-center gap-2">
            {{-- Botón adjuntar --}}
            <button
                type="button"
                onclick="document.getElementById('chat-upload-input').click()"
                wire:loading.attr="disabled"
                wire:target="upload"
                class="h-12 w-12 rounded-2xl
                    bg-white/70 dark:bg-slate-900/50 border border-black/5 dark:border-white/10
                    hover:bg-cyan-500/10 dark:hover:bg-cyan-400/10 transition disabled:cursor-not-allowed
                    flex items-center justify-center"
                {{ $canSend ? '' : 'disabled' }}
                title="Adjuntar archivos"
            >
                <x-heroicon-o-document-arrow-up class="h-8 w-8 text-cyan-600 dark:text-cyan-300" />
            </button>

            {{-- Form único --}}
            <form
                wire:submit.prevent="send"
                class="flex items-center gap-2 flex-1"
                x-data
                x-on:submit="
                    sessionStorage.removeItem('misChats:composerHeight');
                    if ($refs.composer) $refs.composer.style.height = '';
                "
            >
                <textarea
                    x-ref="composer"
                    wire:model.defer="message"
                    placeholder="{{ $canSend ? 'Escribe un mensaje…' : 'Chat sin vincular (no se puede enviar)' }}"
                    rows="3"
                    class="flex-1 rounded-2xl border border-black/5 dark:border-white/10 bg-white/80 dark:bg-slate-950/40 px-4 py-3 text-sm
                        outline-none focus:outline-none focus:ring-0 focus:ring-offset-0
                        focus:border-cyan-500/40 focus:shadow-[0_0_0_4px_rgba(34,211,238,.16)]
                        disabled:cursor-not-allowed resize-y overflow-auto leading-5"
                    x-data="{
                        key() { return 'misChats:composerHeight' },
                        applySaved() {
                            const h = sessionStorage.getItem(this.key());
                            if (h) this.$el.style.height = h;
                        },
                        init() {
                            this.applySaved();

                            const ro = new ResizeObserver(() => {
                                const h = this.$el.style.height;
                                if (h) sessionStorage.setItem(this.key(), h);
                            });
                            ro.observe(this.$el);

                            if (window.Livewire?.hook) {
                                Livewire.hook('morph.updated', () => this.applySaved());
                            }
                        },
                    }"
                    x-on:composer-reset.window="
                        sessionStorage.removeItem(key());
                        $el.style.height = '';
                        $el.scrollTop = 0;
                    "
                    {{ $canSend ? '' : 'disabled' }}
                ></textarea>

                {{-- Botón inteligente: si hay pendientes -> sendFiles, si no -> send --}}
                <button
                    type="{{ $hasFiles ? 'button' : 'submit' }}"
                    @if($hasFiles)
                        x-on:click="
                            // enviar archivos: corta loaders locales para que no quede 'procesando'
                            postUpload = false;
                            if (postUploadTimer) { clearTimeout(postUploadTimer); postUploadTimer = null; }
                        "
                        wire:click="sendFiles"
                        wire:target="sendFiles"
                    @else
                        wire:target="send"
                    @endif
                    wire:loading.attr="disabled"
                    class="px-5 py-3 rounded-2xl font-extrabold text-white shadow-md disabled:cursor-not-allowed"
                    style="background: linear-gradient(135deg, #2AABEE, #38BDF8);"
                    {{ $canSend ? '' : 'disabled' }}
                >
                    @if($hasFiles)
                        <span wire:loading.remove wire:target="sendFiles">Enviar {{ count($pendingUploads) }} archivo(s)</span>
                        <span wire:loading wire:target="sendFiles">Enviando…</span>
                    @else
                        <span wire:loading.remove wire:target="send">Enviar</span>
                        <span wire:loading wire:target="send">Enviando…</span>
                    @endif
                </button>
            </form>
        </div>

        {{-- Errores del input temporal (multiple puede dar upload.*) --}}
        @error('upload')
            <div class="text-[11px] font-bold text-rose-600 dark:text-rose-400">
                {{ $message }}
            </div>
        @enderror
        @error('upload.*')
            <div class="text-[11px] font-bold text-rose-600 dark:text-rose-400">
                {{ $message }}
            </div>
        @enderror

        {{-- Loader SUBIENDO (mientras Livewire está subiendo el upload) --}}
        <div
            wire:loading
            wire:target="upload"
            class="rounded-2xl border border-black/5 dark:border-white/10 bg-white/70 dark:bg-slate-900/40 px-4 py-3
                flex items-center gap-4"
        >
            <span class="relative flex h-5 w-5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cyan-400 opacity-60"></span>
                <span class="relative inline-flex rounded-full h-5 w-5 bg-cyan-500"></span>
            </span>

            <div class="min-w-0">
                <div class="text-[12px] font-extrabold text-gray-800 dark:text-gray-100 leading-4">
                    Subiendo archivos…
                </div>
                <div class="text-[11px] text-gray-500 dark:text-gray-400 leading-4">
                    Preparando para enviar
                </div>
            </div>

            <div class="ml-auto flex items-center gap-1 opacity-80">
                <span class="h-2.5 w-2.5 rounded-full bg-cyan-500 animate-bounce [animation-delay:-.2s]"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-cyan-500 animate-bounce [animation-delay:-.1s]"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-cyan-500 animate-bounce"></span>
            </div>
        </div>

        {{-- Loader POST-UPLOAD (cubre el hueco entre fin de upload y render de $pendingUploads) --}}
        <div
            x-cloak
            x-show="postUpload"
            x-transition.opacity
            class="rounded-2xl border border-black/5 dark:border-white/10 bg-white/70 dark:bg-slate-900/40 px-4 py-3
                flex items-center gap-4"
        >
            <span class="relative flex h-5 w-5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-60"></span>
                <span class="relative inline-flex rounded-full h-5 w-5 bg-amber-500"></span>
            </span>

            <div class="min-w-0">
                <div class="text-[12px] font-extrabold text-gray-800 dark:text-gray-100 leading-4">
                    Procesando…
                </div>
                <div class="text-[11px] text-gray-500 dark:text-gray-400 leading-4">
                    Casi listo
                </div>
            </div>

            <div class="ml-auto flex items-center gap-1 opacity-80">
                <span class="h-2.5 w-2.5 rounded-full bg-amber-500 animate-bounce [animation-delay:-.2s]"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-amber-500 animate-bounce [animation-delay:-.1s]"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-amber-500 animate-bounce"></span>
            </div>
        </div>

        {{-- Loader ENVIANDO ARCHIVOS (más claro, fuera del botón) --}}
        <div
            wire:loading
            wire:target="sendFiles"
            class="rounded-2xl border border-black/5 dark:border-white/10 bg-white/70 dark:bg-slate-900/40 px-4 py-3
                flex items-center gap-4"
        >
            <span class="relative flex h-5 w-5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-60"></span>
                <span class="relative inline-flex rounded-full h-5 w-5 bg-emerald-500"></span>
            </span>

            <div class="min-w-0">
                <div class="text-[12px] font-extrabold text-gray-800 dark:text-gray-100 leading-4">
                    Enviando archivos…
                </div>
                <div class="text-[11px] text-gray-500 dark:text-gray-400 leading-4">
                    Esto puede tardar unos segundos
                </div>
            </div>

            <div class="ml-auto flex items-center gap-1 opacity-80">
                <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 animate-bounce [animation-delay:-.2s]"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 animate-bounce [animation-delay:-.1s]"></span>
                <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 animate-bounce"></span>
            </div>
        </div>

        {{-- Lista de archivos pendientes (BUFFER: $pendingUploads) --}}
        @if($hasFiles)
            <div data-pending-list class="rounded-2xl bg-white/70 dark:bg-slate-900/40 border border-black/5 dark:border-white/10 p-2">
                <div class="flex items-center justify-between mb-2">
                    <div class="text-[11px] font-extrabold text-gray-700 dark:text-gray-200">
                        Archivos listos ({{ count($pendingUploads) }})
                    </div>

                    {{-- QUITAR TODOS: corta loaders locales al instante --}}
                    <button
                        type="button"
                        x-on:click="
                            postUpload = false;
                            if (postUploadTimer) { clearTimeout(postUploadTimer); postUploadTimer = null; }
                        "
                        wire:click="clearUpload"
                        wire:loading.attr="disabled"
                        wire:target="clearUpload,upload,sendFiles"
                        class="text-[11px] font-extrabold px-3 py-1.5 rounded-full
                            bg-rose-500/10 text-rose-700 dark:text-rose-300 border border-rose-500/20
                            hover:bg-rose-500/15 transition disabled:cursor-not-allowed"
                        {{ $canSend ? '' : 'disabled' }}
                        title="Quitar todos"
                    >
                        Quitar todos
                    </button>
                </div>

                <div class="flex flex-col gap-2">
                    @foreach($pendingUploads as $i => $file)
                        <div class="flex items-center justify-between gap-2 px-3 py-2 rounded-2xl
                            bg-white/60 dark:bg-slate-950/30 border border-black/5 dark:border-white/10">
                            <div class="min-w-0">
                                <div class="text-xs font-extrabold text-gray-700 dark:text-gray-200 truncate">
                                    <span class="inline-flex items-center gap-2 min-w-0">
                                        <x-heroicon-o-document-arrow-up class="h-4 w-4 text-cyan-600 dark:text-cyan-300 shrink-0" />
                                        <span class="truncate">{{ $file->getClientOriginalName() }}</span>
                                    </span>
                                </div>
                                <div class="text-[11px] text-gray-500 dark:text-gray-400">
                                    Listo para enviar
                                </div>
                            </div>

                            {{-- QUITAR 1: corta loaders locales al instante --}}
                            <button
                                type="button"
                                x-on:click="
                                    postUpload = false;
                                    if (postUploadTimer) { clearTimeout(postUploadTimer); postUploadTimer = null; }
                                "
                                wire:click="removePendingUpload({{ $i }})"
                                wire:loading.attr="disabled"
                                wire:target="removePendingUpload,upload,sendFiles"
                                class="h-9 w-9 rounded-full
                                    bg-white/60 dark:bg-slate-950/30 border border-black/5 dark:border-white/10
                                    hover:bg-rose-500/10 dark:hover:bg-rose-400/10 transition
                                    flex items-center justify-center disabled:cursor-not-allowed"
                                title="Quitar"
                                {{ $canSend ? '' : 'disabled' }}
                            >
                                <x-heroicon-o-x-mark class="h-5 w-5 text-rose-600 dark:text-rose-300" />
                            </button>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @if($this->selectedChat && ! $this->selectedChat->telegram_chat_id)
        <div class="mt-2 text-[11px] text-amber-600 dark:text-amber-400">
            Este chat aún no está vinculado a Telegram.
        </div>
    @endif
</div>







            </div>
        </div>
    </div>
</x-filament-panels::page>
