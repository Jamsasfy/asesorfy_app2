@php
    /** @var \App\Models\Factura $record */
    $record  = $getRecord();
    $factura = $record;
    $urlPago = route('payment.pay', $factura);

    use App\Enums\FacturaEstadoEnum;
@endphp

@if ($factura->estado === FacturaEstadoEnum::PENDIENTE_PAGO)
    <div 
        x-data="{ copied: false }"
        class="flex flex-col gap-2"
    >
        {{-- Botón FILAMENT para copiar enlace --}}
        <x-filament::button
            size="xs"
            color="gray"
            icon="heroicon-o-clipboard-document"
            type="button"
            x-on:click="
                navigator.clipboard.writeText('{{ $urlPago }}');
                copied = true;
                setTimeout(() => copied = false, 1500);
            "
            class="justify-start"
        >
            <span x-show="!copied">Copiar enlace</span>
            <span x-show="copied">Enlace copiado</span>
        </x-filament::button>

        {{-- Botón FILAMENT para enviar enlace por email --}}
        <form method="POST" action="{{ route('payment.send-link', $factura) }}">
            @csrf
            <x-filament::button
                size="xs"
                color="success"
                icon="heroicon-o-envelope"
                type="submit"
                class="w-full justify-start"
            >
                Enviar enlace de pago por email
            </x-filament::button>
        </form>
    </div>
@else
    <div class="flex items-center gap-1 text-xs text-gray-500">
        <x-heroicon-o-check-circle class="w-4 h-4 text-success-500" />
        <span>Pago completado</span>
    </div>
@endif
