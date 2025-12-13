{{-- resources/views/public/conversion/recurrente-pendiente.blade.php --}}
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Configurar Pago Recurrente</title>

    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">

    <style>
        :root {
            --bg:#0b1220;
            --card:#0f172a;
            --muted:#94a3b8;
            --border:#1f2a44;
            --ok:#22c55e;
            --warn:#fbbf24;
            --btn:#3b82f6;
            --btn-h:#1e40af;
        }
        body {
            margin: 0;
            background: var(--bg);
            color: #e5e7eb;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto;
        }
        .wrap { max-width: 760px; margin: 40px auto; padding: 18px; }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            padding: 28px;
            border-radius: 18px;
            box-shadow: 0 20px 40px rgba(0,0,0,.35);
        }
        h1 { font-size: 26px; margin: 0 0 8px; font-weight: 800; }
        .muted { color: var(--muted); }

        .warning-box {
            background: rgba(251,191,36,0.08);
            border: 1px solid rgba(251,191,36,0.3);
            color: var(--warn);
            padding: 14px;
            border-radius: 12px;
            margin: 18px 0;
            font-size: 0.95rem;
        }

        .btn {
            display: inline-flex;
            background: var(--btn);
            padding: 12px 18px;
            border-radius: 12px;
            color: #fff;
            font-weight: 700;
            text-decoration: none;
            font-size: 1rem;
            transition: 0.15s;
            margin-top: 12px;
        }
        .btn:hover { background: var(--btn-h); transform: translateY(-2px); }

        .footer {
            margin-top: 20px;
            text-align: center;
        }
        .footer a {
            color: #93c5fd;
            text-decoration: underline;
        }
    </style>
</head>

<body>

<div class="wrap">
    <div class="card">

        <h1>Configurar Pago Recurrente</h1>
        <p class="muted">
            Tu contrato está firmado, pero falta completar la configuración del método de pago
            para activar tus servicios recurrentes.
        </p>

        @php
            $cliente   = $cliente ?? null;
            $preferencia = $cliente->preferencia_pago_recurrente ?? 'tarjeta';
        @endphp

        {{-- TARJETA --}}
        @if($preferencia === 'tarjeta')
            <div class="warning-box">
                <strong>Falta registrar una tarjeta para los pagos mensuales.</strong><br>
                Se usará automáticamente para tus cuotas de AsesorFy.
            </div>

            <a class="btn"
               href="{{ route('stripe.setup-card', ['token' => $token]) }}">
                💳 Registrar Tarjeta Ahora
            </a>

            <p class="muted" style="margin-top:12px;">
                Pagos seguros procesados por Stripe.
            </p>
        @endif

        {{-- DOMICILIACIÓN SEPA --}}
        @if($preferencia === 'domiciliacion')
            <div class="warning-box" style="border-color:#38bdf8; color:#38bdf8;">
                <strong>Falta firmar el mandato SEPA.</strong><br>
                Añade tu IBAN para autorizar los cargos automáticos mensuales.
            </div>

            <a class="btn"
               href="{{ route('stripe.setup-sepa', ['token' => $token]) }}"
               style="background:#0ea5e9;">
                🧾 Firmar Mandato SEPA
            </a>

            <p class="muted" style="margin-top:12px;">
                Tus datos bancarios se procesan de forma segura con Stripe.
            </p>
        @endif

        <div class="footer">
            <a href="https://asesorfy.net" target="_blank">Volver a AsesorFy</a>
        </div>

    </div>
</div>

</body>
</html>
