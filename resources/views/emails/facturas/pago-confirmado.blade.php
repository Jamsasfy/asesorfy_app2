<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Confirmado - {{ config('app.name') }}</title>
    <style>
        /* Estilos base */
        body { font-family: 'Helvetica', 'Arial', sans-serif; background-color: #f3f4f6; margin: 0; padding: 0; color: #1f2937; line-height: 1.6; }
        .container { max-width: 600px; margin: 30px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb; }
        .header { background-color: #e0f7ff; padding: 24px; text-align: center; border-bottom: 1px solid #bae6fd; }
        .header img { height: 45px; width: auto; display: inline-block; }
        .content { padding: 40px 32px; }
        .amount-box { background-color: #ecfdf5; border: 1px solid #a7f3d0; color: #047857; padding: 20px; border-radius: 12px; text-align: center; font-size: 22px; font-weight: 700; margin: 30px 0; letter-spacing: -0.5px; }
        .services-list { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px; margin-bottom: 35px; }
        .service-item { margin-bottom: 10px; font-weight: 600; color: #334155; font-size: 16px; display: block; }
        .footer { background-color: #f8fafc; padding: 24px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    
    @php
        $venta = $factura->venta;
        $items = $venta?->items ?? collect();
        
        $unicos = $items->filter(fn($i) => optional($i->servicio)->tipo?->value === 'unico');
        $recurrentes = $items->filter(fn($i) => optional($i->servicio)->tipo?->value === 'recurrente');

        $metodo = $factura->metodo_pago ?? $venta->pago_inicial_metodo;
        $textoMetodo = match($metodo) {
            'transferencia' => 'por transferencia bancaria',
            'tarjeta', 'stripe' => 'por tarjeta',
            default => 'correctamente',
        };

        $nombresUnicos = $unicos->map(fn($i) => $i->nombre_personalizado ?: $i->servicio->nombre)->implode(', ');
        $nombresRecurrentes = $recurrentes->map(fn($i) => $i->nombre_personalizado ?: $i->servicio->nombre)->implode(', ');
    @endphp

    <div class="container">
        {{-- Cabecera --}}
        <div class="header">
            <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}">
        </div>

        {{-- Contenido --}}
        <div class="content">
            <h1 style="color: #0f172a; font-size: 24px; margin: 0 0 20px 0; font-weight: 700; letter-spacing: -0.5px;">
                Pago Recibido ✅
            </h1>
            
            <p style="margin-bottom: 20px; color: #4b5563; font-size: 15px; line-height: 1.6;">
                Hola <strong>{{ $venta->cliente->razon_social ?? 'Cliente' }}</strong>,<br>
                Te confirmamos que hemos recibido el pago <strong>{{ $textoMetodo }}</strong> correspondiente a tus servicios con {{ config('app.name') }}.
            </p>

            {{-- Caja de Importe --}}
            <div class="amount-box">
                Importe pagado: {{ number_format($factura->total_factura ?? 0, 2, ',', '.') }} €
            </div>

            {{-- Lista de Servicios Abonados (SOLO ÚNICOS) --}}
            @if($unicos->isNotEmpty())
                <div class="services-list">
                    <h3 style="margin-top: 0; font-size: 13px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; border-bottom: 1px solid #cbd5e1; padding-bottom: 12px; margin-bottom: 15px; font-weight: 700;">
                        🧾 SERVICIOS ABONADOS
                    </h3>
                    @foreach ($unicos as $item)
                        <div class="service-item">
                            • {{ $item->nombre_personalizado ?: $item->servicio->nombre }}
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- 1. BLOQUE SERVICIOS PUNTUALES (Estilos en línea forzados) --}}
            @if($unicos->isNotEmpty())
                <div style="margin-top: 35px; padding-top: 25px; border-top: 1px solid #e5e7eb;">
                    <div style="font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                        🎯 Servicios puntuales
                    </div>
                    <div style="font-size: 15px; color: #475569; margin: 0; line-height: 1.8;">
                        El servicio de <strong>{{ $nombresUnicos }}</strong> ha sido abonado correctamente y comenzamos su tramitación.
                    </div>
                </div>
            @endif

            {{-- 2. BLOQUE SERVICIOS RECURRENTES (Estilos en línea forzados) --}}
            @if($recurrentes->isNotEmpty())
                <div style="margin-top: 35px; padding-top: 25px; border-top: 1px solid #e5e7eb;">
                    <div style="font-size: 18px; font-weight: 800; color: #0f172a; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                        🔁 Servicios recurrentes
                    </div>
                    <div style="font-size: 15px; color: #475569; margin: 0; line-height: 1.8;">
                        El servicio de <strong>{{ $nombresRecurrentes }}</strong> queda activado (o pendiente de inicio tras el trámite).
                        Las cuotas periódicas se gestionarán automáticamente según lo previsto.
                    </div>
                </div>
            @endif

            <p style="margin-top: 40px; color: #4b5563; border-top: 1px dashed #e5e7eb; padding-top: 20px;">
                Tu asesor continuará ahora con el proceso interno.
                Si necesita algún dato adicional, se pondrá en contacto contigo.
            </p>
        </div>

        {{-- Pie --}}
        <div class="footer">
            Gracias por confiar en nosotros.<br>
            &copy; {{ date('Y') }} {{ config('app.name') }}.
        </div>
    </div>
</body>
</html>