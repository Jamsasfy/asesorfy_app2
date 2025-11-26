<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato Firmado - AsesorFy</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
            color: #1f2937;
            line-height: 1.6;
        }
        .container {
            max-width: 600px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }
        .header {
            background-color: #e0f7ff; /* Azul claro */
            padding: 24px;
            text-align: center;
            border-bottom: 1px solid #bae6fd;
        }
        .header img { height: 45px; width: auto; }
        .content { padding: 32px; }
        h1 { color: #0f172a; font-size: 22px; margin: 0 0 16px; }
        p { margin-bottom: 16px; color: #4b5563; }
        
        /* Caja destacada contrato */
        .highlight {
            background-color: #f0f9ff;
            border-left: 4px solid #0ea5e9;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #0369a1;
        }

        /* Caja de PAGO */
        .pay-box {
            text-align: center;
            margin: 30px 0;
            padding: 25px;
            background-color: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 12px;
        }
        .pay-btn {
            display: inline-block;
            background-color: #635bff; /* Morado Stripe */
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 16px;
            margin-top: 15px;
            box-shadow: 0 4px 6px rgba(99, 91, 255, 0.2);
        }
        .pay-btn:hover { background-color: #5346e0; }

        .footer {
            background-color: #f8fafc;
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="container">
        {{-- Cabecera --}}
        <div class="header">
            <img src="{{ asset('images/logo.png') }}" alt="AsesorFy">
        </div>

        {{-- Contenido --}}
        <div class="content">
            <h1>¡Hola, {{ $lead->nombre }}! 👋</h1>
            
            <p>
                Te confirmamos que hemos recibido la firma de tu contrato correctamente.
                El proceso de alta ha quedado registrado en nuestros sistemas.
            </p>

            <div class="highlight">
                <strong>📄 Contrato Adjunto:</strong><br>
                Encontrarás una copia en formato PDF de tu contrato de servicios firmado adjunta a este correo.
            </div>

            {{-- BLOQUE DE PAGO (Solo si hay factura pendiente) --}}
            @if(isset($factura) && $factura)
                <div class="pay-box">
                    <h3 style="margin-top: 0; color: #9a3412;">Finalizar Contratación</h3>
                    <p style="margin-bottom: 10px; color: #7c2d12;">
                        Para activar los servicios de <strong>Constitución, Alta o Trámites</strong>, 
                        es necesario abonar la provisión de fondos.
                    </p>
                    
                    <div style="font-size: 24px; font-weight: 800; color: #0f172a; margin-bottom: 15px;">
                        {{ number_format($factura->total_factura, 2, ',', '.') }} €
                    </div>

                    <a href="{{ route('payment.pay', $factura->id) }}" class="pay-btn">
                        💳 Pagar con Tarjeta
                    </a>
                    
                    <p style="font-size: 12px; color: #9ca3af; margin-top: 15px;">
                        Pago seguro procesado por Stripe.
                    </p>
                </div>
            @endif

            <p>
                Guarda este email como justificante de tu contratación.
                Si tienes cualquier duda, responde a este correo y tu asesor te ayudará.
            </p>
        </div>

        {{-- Pie --}}
        <div class="footer">
            &copy; {{ date('Y') }} AsesorFy. Todos los derechos reservados.<br>
        </div>
    </div>
</body>
</html>