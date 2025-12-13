<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Firma completada</title>
  <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
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
      font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
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
  @endphp

  <div class="wrap">
    <div class="card">

      <div class="logo">
        <img src="{{ asset('images/logo.png') }}" alt="AsesorFy"
             onerror="this.replaceWith(document.createTextNode('AsesorFy'));">
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

      {{-- 
          ======================================================== 
          BLOQUE 2: PAGO INICIAL (PRIORIDAD ABSOLUTA)
          ======================================================== 
      --}}
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
                {{-- Texto de Impuestos Dinámico --}}
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
                      {{-- Texto de Impuestos Dinámico --}}
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


      {{-- 
          ======================================================== 
          BLOQUE 3: CONFIGURACIÓN RECURRENTE
          ======================================================== 
      --}}
      
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
             {{-- 
                CASO B: Todo correcto (Stripe configurado) - DISEÑO PREMIUM
             --}}
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

                <div style="display:flex; align-items:flex-start; gap:16px; position:relative; z-index:2;">
                    <div style="background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: #fff; border-radius: 50%; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 2px 4px rgba(34,197,94,0.3);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    </div>

                    <div style="flex: 1;">
                        <h3 style="margin: 0 0 4px 0; font-size: 1.1rem; color: #f8fafc; font-weight: 700;">
                            Método de pago recurrente activo
                        </h3>
                        
                        {{-- PRECIO MENSUAL DETALLADO (PRECIO FUTURO/ESTANDAR) --}}
                       @if(isset($totalRecurrenteMensual) && $totalRecurrenteMensual > 0)
                        <div style="margin-top: 6px;">
                            <div style="font-size: 1.4rem; font-weight: 800; color: #4ade80; display: flex; align-items: baseline; flex-wrap: wrap; gap: 8px;">
                                {{ number_format($totalRecurrenteMensual, 2, ',', '.') }} € / mes
                                
                                {{-- Precio sin IVA --}}
                                <span style="font-size: 0.9rem; font-weight: 500; color: #94a3b8;">
                                    ({{ number_format($importeSinIvaRecurrente, 2, ',', '.') }} € + {{ $porcentajeIva }}% IVA)
                                </span>
                            </div>
                            
                            <div style="display:flex; flex-direction: column; gap: 4px; margin-top: 8px;">
                                {{-- Nombre del Servicio (Concatenado) --}}
                                <div style="font-size: 0.95rem; color: #cbd5e1; font-weight: 600; line-height: 1.4;">
                                    {{ $nombreServicioRecurrente }}
                                </div>

                                {{-- 🏷️ BADGE DE DESCUENTO (Si existe) --}}
                                @if(!empty($infoDescuento))
                                <div style="align-self: flex-start; background: rgba(234, 179, 8, 0.2); color: #facc15; font-size: 0.75rem; padding: 2px 8px; border-radius: 99px; border: 1px solid rgba(234, 179, 8, 0.4); font-weight: 600;">
                                    🏷️ {{ $infoDescuento }}
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- VISUALIZACIÓN TARJETA / SEPA --}}
                @if(isset($cardInfo) && $cardInfo['type'] === 'card')
                    <div style="margin-top: 20px; margin-left: 48px; background: #1e293b; border: 1px solid #475569; border-radius: 10px; padding: 14px 18px; display: flex; align-items: center; gap: 14px; max-width: 320px;">
                        <div style="background: #fff; width: 42px; height: 28px; border-radius: 4px; display: flex; align-items: center; justify-content: center;">
                            @if(isset($cardInfo['brand']) && strtolower($cardInfo['brand']) == 'visa')
                               <span style="color: #1a1f71; font-weight: 800; font-size: 14px; font-style: italic; font-family: sans-serif;">VISA</span>
                            @elseif(isset($cardInfo['brand']) && strtolower($cardInfo['brand']) == 'mastercard')
                               <span style="display:flex; gap:0;"><span style="width:14px; height:14px; background:#eb001b; border-radius:50%; opacity:0.9;"></span><span style="width:14px; height:14px; background:#f79e1b; border-radius:50%; margin-left:-6px; opacity:0.9;"></span></span>
                            @else
                               <span style="color: #334155; font-size: 18px;">💳</span>
                            @endif
                        </div>
                        <div style="font-family: 'Courier New', monospace; font-size: 16px; letter-spacing: 2px; color: #e2e8f0; font-weight: 600;">
                            <span style="color: #64748b; font-size: 14px;">••••</span> {{ $cardInfo['last4'] ?? '0000' }}
                        </div>
                    </div>

                @elseif(isset($cardInfo) && $cardInfo['type'] === 'sepa')
                    <div style="margin-top: 20px; margin-left: 48px; background: #1e293b; border: 1px solid #475569; border-radius: 10px; padding: 14px 18px; display: flex; align-items: center; gap: 14px; max-width: 340px;">
                        <div style="background: #e2e8f0; width: 42px; height: 28px; border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 16px;">🏦</div>
                        <div>
                            <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px;">Cuenta Bancaria</div>
                            <div style="font-family: 'Courier New', monospace; font-size: 15px; letter-spacing: 1px; color: #e2e8f0; font-weight: 600;">
                                <span style="color: #64748b;">IBAN ••••</span> {{ $cardInfo['last4'] ?? '0000' }}
                            </div>
                        </div>
                    </div>
                @endif

                {{-- INFO DINÁMICA: ACTIVACIÓN Y PRORRATEO --}}
                <div style="margin-top: 20px; margin-left: 48px; background: rgba(15, 23, 42, 0.4); border-radius: 8px; padding: 14px; border-left: 3px solid #38bdf8;">
                    
                    @if($esperaProyecto)
                        {{-- CASO 1: HAY PROYECTO PENDIENTE (Diferido) --}}
                        <p style="margin: 0 0 10px 0; font-size: 0.85rem; color: #cbd5e1; line-height: 1.5;">
                            <strong style="color: #38bdf8;">📅 Activación diferida:</strong><br>
                            La cuota mensual solo se activará <strong>cuando tu servicio inicial esté finalizado</strong> (ej: alta completada, sociedad constituida...).
                            <br><span style="color: #94a3b8; font-size: 0.8rem;">(No se cobrará nada por la parte recurrente hasta entonces).</span>
                        </p>
                    @else
                        {{-- CASO 2: ACTIVACIÓN INMEDIATA (Prorrateo) --}}
                        <p style="margin: 0 0 10px 0; font-size: 0.85rem; color: #cbd5e1; line-height: 1.5;">
                            <strong style="color: #4ade80;">📅 Suscripción activa desde hoy:</strong><br>
                            
                            @if(isset($prorrateo))
                                Se cobrará ahora la parte proporcional de {{ ucfirst($prorrateo['mes_actual']) }}:
                                <br>
                                
                                @if($prorrateo['es_gratis'])
                                    {{-- CASO GRATIS / 100% DTO --}}
                                    <strong style="color: #facc15;">0,00 €</strong> 
                                    (por la promoción activa aplicable a los {{ $prorrateo['dias_restantes'] }} días restantes).
                                @else
                                    {{-- CASO PAGO NORMAL --}}
                                    <strong>{{ $prorrateo['importe'] }} €</strong> 
                                    <span style="font-size: 0.85em; color: #94a3b8; font-weight: normal;">({{ $prorrateo['importe_sin_iva'] }} € sin IVA)</span>
                                    por los {{ $prorrateo['dias_restantes'] }} días restantes.
                                @endif

                                <br>
                                A partir del día 1 del próximo mes, se aplicarán las condiciones estándar de tu tarifa.
                            @else
                                Tu plan ya está activo. El primer cobro se realizará en el próximo ciclo.
                            @endif
                        </p>
                    @endif

                    @if(isset($cardInfo) && $cardInfo['type'] === 'sepa')
                        <p style="margin: 0; font-size: 0.85rem; color: #94a3b8; line-height: 1.5; border-top: 1px dashed #334155; padding-top: 8px;">
                            <strong style="color: #cbd5e1;">ℹ️ Nota sobre Domiciliación:</strong><br>
                            Al ser un adeudo bancario SEPA, el cargo podría reflejarse en tu cuenta entre el día 1 y el 15.
                        </p>
                    @endif
                </div>
                
                {{-- 
                    ✅ MENSAJE DE ÉXITO (Sustituye a los botones de cambio) 
                    Evitamos ofrecer cambios inmediatos para no romper la consistencia con Stripe.
                --}}
                <div style="margin-top: 20px; margin-left: 48px;">
                     <div style="background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.2); border-radius: 8px; padding: 12px 16px;">
                        <p style="margin: 0; font-size: 0.9rem; color: #86efac; font-weight: 600;">
                            ✅ Método de pago confirmado
                        </p>
                        <p style="margin: 4px 0 0 0; font-size: 0.8rem; color: #94a3b8;">
                            Tu suscripción ya está configurada. Si necesitas cambiar la forma de pago en el futuro, podrás hacerlo desde tu Área Privada.
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