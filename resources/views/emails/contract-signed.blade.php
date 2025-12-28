<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato Firmado - {{ config('app.name') }}</title>
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
        .label { color: #64748b; font-weight: 600; width: 120px; }
        .value { color: #1e293b; font-weight: bold; text-align: right; font-family: monospace; }

        .pay-box { background-color: #fff7ed; border: 1px solid #fed7aa; border-radius: 12px; padding: 25px; text-align: center; margin: 30px 0; }
        .pay-btn { display: inline-block; background-color: #635bff; color: #ffffff !important; text-decoration: none; padding: 14px 28px; border-radius: 8px; font-weight: bold; margin-top: 15px; }
        .amount-big { font-size: 26px; font-weight: 800; color: #0f172a; margin: 15px 0; }

        .footer { background-color: #f8fafc; padding: 24px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>

@php
    /**
     * IMPORTANTÍSIMO:
     * - Este email DEBE recibir $venta desde el Mailable.
     * - Solo si no llega, hacemos un fallback razonable (pero NO ideal).
     */
    $venta = $venta
        ?? $lead->ventas()
            ->whereNotNull('signed_at')
            ->with(['items.servicio', 'cliente'])
            ->latest('signed_at')
            ->first();

    $cliente = $venta?->cliente;

    // =========================================================
    // ✅ Método pago inicial (MISMA IDEA que en finished)
    // Fuente de verdad: Venta -> Link meta -> default
    // Normalizamos a: 'stripe' | 'transferencia'
    // =========================================================
    $metodo = $venta?->pago_inicial_metodo;

    // Fallback: leer el LeadConversionLink asociado a esta venta y sacar meta->pago_inicial_metodo
    if (! $metodo && $venta) {
        try {
            $link = \App\Models\LeadConversionLink::where('meta->existing_venta_id', $venta->id)
                ->latest()
                ->first();

            $metodo = data_get($link?->meta, 'pago_inicial_metodo');
        } catch (\Throwable $e) {
            // silencio
        }
    }

    // Default seguro
    $metodo = $metodo ?: 'stripe';

    // Normalización (por si en algún sitio guardas valores distintos)
    $metodo = match ($metodo) {
        'transferencia' => 'transferencia',
        'stripe', 'tarjeta', 'stripe_automatico', 'stripe_checkout' => 'stripe',
        default => 'stripe',
    };

    // =========================================================
    // IVA dinámico
    // =========================================================
    $porcentajeIva = 21;
    if ($cliente) {
        $porcentajeIva = \App\Models\Cliente::getPorcentajeImpuesto(
            $cliente->codigo_postal ?? '',
            $cliente->provincia ?? ''
        );
    }
    $factorIva = 1 + ($porcentajeIva / 100);

    // =========================================================
    // Total base (SOLO servicios únicos con importe > 0)
    // =========================================================
    $totalBaseUnico = 0.0;

    if ($venta) {
        $venta->loadMissing('items.servicio');

        foreach ($venta->items as $item) {
            if (! $item->servicio) continue;

            $tipo = $item->servicio->tipo instanceof \BackedEnum
                ? $item->servicio->tipo->value
                : $item->servicio->tipo;

            if ($tipo !== \App\Enums\ServicioTipoEnum::UNICO->value) {
                continue;
            }

            $importe = (float) ($item->subtotal_aplicado ?? $item->subtotal ?? 0);

            // ✅ solo cuenta si > 0
            if ($importe > 0) {
                $totalBaseUnico += $importe;
            }
        }
    }

    // Total a pagar (únicos + IVA)
    $totalPagar = ($totalBaseUnico > 0)
        ? round($totalBaseUnico * $factorIva, 2)
        : 0.0;

    // Estado del pago inicial (solo tiene sentido si hay algo que pagar)
    $pagoInicialPagado = ($venta && $totalPagar > 0)
        ? (bool) ($venta->tienePagoInicialCompletado() ?? false)
        : false;

    // ✅ Mostrar bloque de pago SOLO si:
    // - hay venta
    // - totalPagar > 0
    // - pago inicial NO está pagado
    $mostrarBloquePago = (bool) ($venta && $totalPagar > 0 && ! $pagoInicialPagado);

    // ✅ Si hay total > 0 pero ya pagado, mostramos un texto (sin botón)
    $mostrarInfoPagado = (bool) ($venta && $totalPagar > 0 && $pagoInicialPagado);

    // =========================================================
    // Datos transferencia (si aplica)
    // =========================================================
    $iban = null;
    $concepto = null;

    if ($metodo === 'transferencia') {
        // mismos posibles nombres que estás usando en otros sitios
        foreach (['iban_transferencias', 'empresa_iban_transferencias', 'empresa_iban', 'iban_empresa', 'empresa_cuenta_bancaria'] as $k) {
            $iban = \App\Models\VariableConfiguracion::where('nombre_variable', $k)->value('valor_variable');
            if ($iban) break;
        }

        $concepto = $venta
            ? ("VENTA {$venta->id}" . ($cliente?->razon_social ? " - {$cliente->razon_social}" : ''))
            : ("CONTRATO - {$lead->id}");
    }
@endphp


<div class="container">
    <div class="header">
        {{-- ✅ SOLO LOGO NORMAL (evita doble logo) --}}
        <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}">
    </div>

    <div class="content">
        <h1>¡Hola, {{ $lead->nombre }}! 👋</h1>

        <p>
            Te confirmamos que hemos recibido tu firma correctamente.
            El proceso de alta ha quedado registrado en nuestros sistemas.
        </p>

        <div class="highlight">
            <strong>📄 Contrato adjunto:</strong><br>
            Encontrarás una copia en PDF de tu contrato de servicios firmado adjunta a este correo.
        </div>

        {{-- ✅ Si el pago inicial existe pero ya está pagado --}}
        @if($mostrarInfoPagado)
            <p style="margin-top: 30px; border-top: 1px dashed #e5e7eb; padding-top: 20px;">
                El pago inicial ya está registrado como <strong>pagado</strong>. Si ves algún enlace de pago en otros correos, puedes ignorarlo.
            </p>
        @endif

        {{-- ✅ BLOQUE PAGO SOLO SI HAY IMPORTE REAL Y ESTÁ PENDIENTE --}}
        @if($mostrarBloquePago)

            {{-- ✅ CASO TRANSFERENCIA (NUNCA debe mostrar tarjeta) --}}
            @if($metodo === 'transferencia')
                <div class="transfer-box">
                    <h3 style="margin-top: 0; color: #1e40af; text-align: center;">Siguiente paso: Pago por transferencia</h3>

                    <p style="text-align: center; font-size: 14px; color: #3b82f6; margin-bottom: 16px;">
                        Has elegido pagar por transferencia. Para activar el servicio, realiza el ingreso con estos datos:
                    </p>

                    <div style="font-size: 28px; font-weight: 800; color: #1e3a8a; text-align: center; margin-bottom: 18px;">
                        {{ number_format($totalPagar, 2, ',', '.') }} €
                    </div>

                    <table class="data-table">
                        <tr>
                            <td class="label">Beneficiario:</td>
                            <td class="value">{{ config('app.name') }}</td>
                        </tr>
                        <tr>
                            <td class="label">IBAN:</td>
                            <td class="value">{{ $iban ?: 'Pendiente de configurar' }}</td>
                        </tr>
                        <tr>
                            <td class="label">Concepto:</td>
                            <td class="value">{{ $concepto }}</td>
                        </tr>
                    </table>

                    <p style="text-align: center; font-size: 12px; color: #64748b; margin-top: 14px; margin-bottom: 0;">
                        * Si ya has realizado la transferencia, responde a este email adjuntando el justificante para agilizar la confirmación.
                    </p>
                </div>

            {{-- ✅ CASO STRIPE (tarjeta) --}}
            @else
                <div class="pay-box">
                    <h3 style="margin-top: 0; color: #9a3412;">Finalizar contratación</h3>

                    <p style="color: #7c2d12; font-size: 14px; margin-bottom: 10px;">
                        Para comenzar es necesario abonar el pago inicial.
                    </p>

                    <div class="amount-big">
                        {{ number_format($totalPagar, 2, ',', '.') }} €
                    </div>

                    @if($venta)
                        <a href="{{ route('payment.pay', ['venta' => $venta->id]) }}" class="pay-btn">
                            💳 Pagar con tarjeta
                        </a>
                    @endif

                    <p style="font-size: 12px; color: #9ca3af; margin-top: 15px; margin-bottom: 0;">
                        Pago seguro procesado por Stripe. Si ya has abonado el pago inicial, puedes ignorar este paso.
                    </p>
                </div>
            @endif

        @else
            <p style="margin-top: 30px; border-top: 1px dashed #e5e7eb; padding-top: 20px;">
                Tu servicio ha quedado registrado correctamente. Tu asesor se pondrá en contacto contigo en breve para comenzar a trabajar.
            </p>
        @endif

        <p style="margin-top: 20px;">
            Guarda este email como justificante de tu contratación.
        </p>
    </div>

    <div class="footer">
        &copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
    </div>
</div>

</body>
</html>
