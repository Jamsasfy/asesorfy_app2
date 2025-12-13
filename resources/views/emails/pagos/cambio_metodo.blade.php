<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actualización de Método de Pago - {{ config('app.name') }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; background-color: #f3f4f6; margin: 0; padding: 0; color: #1f2937; line-height: 1.6; }
        .container { max-width: 600px; margin: 30px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb; }
        .header { background-color: #e0f7ff; padding: 24px; text-align: center; border-bottom: 1px solid #bae6fd; }
        .header img { height: 45px; width: auto; }
        .content { padding: 32px; }
        h1 { color: #0f172a; font-size: 22px; margin: 0 0 16px; }
        p { margin-bottom: 16px; color: #4b5563; }
        
        .transfer-box { background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 25px; margin: 20px 0; }
        .pay-box { text-align: center; margin: 30px 0; padding: 25px; background-color: #fff7ed; border: 1px solid #fed7aa; border-radius: 12px; }

        .pay-btn { display: inline-block; background-color: #635bff; color: #ffffff !important; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: bold; font-size: 16px; margin-top: 15px; box-shadow: 0 4px 6px rgba(99, 91, 255, 0.2); }
        .pay-btn:hover { background-color: #5346e0; }

        .data-table { width: 100%; border-collapse: collapse; margin-top: 15px; background: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #dbeafe; }
        .data-table td { padding: 12px 15px; border-bottom: 1px solid #eff6ff; vertical-align: middle; }
        .label { color: #64748b; font-size: 13px; font-weight: 600; width: 100px; }
        .value { color: #1e293b; font-weight: bold; font-family: monospace; font-size: 15px; text-align: right; }
        
        .note-box { background-color: #f8fafc; border-left: 4px solid #94a3b8; padding: 12px; font-size: 13px; color: #64748b; margin-top: 20px; font-style: italic; }
        .footer { background-color: #f8fafc; padding: 24px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}">
        </div>

        {{-- LÓGICA PHP DE CÁLCULO (IGUALADA AL CONTRATO) --}}
        @php
            // 1. Obtener nombres de servicios ÚNICOS
            $nombresServicios = $venta->items
                ->filter(fn($item) => $item->servicio && $item->servicio->tipo->value === 'unico')
                ->map(fn($item) => $item->nombre_personalizado ?: $item->servicio->nombre)
                ->implode(', ');
            
            if (empty($nombresServicios)) {
                $nombresServicios = 'servicio contratado';
            }

            // 2. CÁLCULO DEL TOTAL EXACTO (Solo Servicios Únicos + IVA)
            $totalPagar = 0;
            foreach ($venta->items as $item) {
                // Solo sumamos lo que sea de tipo 'unico'
                if ($item->servicio && $item->servicio->tipo->value === 'unico') {
                    $base = (float) $item->precio_unitario_aplicado; 
                    $totalPagar += $base * $item->cantidad;
                }
            }
            // Aplicamos el 21% de IVA
            $totalPagar = $totalPagar * 1.21;
        @endphp

        <div class="content">
            <h1>Hola, {{ $venta->cliente->razon_social ?? 'Cliente' }}</h1>
            
            <p>
                Te informamos que hemos actualizado el método de pago para el servicio de 
                <strong>{{ $nombresServicios }}</strong>.
                A continuación encontrarás los nuevos detalles:
            </p>

            {{-- 🔹 CASO 1: TRANSFERENCIA --}}
            @if($venta->pago_inicial_metodo === 'transferencia')
                <div class="transfer-box">
                    <h3 style="margin-top: 0; color: #1e40af; text-align: center;">Datos para Transferencia</h3>
                    
                    <p style="text-align: center; margin-bottom: 20px; font-size: 14px; color: #3b82f6;">
                        Realiza el ingreso del siguiente importe:
                    </p>

                    {{-- PRECIO CORREGIDO --}}
                    <div style="font-size: 28px; font-weight: 800; color: #1e3a8a; text-align: center; margin-bottom: 20px;">
                        {{ number_format($totalPagar, 2, ',', '.') }} €
                    </div>

                    <table class="data-table">
                        <tr>
                            <td class="label">IBAN:</td>
                            <td class="value">{{ $iban }}</td>
                        </tr>
                        <tr>
                            <td class="label">Concepto:</td>
                            <td class="value">{{ $concepto }}</td>
                        </tr>
                        <tr>
                            <td class="label">Beneficiario:</td>
                            <td class="value">{{ config('app.name') }}</td>
                        </tr>
                    </table>

                    <p style="text-align: center; font-size: 12px; color: #64748b; margin-top: 15px; margin-bottom: 0;">
                        * Responde a este email con el justificante para agilizar la activación.
                    </p>
                </div>

            {{-- 🔸 CASO 2: TARJETA (Stripe) --}}
            @else
                <div class="pay-box">
                    <h3 style="margin-top: 0; color: #9a3412;">Pago con Tarjeta</h3>
                    <p style="margin-bottom: 10px; color: #7c2d12;">
                        Puedes abonar el servicio de <strong>{{ $nombresServicios }}</strong> de forma segura e inmediata:
                    </p>
                    
                    {{-- PRECIO CORREGIDO --}}
                    <div style="font-size: 26px; font-weight: 800; color: #0f172a; margin: 15px 0;">
                        {{ number_format($totalPagar, 2, ',', '.') }} €
                    </div>

                    <a href="{{ route('payment.pay', ['venta' => $venta->id]) }}" class="pay-btn">
                        💳 Pagar Ahora
                    </a>
                    
                    <p style="font-size: 12px; color: #9ca3af; margin-top: 15px; margin-bottom: 0;">
                        Pago procesado de forma segura por Stripe.
                    </p>
                </div>
            @endif

            @if(!empty($venta->pago_inicial_notas))
                <div class="note-box">
                    <strong>Nota de tu asesor:</strong><br>
                    "{{ $venta->pago_inicial_notas }}"
                </div>
            @endif

            <p style="margin-top: 30px;">
                Si tienes alguna duda, responde a este correo y te ayudaremos.
            </p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.<br>
            <a href="{{ config('app.url') }}" style="color: #64748b; text-decoration: underline;">{{ config('app.url') }}</a>
        </div>
    </div>
</body>
</html>