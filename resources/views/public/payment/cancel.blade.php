<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Pago no completado</title>
    <style>
        body {
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #f9fafb;
            padding: 30px;
            color: #1f2937;
        }
        .card {
            max-width: 580px;
            margin: 0 auto;
            background: white;
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border: 1px solid #e5e7eb;
        }
        .title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .text-muted {
            color: #6b7280;
            margin-bottom: 16px;
        }
        .btn {
            display: inline-block;
            padding: 12px 18px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            margin-top: 10px;
        }
        .btn-primary {
            background: #635bff;
            color: white;
        }
        .btn-primary:hover {
            background: #5147e0;
        }
        .btn-secondary {
            background: #e5e7eb;
            color: #1f2937;
        }
        .logo {
            text-align: center;
            margin-bottom: 18px;
        }
        .logo img {
            width: 160px;
        }
    </style>
</head>
<body>

<div class="card">

    <div class="logo">
        <img src="{{ asset('images/logo.png') }}" alt="AsesorFy">
    </div>

    <div class="title">El pago no se completó</div>

    <p class="text-muted">
        Parece que se ha cancelado el proceso de pago o no se ha podido finalizar.
    </p>

    @if(!$venta->tienePagoInicialCompletado())
        <p>
            Si deseas completar la contratación, elige cómo quieres realizar el pago:
        </p>

        <a href="{{ route('payment.pay', ['venta' => $venta->id]) }}" class="btn btn-primary" style="display:block; text-align:center; margin-bottom:10px;">
            💳 Pagar con tarjeta
        </a>

        <div style="background:#f3f4f6; border-radius:10px; padding:16px; margin-top:10px;">
            <div style="font-weight:600; margin-bottom:8px;">🏦 Pagar por transferencia bancaria</div>
            <p style="font-size:13px; color:#374151; margin-bottom:6px;">
                Realiza una transferencia con los siguientes datos e indícanos el concepto para identificar tu pago:
            </p>
            <div style="font-size:13px; line-height:1.8;">
                <strong>Titular:</strong> AsesorFy S.L.<br>
                <strong>IBAN:</strong> {{ $iban }}<br>
                <strong>Concepto:</strong> Contrato #{{ $venta->id }} — {{ $venta->cliente->nombre ?? $venta->cliente->razon_social }}
            </div>
            <p style="font-size:12px; color:#6b7280; margin-top:10px;">
                Una vez recibida la transferencia, activaremos tu servicio en un plazo máximo de 24-48h laborables y recibirás confirmación por email.
            </p>
        </div>

        <p class="text-muted" style="margin-top: 16px; font-size:12px;">
            ¿Tienes dudas? Contacta con tu asesor o escríbenos a <strong>info@asesorfy.net</strong>.
        </p>
    @else
        <p>
            El sistema nos indica que tu pago ya se ha registrado correctamente.  
            Si ves este mensaje por error, no te preocupes: tu contratación está activa.
        </p>

        <a href="https://asesorfy.net" class="btn btn-secondary">
            Volver a AsesorFy
        </a>
    @endif

</div>

</body>
</html>
