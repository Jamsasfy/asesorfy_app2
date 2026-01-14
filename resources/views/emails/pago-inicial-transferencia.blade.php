{{-- resources/views/emails/pago-inicial-transferencia.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datos de transferencia - {{ config('app.name') }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; background-color: #f3f4f6; margin: 0; padding: 0; color: #1f2937; line-height: 1.6; }
        .container { max-width: 600px; margin: 30px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb; }
        .header { background-color: #e0f7ff; padding: 24px; text-align: center; border-bottom: 1px solid #bae6fd; }
        .header img { height: 45px; width: auto; display: inline-block; }
        .content { padding: 40px 32px; }

        h1 { color: #0f172a; font-size: 24px; margin: 0 0 20px 0; font-weight: 700; }
        p { margin-bottom: 20px; color: #4b5563; font-size: 15px; }

        .highlight { background-color: #f0fdf4; border-left: 4px solid #22c55e; padding: 15px; margin: 25px 0; border-radius: 4px; font-size: 14px; color: #166534; }

        .transfer-box { background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 12px; padding: 25px; margin: 30px 0; }
        .data-table { width: 100%; margin-top: 15px; border-collapse: collapse; background: #fff; border-radius: 8px; border: 1px solid #dbeafe; }
        .data-table td { padding: 10px 15px; border-bottom: 1px solid #eff6ff; font-size: 14px; }
        .label { color: #64748b; font-weight: 600; width: 140px; }
        .value { color: #1e293b; font-weight: bold; text-align: right; font-family: monospace; }

        .amount-big { font-size: 28px; font-weight: 900; color: #1e3a8a; margin: 10px 0 0 0; text-align: center; }

        .cta-box { background-color: #ffffff; border: 1px dashed #bae6fd; border-radius: 12px; padding: 18px; margin: 18px 0 0; text-align: center; }
        .cta-btn { display: inline-block; background-color: #0ea5e9; color: #ffffff !important; text-decoration: none; padding: 12px 20px; border-radius: 8px; font-weight: 800; }
        .cta-sub { font-size: 12px; color: #64748b; margin-top: 10px; }

        .footer { background-color: #f8fafc; padding: 24px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>

@php
    $cliente = $venta?->cliente;

    // -------------------------
    // Datos para el CONCEPTO
    // -------------------------
    $dniCif = $cliente?->dni_cif
        ?? ($form['dni_cif'] ?? null)
        ?? ($form['cif'] ?? null)
        ?? ($form['dni'] ?? null)
        ?? null;

    if (is_string($dniCif)) $dniCif = trim($dniCif);
    if ($dniCif === '') $dniCif = null;

    $nombreCliente = trim((string)($form['razon_social']
        ?? ($form['nombre'] ?? '') . ' ' . ($form['apellidos'] ?? '')
    ));
    if ($nombreCliente === '') $nombreCliente = null;

    // IBAN fallback
    $iban = $iban ?: 'Pendiente de configurar';

    // ✅ CONCEPTO ÚNICO (sin "Referencia" ni fila DNI/CIF):
    // "VENTA {id} - {NOMBRE} - {DNI/CIF}"
    if (empty($concepto)) {
        $base = $venta?->id ? ('VENTA ' . $venta->id) : ('CONVERSION' . (!empty($token) ? (' ' . $token) : ''));
        $partes = array_values(array_filter([$base, $nombreCliente, $dniCif], fn ($v) => filled($v)));
        $concepto = implode(' - ', $partes);
    }

    $importeTotalFmt = number_format((float) $importeTotal, 2, ',', '.');
@endphp

<div class="container">
    <div class="header">
        <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}">
    </div>

    <div class="content">
        <h1>Datos para realizar la transferencia</h1>

        <p>
            Hola{{ $lead?->nombre ? ', ' . e($lead->nombre) : '' }} 👋<br>
            Para completar el <strong>pago inicial</strong>, realiza una transferencia con los siguientes datos.
        </p>

        <div class="transfer-box">
            <h3 style="margin-top: 0; color: #1e40af; text-align: center;">Pago por transferencia</h3>

            <p style="text-align: center; font-size: 14px; color: #3b82f6; margin-bottom: 12px;">
                Importante: usa el <strong>concepto exacto</strong> para que podamos identificar el pago.
            </p>

            <div class="amount-big">
                {{ $importeTotalFmt }} €
            </div>

            <p style="text-align:center; font-size: 12px; color: #64748b; margin: 10px 0 0;">
                (IVA incluido: {{ (int) $porcentajeIva }}%)
            </p>

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

            <div class="cta-box">
                <div style="font-weight: 800; color:#0f172a; margin-bottom: 10px;">
                    ¿Ya has hecho la transferencia?
                </div>

                @if(!empty($resumeUrl))
                    <a href="{{ $resumeUrl }}" class="cta-btn">Ver estado / continuar</a>
                @endif

                <div class="cta-sub">
                    Si ya la realizaste, puedes responder a este email adjuntando el justificante para agilizar la confirmación.
                </div>
            </div>
        </div>

        <div class="highlight">
            <strong>📌 Consejo:</strong><br>
            Guarda este correo para tener siempre a mano los datos de pago.
        </div>

        <p style="margin-top: 10px;">
            Si necesitas ayuda, responde a este email y te echamos una mano.
        </p>
    </div>

    <div class="footer">
        &copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
    </div>
</div>

</body>
</html>
