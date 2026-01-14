<x-filament-panels::page>
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

                {{-- Chat list (con botón "Cargar más") --}}
                <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain">
                    @forelse($this->chats as $chat)
                        @php
                            $c = $chat->cliente;
                            $nombre = $c?->razon_social
                                ?? trim(($c?->nombre ?? '') . ' ' . ($c?->apellidos ?? ''))
                                ?? 'Cliente';

                            $iniciales = mb_strtoupper(mb_substr($nombre, 0, 2));
                            $preview = trim((string) ($chat->last_text ?? ''));
                            if ($preview === '') $preview = '—';

                            $active = $selectedChatId === $chat->id;
                            $noVinculado = empty($chat->telegram_chat_id);
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
                                                        <span class="text-[10px] px-2 py-0.5 rounded-full
                                                            bg-amber-500/10 text-amber-700 dark:text-amber-300 border border-amber-500/20">
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
                                                    <span class="text-[11px] font-extrabold px-2 py-0.5 rounded-full text-white
                                                                    bg-gradient-to-r from-cyan-500 to-sky-600 shadow">
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
                            <div class="font-extrabold truncate text-gray-900 dark:text-white">
                                @if($this->selectedChat?->cliente)
                                    {{ $this->selectedChat->cliente->razon_social
                                        ?? trim(($this->selectedChat->cliente->nombre ?? '') . ' ' . ($this->selectedChat->cliente->apellidos ?? '')) }}
                                @else
                                    Selecciona un chat
                                @endif
                            </div>

                            <div class="text-[12px] text-gray-500 dark:text-gray-400 flex items-center gap-2">
                                <span class="inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                                Canal oficial · Telegram
                            </div>
                        </div>
                    </div>

                    {{-- Acciones + estado --}}
                    <div class="flex items-center gap-2 shrink-0">
                        @if($this->selectedChatId)
                            <button
                                type="button"
                                wire:click="markSelectedAsRead"
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
                                    this.auto = this.nearBottom()
                                    this.tryLoadOlder()
                                },
                            }"
                    x-ref="box"
                    x-init="init()"
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
                                    <div wire:key="msg-{{ $m->id }}" class="flex {{ $isOut ? 'justify-end' : 'justify-start' }} mb-1 relative">
                                        <div class="relative pl-2.5 pr-3 py-1 rounded-lg shadow-sm text-[15px] leading-snug max-w-[90%] xl:max-w-[85%]
                                            {{ $isOut
                                                ? 'bg-[#EEFFDE] dark:bg-[#2b5233] text-gray-900 dark:text-gray-100 rounded-br-none ml-12'
                                                : 'bg-[#94E2F2] dark:bg-[#155E75] text-gray-900 dark:text-white rounded-bl-none mr-12'
                                            }}">

                                            <span class="float-right w-10 h-0 ml-1"></span>

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
                                                    <div class="mt-1 flex justify-end">
                                                        <span class="text-[11px] select-none flex items-center gap-1
                                                            {{ $isOut
                                                                ? 'text-[#4fae6a] dark:text-[#86efac] font-medium'
                                                                : 'text-cyan-800/60 dark:text-cyan-200/60'
                                                            }}">
                                                            {{ $timeLabel }}

                                                            @if($isOut)
                                                                @php
                                                                    $st = $m->estado_envio ?? null;
                                                                    $err = $m->last_error ?? null;
                                                                    $errShort = $err ? \Illuminate\Support\Str::limit($err, 160) : null;
                                                                @endphp

                                                                @if($st === 'pending')
                                                                    <span class="opacity-80" title="Enviando…">⏳</span>
                                                                @elseif($st === 'failed')
                                                                    <span class="opacity-90" title="{{ $errShort ?: 'Error enviando' }}">⚠</span>
                                                                @elseif($st === 'sent')
                                                                    <span class="opacity-80" title="Enviado">✓</span>
                                                                @endif
                                                            @endif
                                                        </span>
                                                    </div>
                                                @else
                                                    <span class="absolute right-2 bottom-0.5 text-[11px] select-none z-0 flex items-center gap-1
                                                        {{ $isOut
                                                            ? 'text-[#4fae6a] dark:text-[#86efac] font-medium'
                                                            : 'text-cyan-800/60 dark:text-cyan-200/60'
                                                        }}">
                                                        {{ $timeLabel }}

                                                        @if($isOut)
                                                            @php
                                                                $st = $m->estado_envio ?? null;
                                                                $err = $m->last_error ?? null;
                                                                $errShort = $err ? \Illuminate\Support\Str::limit($err, 160) : null;
                                                            @endphp

                                                            @if($st === 'pending')
                                                                <span class="opacity-80" title="Enviando…">⏳</span>
                                                            @elseif($st === 'failed')
                                                                <span class="opacity-90" title="{{ $errShort ?: 'Error enviando' }}">⚠</span>
                                                            @elseif($st === 'sent')
                                                                <span class="opacity-80" title="Enviado">✓</span>
                                                            @endif
                                                        @endif
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

                    <div class="flex flex-col gap-2 {{ $canSend ? '' : 'opacity-60' }}">
                        {{-- Input hidden múltiple (TEMP: $upload) --}}
                        <input
                            type="file"
                            multiple
                            wire:model="upload"
                            wire:key="chat-upload-input-{{ $uploadInputKey }}"
                            class="hidden"
                            id="chat-upload-input"
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain,image/jpeg,image/png,image/webp"
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
                            <form wire:submit.prevent="send" class="flex items-center gap-2 flex-1">
                                <input
                                    type="text"
                                    wire:model.defer="message"
                                    placeholder="{{ $canSend ? 'Escribe un mensaje…' : 'Chat sin vincular (no se puede enviar)' }}"
                                    class="flex-1 rounded-2xl border border-black/5 dark:border-white/10 bg-white/80 dark:bg-slate-950/40 px-4 py-3 text-sm
                                        outline-none focus:outline-none focus:ring-0 focus:ring-offset-0
                                        focus:border-cyan-500/40 focus:shadow-[0_0_0_4px_rgba(34,211,238,.16)]
                                        disabled:cursor-not-allowed"
                                    {{ $canSend ? '' : 'disabled' }}
                                />

                                {{-- Botón inteligente: si hay pendientes -> sendFiles, si no -> send --}}
                                <button
                                    type="{{ $hasFiles ? 'button' : 'submit' }}"
                                    @if($hasFiles)
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

                        {{-- Estado mientras Livewire sube --}}
                        <div
                            class="text-[11px] text-gray-500 dark:text-gray-400"
                            wire:loading
                            wire:target="upload"
                        >
                            Subiendo archivo(s)…
                        </div>

                        {{-- Lista de archivos pendientes (BUFFER: $pendingUploads) --}}
                        @if($hasFiles)
                            <div class="rounded-2xl bg-white/70 dark:bg-slate-900/40 border border-black/5 dark:border-white/10 p-2">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="text-[11px] font-extrabold text-gray-700 dark:text-gray-200">
                                        Archivos listos ({{ count($pendingUploads) }})
                                    </div>

                                    <button
                                        type="button"
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

                                            <button
                                                type="button"
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
