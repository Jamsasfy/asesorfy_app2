@php
    /** @var \App\Models\Lead $lead */
    $lead = $getRecord();
    $ventas = $lead->ventas()->with('facturas')->get();
@endphp

@if ($ventas->isEmpty())
    <p style="font-size: 14px; color: #9ca3af;">
        Este lead todavía no tiene ventas ni facturas asociadas.
    </p>
@else
    <div style="border-radius: 14px; border: 1px solid #1f2937; padding: 12px 12px 10px; background: #020617;">
        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
            <thead>
                <tr style="text-align: left; color: #9ca3af; border-bottom: 1px solid #1f2937;">
                    <th style="padding: 6px 4px;">Venta</th>
                    <th style="padding: 6px 4px;">Factura</th>
                    <th style="padding: 6px 4px;">Estado</th>
                    <th style="padding: 6px 4px; text-align:right;">Importe</th>
                    <th style="padding: 6px 4px;">Pago</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ventas as $venta)
                    @forelse ($venta->facturas as $factura)
                        @php
                            /** @var \App\Enums\FacturaEstadoEnum $estado */
                            $estado = $factura->estado;
                            $isPendiente = $estado === \App\Enums\FacturaEstadoEnum::PENDIENTE_PAGO;
                            $paymentUrl  = route('payment.pay', $factura);

                            // Etiqueta bonita desde el enum
                            $estadoLabel = $estado->getLabel() ?? $estado->value;
                        @endphp
                        <tr style="border-bottom: 1px solid #111827;">
                            {{-- Venta --}}
                            <td style="padding: 6px 4px;">
                                #{{ $venta->id }}<br>
                                <span style="color:#9ca3af;">
                                    {{ optional($venta->fecha_venta ?? $venta->created_at)->format('d/m/Y') }}
                                </span>
                            </td>

                            {{-- Factura --}}
                            <td style="padding: 6px 4px;">
                                {{ $factura->serie ?? '' }}{{ $factura->numero_factura ?? '' }}<br>
                                <span style="color:#9ca3af;">
                                    {{ optional($factura->fecha_emision ?? $factura->created_at)->format('d/m/Y') }}
                                </span>
                            </td>

                            {{-- Estado --}}
                            <td style="padding: 6px 4px;">
                                <span style="
                                    display:inline-flex;
                                    align-items:center;
                                    padding:2px 8px;
                                    border-radius:999px;
                                    font-size:11px;
                                    background: {{ $isPendiente ? 'rgba(251,191,36,.12)' : 'rgba(52,211,153,.12)' }};
                                    color: {{ $isPendiente ? '#fbbf24' : '#22c55e' }};
                                    border: 1px solid {{ $isPendiente ? 'rgba(251,191,36,.5)' : 'rgba(52,211,153,.45)' }};
                                ">
                                    {{ $estadoLabel }}
                                </span>
                            </td>

                            {{-- Importe --}}
                            <td style="padding: 6px 4px; text-align:right;">
                                {{ number_format($factura->total_factura ?? 0, 2, ',', '.') }} €
                            </td>

                            {{-- Pago / enlace --}}
                            <td style="padding: 6px 4px;">
                                @if ($isPendiente)
                                    <div style="display:flex; flex-direction:column; gap:4px;">
                                        {{-- Campo con la URL de pago para copiar --}}
                                        <input
                                            type="text"
                                            readonly
                                            value="{{ $paymentUrl }}"
                                            style="
                                                font-size:11px;
                                                padding:4px 6px;
                                                border-radius:6px;
                                                border:1px solid #374151;
                                                background:#020617;
                                                color:#e5e7eb;
                                                width:100%;
                                            "
                                            onclick="this.select(); document.execCommand('copy');"
                                        >

                                        {{-- Botón para enviar enlace por email --}}
                                        <form
                                            method="POST"
                                            action="{{ route('payment.send-link', $factura) }}"
                                            style="margin:0;"
                                        >
                                            @csrf
                                            <button type="submit"
                                                style="
                                                    font-size:11px;
                                                    padding:4px 8px;
                                                    border-radius:999px;
                                                    border:1px solid #22c55e;
                                                    background:rgba(34,197,94,.12);
                                                    color:#bbf7d0;
                                                    cursor:pointer;
                                                "
                                            >
                                                Enviar enlace por email
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <span style="font-size:11px; color:#9ca3af;">
                                        Pago completado
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" style="padding: 6px 4px; color:#9ca3af;">
                                Esta venta no tiene facturas generadas todavía.
                            </td>
                        </tr>
                    @endforelse
                @endforeach
            </tbody>
        </table>
    </div>
@endif
