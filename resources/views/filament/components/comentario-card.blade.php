@php
    use Illuminate\Support\Str;

    $record = $getRecord();
    $usuario = $record->user?->name ?? 'Usuario';
    $esBot = Str::lower($usuario) === 'boot ia fy';

    $avatar = $esBot ? '🤖' : strtoupper(Str::of($usuario)->substr(0, 2));
@endphp

<div style="
    display:flex;
    align-items:flex-start;
    gap:0.5rem;
    margin:0.25rem 0;
">

    {{-- Avatar --}}
    <div style="
        width:36px;
        height:36px;
        border-radius:9999px;
        background-color: {{ $esBot ? '#6366f1' : '#3b82f6' }};
        color:white;
        font-weight:700;
        display:flex;
        align-items:center;
        justify-content:center;
        flex-shrink:0;
        font-size:0.85rem;
    ">
        {{ $avatar }}
    </div>

    {{-- Burbuja --}}
    <div style="
        display:flex;
        align-items:center;
        gap:0.5rem;
        flex:1;
        padding:0.5rem 0.75rem;
        border-radius:0.75rem;
        font-size:0.9rem;
        line-height:1.25;

        background-color: var(--gray-100);
        color: var(--gray-800);
    "
    class="dark:bg-gray-800 dark:text-gray-100"
    >

        {{-- Usuario --}}
        <span style="font-weight:600; white-space:nowrap;">
            {{ $esBot ? '🤖 Boot IA Fy' : '🧑‍💼 ' . $usuario }}
        </span>

        {{-- Contenido --}}
        <span style="
            flex:1;
            overflow:hidden;
            text-overflow:ellipsis;
            white-space:nowrap;
        ">
            {{ $record->contenido }}
        </span>

        {{-- Fecha --}}
        <span style="
            font-size:0.75rem;
            opacity:0.7;
            white-space:nowrap;
        " title="{{ $record->created_at?->format('d/m/Y H:i:s') }}">
            🕓 {{ $record->created_at?->format('d/m/Y H:i') }}
        </span>

    </div>
</div>
