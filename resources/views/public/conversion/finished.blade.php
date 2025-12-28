<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Firma completada</title>
  <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Varela+Round&display=swap" rel="stylesheet">

  <style>
    :root {
      --bg:#0b1220;
      --card:#0f172a;
      --muted:#94a3b8;
      --border:#1f2a44;
      --ok:#22c55e;
      --ok-strong:#16a34a;
      --btn:#16a34a;
      --btn-h:#15803d;
      --link:#93c5fd;
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      font-family: "Varela Round", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
      background: var(--bg);
      color:#e5e7eb;
    }

    .wrap{max-width:1080px;margin:32px auto;padding:16px}
    .card{
      background: var(--card);
      border:1px solid var(--border);
      border-radius:18px;
      padding:28px;
      box-shadow: 0 20px 40px rgba(0,0,0,.35);
    }

    .logo{ display:flex; align-items:center; gap:12px; margin-bottom:18px; }
    .logo img{ height:40px; width:auto; display:block; }

    .header{ display:flex; align-items:center; gap:14px; margin-bottom:14px; }
    .badge-ok{
      width:38px;height:38px;display:grid;place-items:center;
      background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.35);
      color:var(--ok); border-radius:999px; flex:0 0 auto; font-weight:800;
    }
    .title{font-size:26px;font-weight:800;letter-spacing:.2px;line-height:1.15}
    .muted{color:var(--muted)}

    .grid{ display:grid; grid-template-columns: 1fr 1fr; gap:10px 24px; margin-top:16px; }
    .label{font-size:13px;color:var(--muted);margin-bottom:3px}
    .value{font-size:16px;font-weight:600}

    .panel{
      margin-top:22px; padding:18px 20px; border-radius:14px;
      background:#111827; border:1px solid #1f2937;
    }
    .actions{margin-top:12px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;}
    .btn{
      appearance:none; border:0; cursor:pointer;
      background: var(--btn); color:#fff; font-weight:700;
      padding:10px 16px; border-radius:12px; font-size:15px;
      text-decoration:none; display:inline-flex; align-items:center; gap:10px;
      justify-content: center;
    }
    .btn:hover{background:var(--btn-h)}

    .btn-pay {
        background: #635bff !important;
        width: 100%;
        font-size: 1.1rem;
        padding: 14px;
        transition: transform 0.1s;
    }
    .btn-pay:hover {
        background: #5346e0 !important;
        transform: translateY(-2px);
    }
    .btn-secondary {
        background: transparent; border: 1px solid #475569; color: #cbd5e1; font-size: 0.9rem; padding: 8px 12px;
    }
    .btn-secondary:hover { background: #1e293b; border-color: #94a3b8; }

    .link{color:var(--link); text-decoration:underline}
    .legal{margin-top:10px; font-size:13px; color:var(--muted)}
    .footer-actions{margin-top:20px}

    @media (max-width: 720px){
      .wrap{padding:12px;margin:20px auto}
      .card{padding:20px;border-radius:16px}
      .logo{justify-content:center}
      .title{font-size:22px;text-align:center}
      .header{flex-direction:column;align-items:center;text-align:center;gap:10px}
      .grid{grid-template-columns:1fr; gap:10px}
      .footer-actions{display:flex; justify-content:center}
    }
  </style>
</head>
<body>
  @php
    $form = $form ?? [];
    $importePagoInicial = $importePagoInicial ?? 0;

    $tieneRecurrente = $tieneRecurrente ?? false;
    $pagoRecurrenteCompletado = $pagoRecurrenteCompletado ?? false;
    $tienePagoInicialPendiente = $tienePagoInicialPendiente ?? false;
    $metodoPagoInicial = $metodoPagoInicial ?? 'stripe';

    // Variables visuales aseguradas
    $cardInfo = $cardInfo ?? null;
    $totalRecurrenteMensual = $totalRecurrenteMensual ?? 0;
    $importeSinIvaRecurrente = $importeSinIvaRecurrente ?? 0;
    $nombreServicioRecurrente = $nombreServicioRecurrente ?? 'Suscripción';
    $esperaProyecto = $esperaProyecto ?? false;
    $prorrateo = $prorrateo ?? null;
    $infoDescuento = $infoDescuento ?? null;
    $porcentajeIva = $porcentajeIva ?? 21; // Default por seguridad

    // ✅ Pago inicial realizado (para mostrar bloque confirmación)
    $mostrarPagoInicialRealizado = ((float) $importePagoInicial > 0) && !$tienePagoInicialPendiente;

    $pagoInicialFecha = null;
    if (isset($venta) && $venta) {
        $pagoInicialFecha = $venta->pago_inicial_fecha ?? $venta->confirmada_at ?? null;
    }

    $pagoInicialRef = null;
    if (isset($venta) && $venta) {
        $pagoInicialRef = $venta->pago_inicial_referencia ?? null;
    }

    $metodoInicialLabel = 'Tarjeta (Stripe)';
    $metodoInicialIcon = '💳';
    if (($metodoPagoInicial ?? '') === 'transferencia') {
        $metodoInicialLabel = 'Transferencia bancaria';
        $metodoInicialIcon = '🏦';
    }
  @endphp


  <div class="wrap">
    <div class="card">

      <div class="logo">
        <img src="{{ asset('images/logo_dark.png') }}" alt="AsesorFy"
             onerror="this.replaceWith(document.createTextNode('AsesorFy')); ">
      </div>

      <div class="header">
        <div class="badge-ok">✔</div>
        <div>
          <div class="title">¡Firma completada!</div>
          <div class="muted">Hemos recibido tu aceptación y registro con sello de tiempo.</div>
        </div>
      </div>

      <div class="grid">
        <div>
          <div class="label">Nombre / Razón Social</div>
          <div class="value">
            {{ $form['razon_social'] ?? ($form['nombre'] ?? $lead->nombre ?? '—') }}
          </div>
        </div>
        <div>
          <div class="label">DNI / CIF</div>
          <div class="value">
            {{ $form['cif'] ?? $form['dni'] ?? $form['dni_nie'] ?? $lead->dni ?? $lead->cif ?? '—' }}
          </div>
        </div>
        <div>
          <div class="label">Email</div>
          <div class="value">
            {{ $form['email'] ?? $lead->email ?? '—' }}
          </div>
        </div>
        <div>
          <div class="label">Fecha de firma</div>
          <div class="value">
            {{ optional($lead->contract_signed_at)->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i') }}
          </div>
        </div>
      </div>

      {{-- BLOQUE 1: Descarga de Contrato --}}
      <div class="panel">
        <div class="label" style="margin-bottom:6px;">Tu contrato en PDF:</div>
        <div class="actions">
          @if(!empty($pdfUrl))
            <a class="btn" href="{{ $pdfUrl }}" target="_blank" rel="noopener noreferrer">
              Descargar contrato (PDF)
            </a>
          @else
            <span class="muted">El PDF aún no está disponible.</span>
          @endif
        </div>
      </div>

      {{-- MENSAJE DE ERROR GENÉRICO --}}
      @if (session('error'))
        <div style="margin-top: 16px; padding: 12px 16px; border-radius: 8px; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; font-size: 0.9rem;">
            {{ session('error') }}
        </div>
      @endif

      {{-- ========================================================
          BLOQUE 2: PAGO INICIAL (PRIORIDAD ABSOLUTA)
          ======================================================== --}}
      @if($tienePagoInicialPendiente)

        @if($metodoPagoInicial === 'transferencia')
          {{-- 🔸 MODO TRANSFERENCIA --}}
          <div class="panel" style="background:#0b1120; border-color:#38bdf8; margin-top:24px;">
            <div style="text-align:center;">
              <h2 style="color:#e0f2fe; margin-top:0; font-size:1.3rem;">💶 Realizar Transferencia</h2>
              <p style="color:#bae6fd; font-size:0.95rem; margin-bottom:12px;">
                Para activar el servicio, realiza una transferencia por el siguiente importe:
              </p>
              <div style="font-size:2rem; font-weight:800; color:#f9fafb; margin:15px 0;">
                {{ number_format($importePagoInicial, 2, ',', '.') }} €
                <span style="font-size: 0.5em; font-weight: normal; color: #94a3b8; vertical-align: middle;">
                    @if((float)$porcentajeIva === 0.0)
                        (Exento de IVA - Art. 69 LIVA)
                    @else
                        (IVA incluido)
                    @endif
                </span>
              </div>
              <div style="margin:0 auto; max-width:520px; padding:14px; border-radius:12px; background:#020617; border:1px solid #1e293b; font-size:0.9rem;">
                <p style="margin:0 0 6px;"><strong>Beneficiario:</strong> {{ config('app.name') }}</p>
                <p style="margin:0 0 6px;"><strong>IBAN:</strong> {{ $ibanEmpresa ?: 'Consultar' }}</p>
                <p style="margin:0 0 2px;"><strong>Concepto:</strong> {{ $conceptoTransferencia }}</p>
              </div>
            </div>
          </div>

        @else
          {{-- 🔹 MODO TARJETA (STRIPE) --}}
          <div class="panel" style="background: #fff7ed; border-color: #fdba74; margin-top: 24px;">
              <div style="text-align: center;">
                  <h2 style="color: #9a3412; margin-top: 0; font-size: 1.3rem;">⚠️ Pago Inicial Requerido</h2>
                  <p style="color: #7c2d12; font-size: 0.95rem; margin-bottom: 15px;">
                      Para activar el servicio es necesario abonar el importe inicial.
                  </p>

                  <div style="font-size: 2rem; font-weight: 800; color: #0f172a; margin: 15px 0;">
                      {{ number_format($importePagoInicial, 2, ',', '.') }} €
                      <span style="font-size: 0.5em; font-weight: normal; color: #7c2d12; vertical-align: middle;">
                          @if((float)$porcentajeIva === 0.0)
                              (Exento de IVA - Art. 69 LIVA)
                          @else
                              (IVA incluido)
                          @endif
                      </span>
                  </div>

                  <div class="actions" style="justify-content: center;">
                      <a href="{{ route('payment.pay', ['venta' => $venta->id]) }}" class="btn btn-pay">
                          💳 Pagar con Tarjeta (Seguro)
                      </a>
                  </div>

                  @if($tieneRecurrente)
                  <p style="margin-top: 15px; font-size: 0.85rem; color: #9a3412; max-width: 400px; margin-left: auto; margin-right: auto;">
                    ℹ️ La tarjeta que utilices para este pago quedará configurada de forma segura para tus futuras cuotas mensuales.
                  </p>
                  @endif
              </div>
          </div>
        @endif

      @endif

      {{-- ========================================================
          ✅ BLOQUE 2.5: PAGO INICIAL REALIZADO (CONFIRMACIÓN)
          ======================================================== --}}
      @if($mostrarPagoInicialRealizado)
        <div class="panel" style="margin-top:24px; background: rgba(34,197,94,.06); border: 1px solid rgba(34,197,94,.22);">
          <div style="display:flex; gap:12px; align-items:flex-start;">
            <div style="
              width:34px; height:34px; border-radius:999px; flex:0 0 auto;
              display:flex; align-items:center; justify-content:center;
              background: rgba(34,197,94,.14);
              border: 1px solid rgba(34,197,94,.28);
              color:#86efac; font-weight:900;
            ">✓</div>

            <div style="flex:1;">
              <div style="display:flex; align-items:baseline; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <div>
                  <div style="font-weight:800; color:#dcfce7; font-size:1rem;">Pago inicial recibido</div>
                  <div class="muted" style="font-size:.9rem; margin-top:2px;">
                    Tu pago inicial se ha procesado correctamente. Esto activa tus servicios de inicio.
                  </div>
                </div>

                <div style="text-align:right; min-width: 180px;">
                  <div style="font-size: 1.35rem; font-weight: 900; color:#4ade80; line-height:1;">
                    {{ number_format($importePagoInicial, 2, ',', '.') }} €
                  </div>
                  <div class="muted" style="font-size:.78rem; margin-top:3px;">
                    @if((float)$porcentajeIva === 0.0)
                      Exento de IVA (Art. 69 LIVA)
                    @else
                      IVA incluido
                    @endif
                  </div>
                </div>
              </div>

              <div style="margin-top:12px; display:flex; gap:10px; flex-wrap:wrap;">
                <div style="padding:8px 10px; border-radius:10px; background:#0b1120; border:1px solid #1e293b; font-size:.9rem; color:#cbd5e1;">
                  <span style="opacity:.85;">{{ $metodoInicialIcon }}</span>
                  <span style="margin-left:6px; font-weight:700;">{{ $metodoInicialLabel }}</span>
                </div>

                @if(!empty($pagoInicialFecha))
                  <div style="padding:8px 10px; border-radius:10px; background:#0b1120; border:1px solid #1e293b; font-size:.9rem; color:#cbd5e1;">
                    <span style="opacity:.85;">🗓️</span>
                    <span style="margin-left:6px;">
                      {{ \Illuminate\Support\Carbon::parse($pagoInicialFecha)->format('d/m/Y H:i') }}
                    </span>
                  </div>
                @endif

                @if(!empty($pagoInicialRef))
                  <div style="padding:8px 10px; border-radius:10px; background:#0b1120; border:1px solid #1e293b; font-size:.9rem; color:#cbd5e1;">
                    <span style="opacity:.85;">🔖</span>
                    <span style="margin-left:6px;">
                      Ref: <span style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">{{ $pagoInicialRef }}</span>
                    </span>
                  </div>
                @endif
              </div>

              @if($tieneRecurrente)
                <div class="muted" style="margin-top:10px; font-size:.82rem;">
                  La parte mensual se gestiona aparte y se cobrará según el estado de tu servicio (activación inmediata o diferida).
                </div>
              @endif
            </div>
          </div>
        </div>
      @endif

      {{-- ========================================================
          BLOQUE 3: CONFIGURACIÓN RECURRENTE
          ======================================================== --}}
      @if($tieneRecurrente)

          @if(!$pagoRecurrenteCompletado)

             {{-- CASO A: Faltan datos y NO hay un pago pendiente que lo solucione --}}
             {{-- NOTA: Si es transferencia, entra aquí para configurar el recurrente aparte --}}
             @if(!$tienePagoInicialPendiente || $metodoPagoInicial === 'transferencia')
                 <div style="margin-top: 24px;">
                    @include('public.conversion.recurrente-pendiente', [
                        'venta'   => $venta,
                        'cliente' => $venta->cliente,
                        'token'   => $link->token,
                    ])
                 </div>
             @endif

          @else
             {{-- CASO B: Stripe configurado (método de pago guardado) --}}
             @php
                $esDiferido = (bool) $esperaProyecto;

                // ✅ SEPA: texto real según día de cierre (16+ agrupa al día 1)
                $esSepa = isset($cardInfo) && (($cardInfo['type'] ?? null) === 'sepa');

                $fechaCierre = $venta?->signed_at
                    ?? $lead?->contract_signed_at
                    ?? now();

                $diaCierre = \Illuminate\Support\Carbon::parse($fechaCierre)->day;

                // Regla negocio: del 16 al fin de mes => se agrupa al día 1
                $sepaAgrupadoDia1 = $esSepa && $diaCierre >= 16;

                $statusColor = $esDiferido ? '#f59e0b' : '#22c55e'; // amber-500 / green-500
                $statusBg    = $esDiferido ? 'rgba(245, 158, 11, 0.12)' : 'rgba(34, 197, 94, 0.12)';
                $statusBd    = $esDiferido ? 'rgba(245, 158, 11, 0.28)' : 'rgba(34, 197, 94, 0.28)';
                $statusText  = $esDiferido ? 'Configurado (activación diferida)' : 'Activo';
                $statusTitle = $esDiferido ? 'Pago mensual configurado' : 'Método de pago recurrente activo';

                // ✅ Helper robusto para convertir importes "ES" (1.234,56) a float
                $toFloatEuro = function ($v) {
                  $s = (string) $v;

                  // Quita símbolos/espacios (€, etc.)
                  $s = preg_replace('/[^\d\.,\-]/', '', $s);

                  // Si hay coma, asumimos formato ES: 1.234,56
                  if (str_contains($s, ',')) {
                    $s = str_replace('.', '', $s);   // quita miles
                    $s = str_replace(',', '.', $s);  // coma -> punto decimal
                  }

                  return (float) $s;
                };
             @endphp

             <div style="
                margin-top: 24px;
                padding: 24px;
                border-radius: 16px;
                background: linear-gradient(145deg, #0f172a 0%, #1e293b 100%);
                border: 1px solid #334155;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
                position: relative;
                overflow: hidden;
             ">
                {{-- Decoración --}}
                <div style="position: absolute; top: 0; right: 0; width: 120px; height: 120px; background: radial-gradient(circle, rgba(14, 165, 233, 0.1) 0%, rgba(0,0,0,0) 70%); pointer-events: none;"></div>

                {{-- CABECERA --}}
                <div style="display:flex; align-items:flex-start; gap:16px; position:relative; z-index:2;">
                    <div style="
                      background: {{ $statusBg }};
                      border: 1px solid {{ $statusBd }};
                      color: {{ $statusColor }};
                      border-radius: 50%;
                      width: 34px;
                      height: 34px;
                      display: flex;
                      align-items: center;
                      justify-content: center;
                      flex-shrink: 0;
                    ">
                      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </div>

                    <div style="flex: 1;">
                        <div style="display:flex; align-items:baseline; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                          <div>
                            <h3 style="margin: 0 0 4px 0; font-size: 1.1rem; color: #f8fafc; font-weight: 800;">
                                {{ $statusTitle }}
                            </h3>

                            <div style="
                              display:inline-flex; align-items:center; gap:8px;
                              padding: 4px 10px;
                              border-radius: 999px;
                              background: {{ $statusBg }};
                              border: 1px solid {{ $statusBd }};
                              color: {{ $statusColor }};
                              font-size: 0.82rem;
                              font-weight: 800;
                            ">
                              {{ $statusText }}
                            </div>

                            @if($esDiferido)
                              <div style="margin-top:10px; font-size:0.9rem; color:#cbd5e1; line-height:1.5;">
                                <strong style="color:#fbbf24;">Hoy no se cobrará nada</strong> de la cuota mensual.
                                Empezará a cobrarse <strong>cuando finalice tu servicio inicial</strong>.
                              </div>
                            @else
                              <div style="margin-top:10px; font-size:0.9rem; color:#cbd5e1; line-height:1.5;">
                                Tu suscripción está activa. Se aplicará el cobro según el ciclo indicado.
                              </div>
                            @endif
                          </div>

                          {{-- Precio mensual --}}
                          @if(isset($totalRecurrenteMensual) && $totalRecurrenteMensual > 0)
                           <div style="text-align:right; min-width:220px; margin-left:auto; flex:0 0 auto;">
                              <div style="font-size: 1.45rem; font-weight: 900; color: #4ade80; line-height:1;">
                                {{ number_format($totalRecurrenteMensual, 2, ',', '.') }} € / mes
                              </div>
                              <div style="margin-top:4px; font-size: 0.85rem; color: #94a3b8; font-weight: 600;">
                                ({{ number_format($importeSinIvaRecurrente, 2, ',', '.') }} € + IVA)
                              </div>
                            </div>
                          @endif
                        </div>

                        {{-- Servicio --}}
                        <div style="margin-top: 10px; font-size: 0.95rem; color: #cbd5e1; font-weight: 700; text-align:right;">
                          {{ $nombreServicioRecurrente }}
                        </div>

                        {{-- Badge descuento si existe --}}
                        @if(!empty($infoDescuento))
                          <div style="margin-top:8px; display:inline-flex; align-items:center; gap:8px;
                            background: rgba(234, 179, 8, 0.18); color: #facc15; font-size: 0.78rem;
                            padding: 3px 10px; border-radius: 999px; border: 1px solid rgba(234, 179, 8, 0.35);
                            font-weight: 800;">
                            🏷️ {{ $infoDescuento }}
                          </div>
                        @endif
                    </div>
                </div>

                {{-- CÓMO SE COBRARÁ (tarjeta / sepa) --}}
                @if(isset($cardInfo) && $cardInfo['type'] === 'card')
                    <div style="margin-top: 18px; margin-left: 50px; background: #1e293b; border: 1px solid #475569; border-radius: 10px; padding: 14px 18px; display: flex; align-items: center; gap: 14px; max-width: 360px;">
                        <div style="background: #fff; width: 42px; height: 28px; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                            @if(isset($cardInfo['brand']) && strtolower($cardInfo['brand']) == 'visa')
                               <span style="color: #1a1f71; font-weight: 800; font-size: 14px; font-style: italic; font-family: sans-serif;">VISA</span>
                            @elseif(isset($cardInfo['brand']) && strtolower($cardInfo['brand']) == 'mastercard')
                               <span style="display:flex; gap:0;"><span style="width:14px; height:14px; background:#eb001b; border-radius:50%; opacity:0.9;"></span><span style="width:14px; height:14px; background:#f79e1b; border-radius:50%; margin-left:-6px; opacity:0.9;"></span></span>
                            @else
                               <span style="color: #334155; font-size: 18px;">💳</span>
                            @endif
                        </div>
                        <div>
                          <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px;">Se cobrará con tarjeta</div>
                          <div style="font-family: 'Courier New', monospace; font-size: 15px; letter-spacing: 2px; color: #e2e8f0; font-weight: 700;">
                            <span style="color: #64748b; font-size: 14px;">••••</span> {{ $cardInfo['last4'] ?? '0000' }}
                          </div>
                        </div>
                    </div>

                @elseif(isset($cardInfo) && $cardInfo['type'] === 'sepa')
                    <div style="margin-top: 18px; margin-left: 50px; background: #1e293b; border: 1px solid #475569; border-radius: 10px; padding: 14px 18px; display: flex; align-items: center; gap: 14px; max-width: 420px;">
                        <div style="background: #e2e8f0; width: 42px; height: 28px; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 16px;">🏦</div>
                        <div>
                            <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px;">Se cobrará por domiciliación (SEPA)</div>
                            <div style="font-family: 'Courier New', monospace; font-size: 15px; letter-spacing: 1px; color: #e2e8f0; font-weight: 700;">
                                <span style="color: #64748b;">IBAN ••••</span> {{ $cardInfo['last4'] ?? '0000' }}
                            </div>
                        </div>
                    </div>
                @endif

                {{-- CUÁNDO EMPIEZA / NOTAS --}}
                <div style="margin-top: 18px; margin-left: 50px; background: rgba(15, 23, 42, 0.4); border-radius: 10px; padding: 14px; border-left: 3px solid {{ $esDiferido ? '#f59e0b' : '#38bdf8' }};">
                    @if($esDiferido)
                      <p style="margin: 0; font-size: 0.9rem; color: #cbd5e1; line-height: 1.55;">
                        <strong style="color: #fbbf24;">📌 Cuándo empezará a cobrarse:</strong><br>
                        Cuando terminemos tu servicio inicial (ej: alta completada, sociedad constituida…), activaremos la cuota mensual automáticamente.
                        <br><span style="color: #94a3b8; font-size: 0.85rem;">Hasta ese momento, no habrá cargos mensuales.</span>
                      </p>
                    @else
                      <p style="margin: 0; font-size: 0.9rem; color: #cbd5e1; line-height: 1.55;">
                        <strong style="color: #4ade80;">📌 Inicio de la suscripción:</strong><br>

                        @if(isset($prorrateo))

                          @if(isset($cardInfo) && ($cardInfo['type'] ?? null) === 'sepa' && $sepaAgrupadoDia1)
                            Se cobrará <strong>todo junto el día 1 del próximo mes</strong>:
                            la parte proporcional de {{ ucfirst($prorrateo['mes_actual']) }} + la cuota mensual.
                            <br>

                            @if($prorrateo['es_gratis'])
                              <strong style="color:#facc15;">0,00 €</strong> por la promoción activa aplicable a los {{ $prorrateo['dias_restantes'] }} días restantes.
                              <br>
                            @else
                              <strong>{{ $prorrateo['importe'] }} €</strong>
                              <span style="font-size: 0.9em; color: #94a3b8; font-weight: 600;">({{ $prorrateo['importe_sin_iva'] }} € sin IVA)</span>
                              por los {{ $prorrateo['dias_restantes'] }} días restantes.
                              <br>
                            @endif

                            {{-- Y el mismo día 1 se aplicará la cuota mensual estándar. --}}

                            @php
                              $mensualConIva = (float) ($totalRecurrenteMensual ?? 0);
                              $mensualSinIva = (float) ($importeSinIvaRecurrente ?? 0);

                              $prorrataConIva = 0.0;
                              $prorrataSinIva = 0.0;

                              if (isset($prorrateo)) {
                                $prorrataConIva = $toFloatEuro($prorrateo['importe'] ?? 0);
                                $prorrataSinIva = $toFloatEuro($prorrateo['importe_sin_iva'] ?? 0);

                                if (!empty($prorrateo['es_gratis'])) {
                                  $prorrataConIva = 0.0;
                                  $prorrataSinIva = 0.0;
                                }
                              }

                              $totalPrimerCobroConIva = round($mensualConIva + $prorrataConIva, 2);
                              $totalPrimerCobroSinIva = round($mensualSinIva + $prorrataSinIva, 2);

                              // ✅ Mostrar resumen si hay mensual o prorrata (no redundante)
                              $mostrarResumenCobro = ($mensualConIva > 0) || ($prorrataConIva > 0);

                              // Fecha real de cobro del "día 1"
                              $fechaDia1Cobro = null;

                              // Opción 1 (ideal): viene de suscripción local (si la pasas al blade)
                              if (isset($suscripcion) && !empty($suscripcion->proxima_fecha_facturacion)) {
                                  $fechaDia1Cobro = \Illuminate\Support\Carbon::parse($suscripcion->proxima_fecha_facturacion);
                              }

                              // Opción 2: si la pasas ya calculada desde el controller
                              if (! $fechaDia1Cobro && isset($proximaFechaFacturacion) && $proximaFechaFacturacion) {
                                  $fechaDia1Cobro = \Illuminate\Support\Carbon::parse($proximaFechaFacturacion);
                              }

                              // Fallback: mes siguiente día 1
                              if (! $fechaDia1Cobro) {
                                  $fechaDia1Cobro = now()->addMonth()->startOfMonth();
                              }

                              $mesCuotaLabel = ucfirst($fechaDia1Cobro->locale('es')->translatedFormat('F Y')); // "Enero 2026"
                              $fechaDia1Label = $fechaDia1Cobro->format('d/m/Y'); // "01/01/2026"
                            @endphp

                            @if($mostrarResumenCobro)
                              <div style="margin-top:12px; padding:12px 14px; border-radius:12px; background:#0b1120; border:1px solid #1e293b;">
                                <div style="font-weight:800; color:#e2e8f0; margin-bottom:6px;">
                                  🧾 Resumen del primer cobro
                                  (todo junto el día 1 {{ $mesCuotaLabel }})
                                </div>

                                <div style="display:grid; grid-template-columns: 1fr auto; gap:6px 12px; font-size:.92rem; color:#cbd5e1;">
                                  @if(isset($prorrateo))
                                    <div>Prorrata {{ ucfirst($prorrateo['mes_actual'] ?? '') }}</div>
                                    <div style="font-weight:800;">
                                      {{ number_format($prorrataConIva, 2, ',', '.') }} €
                                      <span style="color:#94a3b8; font-weight:600; font-size:.88em;">
                                        ({{ number_format($prorrataSinIva, 2, ',', '.') }} € sin IVA)
                                      </span>
                                    </div>
                                  @endif

                                  <div>Cuota mensual ({{ $mesCuotaLabel }})</div>
                                  <div style="font-weight:800;">
                                    {{ number_format($mensualConIva, 2, ',', '.') }} €
                                    <span style="color:#94a3b8; font-weight:600; font-size:.88em;">
                                      ({{ number_format($mensualSinIva, 2, ',', '.') }} € sin IVA)
                                    </span>
                                  </div>

                                  <div style="border-top:1px dashed #334155; padding-top:8px; margin-top:4px; font-weight:900; color:#f8fafc;">
                                    Total a emitir el día 1 ({{ $fechaDia1Label }} — {{ $mesCuotaLabel }})
                                  </div>
                                  <div style="border-top:1px dashed #334155; padding-top:8px; margin-top:4px; font-weight:900; color:#f8fafc;">
                                    {{ number_format($totalPrimerCobroConIva, 2, ',', '.') }} €
                                    <span style="color:#94a3b8; font-weight:700; font-size:.88em;">
                                      ({{ number_format($totalPrimerCobroSinIva, 2, ',', '.') }} € sin IVA)
                                    </span>
                                  </div>
                                </div>

                                <div style="margin-top:8px; font-size:.85rem; color:#94a3b8;">
                                  * El adeudo SEPA puede reflejarse en cuenta entre el día 1 y el 15.
                                </div>
                              </div>
                            @endif

                          @else
                            {{-- Tarjeta o SEPA 1-15: iniciamos hoy (sin mentir con “instantáneo”) --}}
                            @if(isset($cardInfo) && ($cardInfo['type'] ?? null) === 'sepa')
                              Se iniciará <strong>hoy</strong> el cobro de la parte proporcional de {{ ucfirst($prorrateo['mes_actual']) }}.
                            @else
                              Se cobrará ahora la parte proporcional de {{ ucfirst($prorrateo['mes_actual']) }}.
                            @endif
                            <br>

                            @if($prorrateo['es_gratis'])
                              <strong style="color: #facc15;">0,00 €</strong> por la promoción activa aplicable a los {{ $prorrateo['dias_restantes'] }} días restantes.
                            @else
                              <strong>{{ $prorrateo['importe'] }} €</strong>
                              <span style="font-size: 0.9em; color: #94a3b8; font-weight: 600;">({{ $prorrateo['importe_sin_iva'] }} € sin IVA)</span>
                              por los {{ $prorrateo['dias_restantes'] }} días restantes.
                            @endif
                            <br>

                            A partir del día 1 del próximo mes se aplicarán las condiciones estándar.
                          @endif

                        @else
                          Tu plan ya está activo. El primer cobro se realizará en el próximo ciclo.
                        @endif
                      </p>
                    @endif

                    @if(isset($cardInfo) && $cardInfo['type'] === 'sepa')
                      <p style="margin: 12px 0 0 0; font-size: 0.88rem; color: #94a3b8; line-height: 1.5; border-top: 1px dashed #334155; padding-top: 10px;">
                        <strong style="color: #cbd5e1;">ℹ️ Nota sobre domiciliación:</strong><br>

                        @if($sepaAgrupadoDia1)
                          El primer cargo se emitire el <strong>día 1 del próximo mes a la activación del servicio</strong> (incluyendo prorrata + cuota mensual).
                          Al ser un adeudo SEPA, puede reflejarse en tu cuenta entre el día 1 y el 15.
                        @else
                          Al ser un adeudo SEPA, el cargo puede tardar unos días en reflejarse en tu cuenta.
                          Las cuotas mensuales suelen reflejarse entre el día 1 y el 15.
                        @endif
                      </p>
                    @endif
                </div>

                {{-- Cierre (coherente con diferido) --}}
                <div style="margin-top: 18px; margin-left: 50px;">
                  <div style="background: {{ $esDiferido ? 'rgba(245,158,11,0.10)' : 'rgba(34,197,94,0.10)' }};
                              border: 1px solid {{ $esDiferido ? 'rgba(245,158,11,0.22)' : 'rgba(34,197,94,0.20)' }};
                              border-radius: 10px; padding: 12px 16px;">
                    <p style="margin: 0; font-size: 0.92rem; color: {{ $esDiferido ? '#fcd34d' : '#86efac' }}; font-weight: 800;">
                      {{ $esDiferido ? '🟠 Método de pago guardado' : '✅ Método de pago confirmado' }}
                    </p>
                    <p style="margin: 4px 0 0 0; font-size: 0.82rem; color: #94a3b8;">
                      {{ $esDiferido
                          ? 'Lo usaremos automáticamente cuando tu servicio inicial finalice. Hasta entonces no se realizará ningún cobro mensual.'
                          : 'Tu suscripción ya está configurada. Si necesitas cambiar la forma de pago en el futuro, podrás hacerlo desde tu Área Privada.' }}
                    </p>
                  </div>
                </div>

             </div>

          @endif
      @endif

      <div class="footer-actions">
        <a class="link" href="https://asesorfy.net" target="_blank" rel="noopener noreferrer">Volver a AsesorFy</a>
      </div>

    </div>
  </div>
</body>
</html>
