<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Copia de Contrato - {{ config('app.name') }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; background-color: #f3f4f6; margin: 0; padding: 0; color: #1f2937; line-height: 1.6; }
        .container { max-width: 600px; margin: 30px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb; }
        .header { background-color: #e0f7ff; padding: 24px; text-align: center; border-bottom: 1px solid #bae6fd; }
        .header img { height: 45px; width: auto; display: inline-block; }
        .content { padding: 40px 32px; }
        
        h1 { color: #0f172a; font-size: 24px; margin: 0 0 20px 0; font-weight: 700; }
        p { margin-bottom: 20px; color: #4b5563; font-size: 15px; }

        .highlight { background-color: #f0f9ff; border-left: 4px solid #0ea5e9; padding: 15px; margin: 25px 0; border-radius: 4px; font-size: 14px; color: #0369a1; }

        /* Estilos Pago */
        .alert-payment { background-color: #fff7ed; border: 1px solid #fed7aa; border-radius: 12px; padding: 20px; margin-top: 30px; text-align: center; }
        .pay-title { color: #9a3412; font-weight: 800; font-size: 18px; margin-bottom: 10px; }
        .pay-amount { font-size: 26px; font-weight: 800; color: #0f172a; margin: 15px 0; }
        .pay-btn { display: inline-block; background-color: #635bff; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold; margin-top: 10px; }
        
        .transfer-table { width: 100%; margin-top: 15px; background: #fff; border-radius: 8px; border: 1px solid #dbeafe; font-size: 14px; border-collapse: collapse; }
        .transfer-table td { padding: 8px 12px; border-bottom: 1px solid #eff6ff; }
        .label { color: #64748b; font-weight: 600; width: 90px; text-align: left; }
        .value { color: #1e293b; font-weight: bold; text-align: right; font-family: monospace; }

        .footer { background-color: #f8fafc; padding: 24px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}">
        </div>

        <div class="content">
            <h1>Hola, {{ $lead->nombre }} 👋</h1>
            
            <p>
                Tal y como nos has solicitado, te enviamos adjunta una copia de tu contrato de servicios con <strong>{{ config('app.name') }}</strong>.
            </p>

            <div class="highlight">
                <strong>📎 Documento Adjunto:</strong><br>
                Puedes descargar o guardar el PDF que acompaña a este correo.
            </div>

            {{-- BLOQUE DE PAGO PENDIENTE (Solo si debePagar es true) --}}
            @if($debePagar)
                
                <div class="alert-payment">
                    <div class="pay-title">⚠️ Aviso: Pago Pendiente</div>
                    <p style="font-size: 14px; color: #7c2d12; margin-bottom: 15px;">
                        Te recordamos que el servicio contratado está pendiente de abono. 
                        Para activarlo, es necesario completar el pago:
                    </p>

                    <div class="pay-amount">
                        {{ number_format($totalPagar, 2, ',', '.') }} €
                    </div>

                    @if($metodo === 'transferencia')
                        {{-- DATOS TRANSFERENCIA --}}
                        <table class="transfer-table">
                            <tr><td class="label">IBAN:</td><td class="value">{{ $iban }}</td></tr>
                            <tr><td class="label">Concepto:</td><td class="value">{{ $concepto }}</td></tr>
                            <tr><td class="label">Beneficiario:</td><td class="value">{{ config('app.name') }}</td></tr>
                        </table>
                        <p style="font-size: 12px; color: #64748b; margin-top: 10px;">* Responde con el justificante.</p>
                    
                    @else
                        {{-- BOTÓN TARJETA --}}
                        <a href="{{ route('payment.pay', ['venta' => $venta->id]) }}" class="pay-btn">
                            💳 Pagar Ahora
                        </a>
                        <p style="font-size: 12px; color: #9a3412; margin-top: 10px;">Pago seguro vía Stripe.</p>
                    @endif
                </div>

            @else
                <p style="margin-top: 30px;">
                    Si necesitas cualquier otra cosa, no dudes en pedírnosla.
                </p>
            @endif
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
        </div>
    </div>
</body>
</html>
