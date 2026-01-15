{{-- resources/views/filament/pages/_mis-chats-meta.blade.php --}}

{{ $timeLabel }}

@if($respondioOtro)
    <span
        class="inline-flex items-center rounded-full p-0.5
            bg-amber-500/20 dark:bg-amber-300/20
            text-amber-500 dark:text-amber-300
            drop-shadow-[0_1px_1px_rgba(0,0,0,.35)]"
        title="Respondido por {{ $m->user?->name ?? 'otro usuario' }} (no es el asesor asignado)"
    >
        <x-heroicon-o-user-circle class="h-4 w-4" />
    </span>
@endif

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
