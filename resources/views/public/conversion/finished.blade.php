<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>¡Todo listo!</title>
  <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
  <link href="https://fonts.googleapis.com/css2?family=Varela+Round&display=swap" rel="stylesheet">
  <style>
    :root { --bg:#0f172a; --card:#1e293b; --text:#f1f5f9; --muted:#94a3b8; --ok:#22c55e; }
    body{ margin:0; font-family:"Varela Round", sans-serif; background:var(--bg); color:var(--text); }
    .wrap{ max-width:800px; margin:40px auto; padding:20px; }
    .card{ background:var(--card); border-radius:24px; padding:40px; box-shadow:0 25px 50px -12px rgba(0,0,0,0.5); border:1px solid #334155; }

    .logo img { height: 45px; display:block; margin: 0 auto 25px; }
    .icon-ok { width:70px; height:70px; background:rgba(34,197,94,0.1); border:2px solid var(--ok); color:var(--ok); border-radius:50%; display:grid; place-items:center; font-size:32px; margin:0 auto 20px; }
    .header { text-align:center; margin-bottom:40px; }
    h1 { margin:0 0 10px; font-size:30px; }

    .data-grid { display:grid; grid-template-columns: repeat(3, 1fr); gap:20px; background:#0f172a; padding:20px; border-radius:16px; margin-bottom:30px; border:1px solid #334155; }
    .lbl { font-size:12px; text-transform:uppercase; color:var(--muted); font-weight:700; margin-bottom:4px; }
    .val { font-size:15px; font-weight:600; color:#fff; word-break: break-word; }

    /* ESTADOS */
    .status-box { background:#0f172a; border-radius:16px; padding:24px; margin-bottom:20px; border-left:5px solid transparent; }
    .status-box.success { border-left-color: #22c55e; background: linear-gradient(90deg, rgba(34,197,94,0.05) 0%, rgba(15,23,42,1) 100%); }
    .status-box.active { border-left-color: #3b82f6; background: linear-gradient(90deg, rgba(59,130,246,0.05) 0%, rgba(15,23,42,1) 100%); }
    .status-box.deferred { border-left-color: #f59e0b; background: linear-gradient(90deg, rgba(245,158,11,0.05) 0%, rgba(15,23,42,1) 100%); }
    .status-box.warn { border-left-color: #ef4444; background: linear-gradient(90deg, rgba(239,68,68,0.05) 0%, rgba(15,23,42,1) 100%); }

    .box-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
    .box-title { font-size:18px; font-weight:800; display:flex; align-items:center; gap:10px; }
    .box-amount { font-size:24px; font-weight:900; color:#fff; }
    .box-body { font-size:14px; color:var(--muted); line-height:1.6; }

    .text-green { color:#4ade80; font-weight:700; }
    .text-orange { color:#fbbf24; font-weight:700; }
    .text-blue { color:#60a5fa; font-weight:700; }
    .text-white { color:#fff; font-weight:700; }

    /* TABLA SERVICIOS */
    .services-table { margin-top: 35px; border-top: 1px solid #334155; padding-top: 25px; }
    .st-head { font-size: 12px; text-transform: uppercase; color: var(--muted); font-weight: 800; margin-bottom: 15px; letter-spacing: 0.05em; }
    .st-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px dashed #334155; }
    .st-row:last-child { border-bottom: 0; }
    .st-name { font-weight: 600; font-size: 15px; color: #fff; }
    .st-meta { font-size: 12px; color: var(--muted); display: block; margin-top: 4px; }
    .st-price { font-weight: 700; color: #fff; text-align: right; }
    .tag { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 800; text-transform: uppercase; margin-left: 8px; }
    .tag.blue { background: rgba(59,130,246,0.2); color: #60a5fa; }

    .btn-download { display:block; width:100%; background:#6366f1; color:#fff; text-align:center; padding:16px; border-radius:12px; font-weight:700; text-decoration:none; margin-top:30px; transition:0.2s; }
    .btn-download:hover { background:#4f46e5; }
    .footer { text-align:center; margin-top:30px; font-size:13px; }
    .footer a { color:var(--muted); }
    .iva-inc{
      font-size:11px;
      font-weight:700;
      color:var(--muted);
      margin-left:6px;
      white-space:nowrap;
    }

    @media(max-width:600px){ .data-grid{ grid-template-columns:1fr; } }
  </style>
</head>
<body>

@php
  // -------------------------
  // Helpers (sin tocar diseño)
  // -------------------------
  $fmtMoney = fn ($n) => number_format((float) $n, 2, ',', '.');

  $promoRangoRelativo = function (int $inicioMes, int $meses): ?string {
      if ($meses <= 0) return null;
      $fin = $inicioMes + $meses - 1;
      return $inicioMes === $fin ? "Mes {$inicioMes}" : "Meses {$inicioMes}-{$fin}";
  };

  $getRecPrincipalFromResumen = function ($resumenServicios) {
      $col = $resumenServicios instanceof \Illuminate\Support\Collection ? $resumenServicios : collect($resumenServicios);
      return $col->first(fn ($s) => ($s['tipo'] ?? null) === 'recurrente' && !empty($s['es_tarifa_principal']))
          ?? $col->first(fn ($s) => ($s['tipo'] ?? null) === 'recurrente');
  };

  $recResumenPrincipal = $getRecPrincipalFromResumen($resumenServicios ?? []);
@endphp

<div class="wrap">
  <div class="card">

    <div class="header">
      <div class="logo">
         <img src="{{ asset('images/logo_dark.png') }}" alt="AsesorFy">
      </div>
      <div class="icon-ok">✓</div>
      <h1>¡Todo listo!</h1>
      <p style="color:#94a3b8;">Hemos procesado tu firma y la configuración de tu cuenta.</p>
    </div>

    {{-- DATOS CLIENTE --}}
    @php
       $form = $form ?? [];
       $titular = trim(($form['nombre'] ?? '') . ' ' . ($form['apellidos'] ?? ''));
       if (!$titular) $titular = trim(($cliente->nombre ?? '') . ' ' . ($cliente->apellidos ?? ''));
       if (!$titular) $titular = $cliente->razon_social ?? '—';
    @endphp

    <div class="data-grid">
      <div><div class="lbl">Titular</div><div class="val">{{ $titular }}</div></div>
      <div><div class="lbl">DNI / CIF</div><div class="val">{{ $form['cif'] ?? $form['dni'] ?? '—' }}</div></div>
      <div><div class="lbl">Email</div><div class="val">{{ $form['email'] ?? '—' }}</div></div>
    </div>

    {{-- 1. PAGO ÚNICO --}}
    @if(($pagoInicial['existe'] ?? false))
       @if(($pagoInicial['pagado'] ?? false))
          <div class="status-box success">
            <div class="box-header">
              <div class="box-title" style="color:#4ade80;">✅ Pago Inicial Recibido</div>
              <div class="box-amount" style="color:#4ade80;">{{ $pagoInicial['importe'] }} € <small class="iva-inc">IVA inc.</small></div>
            </div>
            <div class="box-body">
               Hemos recibido correctamente tu pago único mediante <strong>{{ $pagoInicial['metodo'] }}</strong>.
               <br>Tus servicios de inicio (Alta/Constitución) se ponen en marcha.
            </div>
          </div>
       @else
          <div class="status-box warn">
            <div class="box-header">
               <div class="box-title" style="color:#f87171;">⚠️ Pago Inicial Pendiente</div>
               <div class="box-amount" style="color:#f87171;">{{ $pagoInicial['importe'] }} € <small class="iva-inc">IVA inc.</small></div>
            </div>
           <div class="box-body">
                @if(($pagoInicial['es_transferencia'] ?? false) && !empty($transferencia) && !empty($transferencia['iban']))
                    El pago inicial está pendiente de recibir por <strong>transferencia</strong>.
                    <br>Realiza la transferencia con los siguientes datos:

                    <div style="margin-top:12px; padding-top:12px; border-top:1px dashed #334155;">
                        <div><span class="text-white">IBAN:</span> <strong>{{ $transferencia['iban'] }}</strong></div>

                        @php $benef = $transferencia['beneficiario'] ?? $transferencia['titular'] ?? null; @endphp
                        @if(!empty($benef))
                          <div style="margin-top:6px;"><span class="text-white">Beneficiario:</span> <strong>{{ $benef }}</strong></div>
                        @endif

                        @if(!empty($transferencia['banco']))
                          <div style="margin-top:6px;"><span class="text-white">Banco:</span> <strong>{{ $transferencia['banco'] }}</strong></div>
                        @endif

                        @if(!empty($transferencia['swift']))
                          <div style="margin-top:6px;"><span class="text-white">SWIFT/BIC:</span> <strong>{{ $transferencia['swift'] }}</strong></div>
                        @endif

                        @if(!empty($transferencia['concepto']))
                          <div style="margin-top:6px;"><span class="text-white">Concepto:</span> <strong>{{ $transferencia['concepto'] }}</strong></div>
                        @endif
                    </div>
                @else
                    El pago inicial no se ha completado. Contacta con nosotros para finalizarlo.
                @endif
              </div>

          </div>
       @endif
    @endif

    {{-- 2. RECURRENTE --}}
    @if(($recurrente['existe'] ?? false))
       @php
         $esDiferido = (bool) ($recurrente['es_diferido'] ?? false);
         $prorrateo = $recurrente['prorrateo'] ?? null;
         $promo = $recurrente['detalle_promo'] ?? null;

         // Para diferido, detalle_promo suele venir null.
         // En ese caso lo sacamos del resumen (que sí tiene promo_meses y precio_original).
         $promoMeses = (int) data_get($recResumenPrincipal, 'promo_meses', 0);
         $dtoTxt = data_get($recResumenPrincipal, 'texto_descuento');          // "-20%"
         $precioNormal = data_get($recResumenPrincipal, 'precio_original');    // IVA inc
         $cobroPrimerMes = data_get($recResumenPrincipal, 'cobro_primer_mes', 'prorrata');
         $noCobrar = (bool) data_get($recResumenPrincipal, 'no_cobrar_primer_periodo', false);

         $mesGratis = $noCobrar || $cobroPrimerMes === 'gratis';
         $inicioPromo = $mesGratis ? 2 : 1;
         $rangoPromo = $promoMeses > 0 ? $promoRangoRelativo($inicioPromo, $promoMeses) : null;
         $mesNormalDesde = $promoMeses > 0 ? ($inicioPromo + $promoMeses) : null;
       @endphp

       @if($esDiferido)
         <div class="status-box deferred">
            <div class="box-header">
               <div class="box-title" style="color:#fbbf24;">⏳ Suscripción Configurada (En Espera)</div>
               <div class="box-amount" style="color:#fbbf24;">{{ $recurrente['total_mes'] }} €/mes <small class="iva-inc">IVA inc.</small></div>
            </div>
            <div class="box-body">
               Tu método de pago está guardado. No se cobrará nada hoy.
               <br>La cuota se activará automáticamente cuando finalicemos tu servicio inicial.

               {{-- Promo en diferido (desde activación), si aplica --}}
               @if($promoMeses > 0 && $dtoTxt && $precioNormal && $rangoPromo && $mesNormalDesde)
                 <div style="margin-top:12px; padding-top:12px; border-top:1px dashed #334155;">
                   <span class="text-white">🏷️ Promoción {{ $dtoTxt }}</span>
                   <br>
                   <span style="color:#cbd5e1; font-size:13px;">
                     Desde activación: <strong>{{ $rangoPromo }}</strong>.
                   </span>
                   <br>
                   <span style="color:#94a3b8; font-size:13px;">
                     Desde el mes {{ $mesNormalDesde }} (desde activación) pagarás la cuota normal de
                     <strong>{{ $fmtMoney($precioNormal) }} €/mes</strong> (IVA inc.).
                   </span>
                 </div>
               @endif
            </div>
         </div>
       @else
         <div class="status-box active">
            <div class="box-header">
               <div class="box-title" style="color:#60a5fa;">🚀 Suscripción Activa</div>
               <div class="box-amount" style="color:#60a5fa;">{{ $recurrente['total_mes'] }} €/mes <small class="iva-inc">IVA inc.</small></div>
            </div>
            <div class="box-body">
               Tu cuota mensual de <strong>{{ $recurrente['nombre'] }}</strong> está activa.
               <br><br>

               {{-- 1. PRIMER COBRO --}}
               @php $tipo = is_array($prorrateo) ? ($prorrateo['tipo'] ?? null) : null; @endphp

               @if(is_array($prorrateo) && !empty($prorrateo['es_gratis']))
               <span class="text-green" style="font-size:16px;">🎁 PRIMER MES GRATIS ({{ $prorrateo['mes_actual'] ?? 'este mes' }}): 0,00 €</span>
               <br>Tu primer cargo llegará el 1 de {{ $prorrateo['siguiente'] ?? 'el próximo mes' }}.
               @elseif($tipo === 'completo')
               <span class="text-blue">🌕 Primer cobro (mes completo): {{ $prorrateo['importe'] ?? '—' }} €</span>
               <br>Se ha procesado el mes completo. El siguiente será el día 1.
               @elseif($tipo === 'mixto')
               <span class="text-orange">📅 Primer cobro (mixto): {{ $prorrateo['importe'] ?? '—' }} €</span>
               <br>Incluye líneas en prorrata y/o mes completo. El siguiente será el día 1.
               @else
               @if(is_array($prorrateo))
                 <span class="text-orange">📅 Primer cobro ({{ $prorrateo['mes_actual'] ?? 'este mes' }}): {{ $prorrateo['importe'] ?? '—' }} €</span>
                 <br>Se ha procesado el cobro correspondiente a este mes. El siguiente será el día 1.
               @endif
               @endif

               {{-- 2. DETALLE PROMOCIÓN (si viene de backend) --}}
               @if($promo)
                  <div style="margin-top:12px; padding-top:12px; border-top:1px dashed #334155;">
                      <span class="text-white">
                         🏷️ Descuento <strong>{{ $promo['texto'] }}</strong> aplicado
                      </span>
                      <br>
                      <span style="color:#cbd5e1; font-size:13px;">
                         Duración: <strong>{{ $promo['duracion_txt'] }}</strong> ({{ $promo['rango'] }}).
                         <br>Precio actual: <strong>{{ $recurrente['total_mes'] }} €/mes</strong> <span style="text-decoration:line-through; color:#64748b; font-size:12px;">({{ $promo['precio_normal'] }} €)</span>
                      </span>
                      <br>
                      <span style="color:#94a3b8; font-size:13px;">
                         A partir de {{ $promo['fecha_normal'] }} pagarás la cuota normal de <strong>{{ $promo['precio_normal'] }} €/mes</strong>.
                      </span>
                  </div>
               @endif

            </div>
         </div>
       @endif
    @endif

    {{-- 3. RESUMEN SERVICIOS CONTRATADOS --}}
    <div class="services-table">
        <div class="st-head">Resumen de servicios contratados</div>

        @foreach($resumenServicios as $s)
            @php
              $tipoServicio = $s['tipo'] ?? null;
              $cantidad = (int) ($s['cantidad'] ?? 1);
              $textoDescuento = $s['texto_descuento'] ?? null;

              $precioMensual = (float) ($s['precio_mensual'] ?? 0);
              $precioNormal = $s['precio_original'] ?? null; // IVA inc
              $promoMeses = (int) ($s['promo_meses'] ?? 0);
              $dtoTxt = $textoDescuento;

              // Cobro primer mes real (usa tus keys)
              $cobroPrimerMes = $s['cobro_primer_mes'] ?? null; // 'prorrata'|'completo'|'gratis'
              $noCobrar = (bool) ($s['no_cobrar_primer_periodo'] ?? false);
              $cobroTipo = $noCobrar ? 'gratis' : $cobroPrimerMes;

              // Si hay diferido global, en finished no mostramos "primer mes" (porque no hay cobro aún)
              $esDiferidoGlobal = (bool) data_get($recurrente ?? [], 'es_diferido', false);

              // Rango promo relativo (para no meter meses calendario)
              $mesGratis = $cobroTipo === 'gratis';
              $inicioPromo = $mesGratis ? 2 : 1;
              $rangoPromo = $promoMeses > 0 ? $promoRangoRelativo($inicioPromo, $promoMeses) : null;
              $mesNormalDesde = $promoMeses > 0 ? ($inicioPromo + $promoMeses) : null;
            @endphp

            <div class="st-row">
                <div>
                    <div class="st-name">
                        {{ $s['nombre'] ?? '—' }}
                        @if($cantidad > 1)
                          <span style="font-weight:400; color:#94a3b8;">(x{{ $cantidad }})</span>
                        @endif

                        @if($textoDescuento)
                             <span class="tag blue">{{ $textoDescuento }}</span>
                        @endif
                    </div>

                    {{-- Línea base --}}
                    <div class="st-meta">
                        @if($tipoServicio === 'recurrente')
                             Mensual
                             @if($esDiferidoGlobal)
                               <span style="color:#fbbf24;">• Inicio diferido</span>
                             @else
                               @if($cobroTipo === 'gratis') <span style="color:#4ade80;">• Primer mes gratis</span> @endif
                               @if($cobroTipo === 'prorrata') <span style="color:#fbbf24;">• Prorrata inicial</span> @endif
                               @if($cobroTipo === 'completo') <span style="color:#60a5fa;">• Primer mes completo</span> @endif
                             @endif
                        @else
                             Pago único
                        @endif
                    </div>

                    {{-- Línea extra: promo por meses + vuelta a normal --}}
                    @if($tipoServicio === 'recurrente' && $promoMeses > 0 && $dtoTxt && $precioNormal && $rangoPromo && $mesNormalDesde)
                      <div class="st-meta">
                        <span style="color:#cbd5e1; font-weight:700;">
                          Promoción {{ $dtoTxt }}:
                          @if($esDiferidoGlobal)
                            desde activación <strong>{{ $rangoPromo }}</strong>.
                          @else
                            <strong>{{ $rangoPromo }}</strong>.
                          @endif
                        </span>
                        <span style="color:#94a3b8; font-weight:700;">
                          · Desde mes {{ $mesNormalDesde }}{{ $esDiferidoGlobal ? ' (desde activación)' : '' }}:
                          {{ $fmtMoney($precioNormal) }} €/mes (IVA inc.)
                        </span>
                      </div>
                    @endif
                </div>

                <div class="st-price">
                    {{ $fmtMoney($precioMensual) }} € <small class="iva-inc">IVA inc.</small>
                    @if($tipoServicio === 'recurrente')
                      <span style="font-size:11px; font-weight:400; color:#94a3b8;">/mes</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if($pdfUrl)
      <a href="{{ $pdfUrl }}" target="_blank" class="btn-download">📄 Descargar Contrato Firmado</a>
    @endif

    <div class="footer">
       <a href="https://asesorfy.net">Volver a la web principal</a>
    </div>

  </div>
</div>

</body>
</html>
