<x-filament-widgets::widget>
    @php
        $notificaciones = $this->getNotificaciones();
        $accent = '#41c0e9';
    @endphp

    @if($notificaciones->isNotEmpty())
    <div class="space-y-4">
        @foreach($notificaciones as $notif)
            @php
                // Colores según tipo
                $colors = match($notif->tipo) {
                    'critico' => [
                        'bg' => 'from-red-500 to-red-600',
                        'ring' => 'ring-red-500/20',
                        'icon_bg' => 'bg-white/20',
                        'badge_bg' => 'bg-red-900/50',
                        'text' => 'text-white',
                        'button' => 'bg-white/20 hover:bg-white/30 text-white',
                    ],
                    'urgente' => [
                        'bg' => 'from-orange-500 to-orange-600',
                        'ring' => 'ring-orange-500/20',
                        'icon_bg' => 'bg-white/20',
                        'badge_bg' => 'bg-orange-900/50',
                        'text' => 'text-white',
                        'button' => 'bg-white/20 hover:bg-white/30 text-white',
                    ],
                    'aviso' => [
                        'bg' => 'from-yellow-500 to-yellow-600',
                        'ring' => 'ring-yellow-500/20',
                        'icon_bg' => 'bg-white/20',
                        'badge_bg' => 'bg-yellow-900/50',
                        'text' => 'text-white',
                        'button' => 'bg-white/20 hover:bg-white/30 text-white',
                    ],
                    'info' => [
                        'bg' => 'from-blue-500 to-cyan-500',
                        'ring' => 'ring-blue-500/20',
                        'icon_bg' => 'bg-white/20',
                        'badge_bg' => 'bg-blue-900/50',
                        'text' => 'text-white',
                        'button' => 'bg-white/20 hover:bg-white/30 text-white',
                    ],
                };
            @endphp

            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r {{ $colors['bg'] }} p-6 shadow-xl ring-2 {{ $colors['ring'] }} animate-in slide-in-from-top duration-500">
                
                <div class="relative flex items-start justify-between gap-4">
                    <div class="flex items-start gap-4 flex-1">
                        {{-- (Icono eliminado) --}}

                        <div class="flex-1">
                            {{-- Badge de tipo mejorado --}}
                            <div class="inline-flex items-center gap-1.5 rounded-lg {{ $colors['badge_bg'] }} px-2.5 py-1 text-xs font-bold {{ $colors['text'] }} mb-3 backdrop-blur-sm border border-white/20">
                                @if($notif->tipo === 'critico') 
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    CRÍTICO
                                @elseif($notif->tipo === 'urgente') 
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
                                    </svg>
                                    URGENTE
                                @elseif($notif->tipo === 'aviso') 
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                    AVISO
                                @else 
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                    INFORMACIÓN
                                @endif
                            </div>

                            {{-- Título --}}
                            <h3 class="text-xl font-black {{ $colors['text'] }} mb-3 drop-shadow-sm">
                                {{ $notif->titulo }}
                            </h3>

                            {{-- Mensaje --}}
                            <div class="text-sm {{ $colors['text'] }} prose-sm prose-invert max-w-none opacity-95 mb-3 leading-relaxed">
                                {!! $notif->mensaje !!}
                            </div>

                            {{-- Fecha --}}
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 {{ $colors['text'] }} opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-xs {{ $colors['text'] }} opacity-80 font-semibold">
                                    {{ $notif->fecha_publicacion?->diffForHumans() ?? $notif->created_at->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Botón marcar leída --}}
                    @if(!$notif->bloquea_portal)
                    <button 
                        wire:click="marcarComoLeida({{ $notif->id }})"
                        class="shrink-0 flex items-center gap-2 rounded-xl {{ $colors['button'] }} px-4 py-3 text-sm font-bold transition-all hover:scale-105 active:scale-95 shadow-lg backdrop-blur-sm border border-white/20"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <span class="hidden lg:inline">Marcar leída y cerrar</span>
                        <span class="lg:hidden">Cerrar</span>
                    </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
    @endif
</x-filament-widgets::widget>
