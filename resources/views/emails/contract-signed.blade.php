<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato Firmado - {{ config('app.name') }}</title>
    <style>
        /* Estilos base */
        body { font-family: 'Helvetica', 'Arial', sans-serif; background-color: #f3f4f6; margin: 0; padding: 0; color: #1f2937; line-height: 1.6; }
        .container { max-width: 600px; margin: 30px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb; }
        .header { background-color: #e0f7ff; padding: 24px; text-align: center; border-bottom: 1px solid #bae6fd; }
        .header img { height: 45px; width: auto; display: inline-block; }
        .content { padding: 40px 32px; }
        
        h1 { color: #0f172a; font-size: 24px; margin: 0 0 20px 0; font-weight: 700; }
        p { margin-bottom: 20px; color: #4b5563; font-size: 15px; }

        /* Caja Verde Contrato */
        .highlight { background-color: #f0fdf4; border-left: 4px solid #22c55e; padding: 15px; margin: 25px 0; border-radius: 4px; font-size: 14px; color: #166534; }

        /* Caja Azul Transferencia */
        .transfer-box { background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 25px; margin: 30px 0; }
        .data-table { width: 100%; margin-top: 15px; border-collapse: collapse; background: #fff; border-radius: 8px; border: 1px solid #dbeafe; }
        .data-table td { padding: 10px 15px; border-bottom: 1px solid #eff6ff; font-size: 14px; }
        .label { color: #64748b; font-weight: 600; width: 100px; }
        .value { color: #1e293b; font-weight: bold; text-align: right; font-family: monospace; }

        /* Caja Naranja Tarjeta */
        .pay-box { background-color: #fff7ed; border: 1px solid #fed7aa; border-radius: 12px; padding: 25px; text-align: center; margin: 30px 0; }
        .pay-btn { display: inline-block; background-color: #635bff; color: #ffffff !important; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: bold; margin-top: 15px; }
        
        .amount-big { font-size: 26px; font-weight: 800; color: #0f172a; margin: 15px 0; }

        .footer { background-color: #f8fafc; padding: 24px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    
    @php
        // 1. Recuperar Venta (si no viene pasada, la buscamos)
        $venta = $venta ?? $lead->ventas()->latest()->first();
        
        // 2. Verificar si hay pago pendiente
        $debePagar = $venta && $venta->requierePagoInicial() && !$venta->tienePagoInicialCompletado();
        
        // 3. Método y Datos
        $metodo = $venta->pago_inicial_metodo ?? 'tarjeta';
        
        if ($metodo === 'transferencia') {
            $iban = \App\Models\VariableConfiguracion::where('nombre_variable', 'iban_transferencias')->value('valor_variable') 
                 ?? \App\Models\VariableConfiguracion::where('nombre_variable', 'empresa_iban')->value('valor_variable');
            
            // Concepto: DNI - Venta #ID
            $dni = $lead->dni ?? $lead->cif ?? $venta->cliente->dni_cif ?? '---';
            $concepto = trim($dni . " - Venta #" . ($venta->id ?? '---'));
        }

        // 4. CÁLCULO DEL TOTAL EXACTO (Solo Servicios Únicos + IVA)
        // Esto corrige el error de sumar recurrentes o aplicar doble IVA
        $totalPagar = 0;
        if ($venta) {
            foreach ($venta->items as $item) {
                // Solo sumamos lo que sea de tipo 'unico' (igual que en el controlador Web)
                if ($item->servicio && $item->servicio->tipo->value === 'unico') {
                    $base = (float) $item->precio_unitario_aplicado; // Precio con descuento si lo hubiera
                    $totalPagar += $base * $item->cantidad;
                }
            }
            // Aplicamos el 21% de IVA al final
            $totalPagar = $totalPagar * 1.21;
        }
    @endphp

    <div class="container">
        {{-- Cabecera --}}
        <div class="header">
            <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}">
        </div>

        {{-- Contenido --}}
        <div class="content">
            <h1>¡Hola, {{ $lead->nombre }}! 👋</h1>
            
            <p>
                Te confirmamos que hemos recibido tu firma correctamente.
                El proceso de alta ha quedado registrado en nuestros sistemas.
            </p>

            <div class="highlight">
                <strong>📄 Contrato Adjunto:</strong><br>
                Encontrarás una copia en PDF de tu contrato de servicios firmado adjunta a este correo.
            </div>

            {{-- BLOQUE DE PAGO (SOLO SI DEBE PAGAR) --}}
            @if($debePagar)
                
                {{-- OPCIÓN A: TRANSFERENCIA --}}
                @if($metodo === 'transferencia')
                    <div class="transfer-box">
                        <h3 style="margin-top: 0; color: #1e40af; text-align: center;">Siguiente paso: Activación</h3>
                        
                        <p style="text-align: center; font-size: 14px; color: #3b82f6; margin-bottom: 20px;">
                            Ha seleccionado pagar por transferencia. Para continuar, debe abonar el servicio:
                        </p>

                        {{-- PRECIO CON IVA --}}
                        <div style="font-size: 28px; font-weight: 800; color: #1e3a8a; text-align: center; margin-bottom: 20px;">
                            {{ number_format($totalPagar, 2, ',', '.') }} €
                        </div>

                        <table class="data-table">
                            <tr>
                                <td class="label">Beneficiario:</td>
                                <td class="value">{{ config('app.name') }}</td>
                            </tr>
                            <tr>
                                <td class="label">IBAN:</td>
                                <td class="value">{{ $iban }}</td>
                            </tr>
                            <tr>
                                <td class="label">Concepto:</td>
                                <td class="value">{{ $concepto }}</td>
                            </tr>
                        </table>
                        
                        <p style="text-align: center; font-size: 12px; color: #64748b; margin-top: 15px; margin-bottom: 0;">
                            * Responde a este email con el justificante.
                        </p>
                    </div>

                {{-- OPCIÓN B: TARJETA --}}
                @else
                    <div class="pay-box">
                        <h3 style="margin-top: 0; color: #9a3412;">Finalizar Contratación</h3>
                        
                        <p style="color: #7c2d12; font-size: 14px; margin-bottom: 10px;">
                            Para comenzar es necesario abonar el servicio contratado.
                        </p>
                        
                        {{-- PRECIO CON IVA --}}
                        <div class="amount-big">
                            {{ number_format($totalPagar, 2, ',', '.') }} €
                        </div>

                        <a href="{{ route('payment.pay', ['venta' => $venta->id]) }}" class="pay-btn">
                            💳 Pagar con Tarjeta
                        </a>
                        
                        <p style="font-size: 12px; color: #9ca3af; margin-top: 15px; margin-bottom: 0;">
                            Pago seguro procesado por Stripe.
                        </p>
                    </div>
                @endif

            @else
                {{-- SI NO HAY QUE PAGAR NADA --}}
                <p style="margin-top: 30px; border-top: 1px dashed #e5e7eb; padding-top: 20px;">
                    Tu servicio ha quedado activado. Tu asesor se pondrá en contacto contigo en breve para comenzar a trabajar.
                </p>
            @endif

            <p style="margin-top: 30px;">
                Guarda este email como justificante de tu contratación.
            </p>
        </div>

        {{-- Pie --}}
        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>