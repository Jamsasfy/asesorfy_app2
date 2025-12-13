<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Revisa y firma el contrato</title>
  <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
  <link href="https://fonts.googleapis.com/css2?family=Varela+Round&display=swap" rel="stylesheet">
  <style>
    :root{ --muted:#6b7280; --border:#e5e7eb; --primary:#42c0e9; --primary-dark:#2aa8d3; --bg:#d7ecff; }
    *{box-sizing:border-box} html,body{height:100%}
    body{ margin:0; font-family:"Varela Round",ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto; background:var(--bg); color:#0f172a; }
    .wrap{ max-width:1120px; margin:18px auto 24px; padding:12px; }
    .hero{ display:flex; align-items:center; gap:14px; margin-bottom:14px; }
    .hero__logo{ display:block; width:140px; max-width:50%; height:auto; }
    .hero__text h1{ font-size:1.25rem; font-weight:700; margin:2px 0 4px; }
    .hero__text p{ margin:0; color:var(--muted); font-size:.95rem; }
    .card{ background:#fff; border:1px solid var(--border); border-radius:18px; padding:18px 20px; margin-bottom:12px; }
    .info-grid{ display:grid; grid-template-columns: 2fr 1.4fr; gap:10px 24px; align-items:flex-start; }
    .info-col{ display:flex; flex-direction:column; gap:6px; }
    .info-row{ margin-bottom:4px; }
    .info-label{ color:var(--muted); font-size:.8rem; text-transform:uppercase; letter-spacing:.03em; }
    .info-value{ font-weight:600; font-size:.95rem; }
    .pill{ padding:.25rem .7rem; border:1px solid #cfeef9; border-radius:999px; color:#055a70; background:#e7f9ff; font-weight:600; display:inline-flex; align-items:center; gap:.4rem; font-size:.8rem; }
    .doc{ height:55vh; overflow:auto; border:1px solid var(--border); border-radius:16px; padding:18px; background:#fff; margin-top:10px; }
    .hint{ position:sticky; bottom:8px; margin-top:18px; background:#fffbeb; border:1px solid #fcd34d; padding:.6rem .8rem; border-radius:10px; color:#92400e; font-size:.9rem; }
    .bar{ display:flex; gap:12px; align-items:center; justify-content:flex-end; margin-top:14px; }
    .hidden{display:none}
    .btn{ border:1px solid var(--border); background:#f8fafc; padding:.55rem .9rem; border-radius:12px; cursor:not-allowed; opacity:.6; font-weight:600; }
    .btn.primary{ background:var(--primary)!important; border-color:var(--primary-dark)!important; color:#fff!important; }
    .btn[aria-disabled="false"]{ cursor:pointer; opacity:1; }
    .btn-lg{ font-size:1.05rem; padding:.9rem 1.25rem; border-radius:12px; }
    .modal-backdrop{ position:fixed; inset:0; background:rgba(15,23,42,.45); display:flex; align-items:center; justify-content:center; padding:16px; z-index:50; }
    .modal{ background:#fff; border-radius:14px; padding:16px; max-width:560px; width:100%; border:1px solid var(--bg); box-shadow:0 10px 30px rgba(66,192,233,.15); }
    .canvas-wrap{ border:1px solid var(--border); border-radius:10px; overflow:hidden; background:#f8fafc; }
    .modal-backdrop.hidden{ display:none !important; }
    
    /* Servicios */
    .services-card{ margin-top:12px; background: linear-gradient(135deg,#f9fbff,#eef4ff); border-radius:20px; padding:16px 20px 14px; border:1px solid #dbeafe; box-shadow:0 14px 35px rgba(15,23,42,0.10); }
    .services-header{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:8px; }
    .services-header-main{ display:flex; flex-direction:column; gap:2px; }
    .services-eyebrow{ font-size:.78rem; text-transform:uppercase; letter-spacing:.08em; color:#64748b; }
    .services-title{ font-size:1.05rem; font-weight:800; color:#0f172a; }
    .services-sub{ font-size:.86rem; color:#6b7280; margin-top:2px; }
    .services-header-tag{ flex:0 0 auto; display:flex; align-items:flex-start; justify-content:flex-end; }
    .services-chip{ display:inline-flex; align-items:center; padding:4px 10px; border-radius:999px; background:#e0f2fe; color:#0369a1; font-size:.78rem; font-weight:700; border:1px solid #bfdbfe; }
    .services-list{ display:flex; flex-direction:column; gap:6px; margin-top:6px; }
    .service-row{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:9px 10px; border-radius:12px; background:rgba(255,255,255,0.8); border:1px solid rgba(226,232,240,0.9); font-size:.9rem; }
    .service-row + .service-row{ margin-top:6px; }
    .service-main{ flex:1 1 auto; }
    .service-name{ font-weight:700; margin-bottom:2px; letter-spacing:.01em; }
    .service-meta{ font-size:.8rem; color:#6b7280; }
    .service-badges{ display:inline-flex; gap:6px; margin-left:8px; font-size:.7rem; }
    .badge-pill{ border-radius:999px; border:1px solid #bfdbfe; background:#eff6ff; color:#1d4ed8; padding:1px 8px; font-weight:600; }
    .service-price{ flex:0 0 auto; text-align:right; font-weight:700; white-space:nowrap; font-size:.95rem; }

    /* Checkbox Aceptación */
    .agree-card { display: flex; align-items: center; gap: 1rem; padding: 14px 18px; background: #ffffff; border-radius: 16px; border: 2px solid #e5e7eb; cursor: pointer; transition: all 0.25s ease; width: 100%; margin-right: auto; box-shadow: 0 4px 10px rgba(15, 23, 42, 0.03); }
    .agree-card-left { flex: 0 0 auto; display: flex; align-items: center; justify-content: center; }
    .agree-card input[type="checkbox"] { width: 26px; height: 26px; cursor: pointer; accent-color: #0ea5e9; border-radius: 8px; }
    .agree-card-text { display: flex; flex-direction: column; gap: 2px; }
    .agree-main { font-size: 1.05rem; font-weight: 700; color: #0f172a; }
    .agree-sub { font-size: 0.93rem; color: #4b5563; }
    .agree-card:hover { border-color: #42c0e9; background: #f0f9ff; box-shadow: 0 8px 20px rgba(66, 192, 233, 0.18); }
    .agree-card:has(input[type="checkbox"]:checked) { border-color: #0ea5e9; background: #e0f7ff; box-shadow: 0 0 0 3px rgba(14,165,233,0.25); }
    .agree-card:has(input[type="checkbox"]:checked) .agree-main { color: #0c4a6e; }
    .agree-card:has(input[type="checkbox"]:checked) .agree-sub { color: #1e3a8a; }
    @media (max-width: 640px) {
      .info-grid{grid-template-columns:1fr}
      .bar{flex-wrap:wrap} #openSignModalBtn{width:100%} .hero{ flex-direction:column; align-items:flex-start; gap:8px; } .hero__logo{width:150px}
      .agree-card { padding: 12px 14px; gap: 0.8rem; } .agree-main { font-size: 1rem; } .agree-sub { font-size: 0.9rem; }
    }
  </style>
</head>
<body>
  @php
  $form = $form ?? []; $servicesSummary = $servicesSummary ?? [];
  $formNombre = $form['nombre'] ?? null; $formApellidos = $form['apellidos'] ?? data_get($link->meta, 'form_data.apellidos');
  $fullName = (!empty($formNombre)) ? trim($formNombre . ' ' . $formApellidos) : ($lead->nombre ?? '—');
  @endphp

  <div class="wrap">
    <div class="hero">
      <img src="{{ asset('images/logo.png') }}" alt="AsesorFy" class="hero__logo">
      <div class="hero__text"><h1>Revisa y firma el contrato</h1><p>Por favor, lee el contrato completo antes de firmar.</p></div>
    </div>
    <div class="card">
      <div class="info-grid">
        <div class="info-col">
          <div class="info-row"><div class="info-label">Nombre completo</div><div class="info-value">{{ $fullName !== '' ? $fullName : '—' }}</div></div>
          <div class="info-row"><div class="info-label">Email</div><div class="info-value">{{ $form['email'] ?? $lead->email ?? '—' }}</div></div>
          <div class="info-row"><div class="info-label">DNI / NIE / CIF</div><div class="info-value">{{ $form['dni'] ?? $form['dni_nie'] ?? data_get($link->meta,'form_data.dni') ?? data_get($link->meta,'form_data.cif') ?? $lead->dni ?? $lead->cif ?? '—' }}</div></div>
        </div>
        <div class="info-col">
          <div class="info-row"><div class="info-label">Teléfono</div><div class="info-value">{{ $form['telefono'] ?? $lead->tfn ?? '—' }}</div></div>
          <div class="info-row"><div class="info-label">Estado</div><div class="info-value"><span class="pill">Pendiente de firma</span></div></div>
          <div class="info-row"><div class="info-label">Estampa de tiempo</div><div class="info-value"><span id="liveClock">—</span></div></div>
        </div>
      </div>
    </div>

    @if(!empty($servicesSummary))
      @php
          $totalUnico = 0; $totalRecurrente = 0;
          foreach($servicesSummary as $s) {
              $sub = ($s['precio_base'] ?? 0) * ($s['unidades'] ?? 1);
              if (($s['tipo'] ?? '') === 'recurrente') $totalRecurrente += $sub; else $totalUnico += $sub;
          }
          $hayMix = ($totalUnico > 0 && $totalRecurrente > 0);
      @endphp
      <div class="services-card">
        <div class="services-header">
          <div class="services-header-main"><div class="services-eyebrow">Resumen de tu contratación</div><div class="services-title">Tus servicios con AsesorFy</div><div class="services-sub">Revisa que los datos coinciden con lo acordado.</div></div>
          <div class="services-header-tag"><span class="services-chip">Contrato AsesorFy</span></div>
        </div>
        <div class="services-list">
          @foreach($servicesSummary as $svc)
            @php $esRecurrente = ($svc['tipo'] ?? '') === 'recurrente'; $precioLinea = ($svc['precio_base'] ?? 0) * ($svc['unidades'] ?? 1); @endphp
            <div class="service-row">
              <div class="service-main">
                <div class="service-name">{{ $svc['nombre'] ?? 'Servicio' }} @if(($svc['unidades'] ?? 1) > 1) <span style="font-weight:normal; color:#666;">(x{{ $svc['unidades'] }})</span> @endif <span class="service-badges">@if(!empty($svc['es_tarifa_principal']))<span class="badge-pill">Base</span>@endif</span></div>
                <div class="service-meta">@if($esRecurrente) Servicio recurrente (Mensual) @if($hayMix)<div style="color:#d97706; font-size:0.8rem; margin-top:2px; font-weight:600;">⏳ Se activa tras finalizar trámites iniciales</div>@endif @else Servicio único (Pago puntual) @endif</div>
              </div>
              <div class="service-price">@if($precioLinea > 0) {{ number_format($precioLinea, 2, ',', '.') }} € @if($esRecurrente) <span style="font-size:0.8em; color:#6b7280;">/mes</span> @endif @else — @endif</div>
            </div>
          @endforeach
        </div>
        @if($totalUnico > 0 || $totalRecurrente > 0)
            <div style="margin-top: 16px; padding-top: 12px; border-top: 1px dashed #cbd5e1;">
                @if($totalUnico > 0) <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 4px;"> <div style="font-size:0.9rem; color:#166534; font-weight:600;">Total a pagar al inicio (Único):</div> <div style="font-size:1.1rem; font-weight:800; color:#166534;"> {{ number_format($totalUnico, 2, ',', '.') }} € </div> </div> @endif
                @if($totalRecurrente > 0) <div style="display:flex; justify-content:space-between; align-items:center;"> <div style="font-size:0.9rem; color:#1e40af; font-weight:600;"> {{ $hayMix ? 'Cuota mensual (Inicio diferido):' : 'Total Cuota Mensual:' }} </div> <div style="font-size:1.1rem; font-weight:800; color:#1e40af;"> {{ number_format($totalRecurrente, 2, ',', '.') }} €/mes </div> </div> @endif
                <div style="text-align:right; font-size:0.75rem; color:#94a3b8; margin-top:4px;">* Impuestos no incluidos</div>
            </div>
        @endif
      </div>
    @endif

    <div id="doc" class="doc" tabindex="0" aria-label="Contrato">
      <style>
        .contract-content h1 { font-size: 1.4rem; text-align: center; color: #0f172a; margin-bottom: 1rem; }
        .contract-content h2 { font-size: 1.2rem; text-align: center; color: #0ea5e9; margin-top: 0; }
        .contract-content h3 { font-size: 1rem; color: #334155; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin-top: 1.5rem; text-transform: uppercase; }
        .contract-content p, .contract-content li { font-size: 0.95rem; line-height: 1.6; color: #374151; text-align: justify; }
        .contract-content ul { padding-left: 1.2rem; }
        .contract-content table { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: 0.9rem; }
        .contract-content th { background: #f1f5f9; padding: 8px; text-align: left; }
        .contract-content td { border-bottom: 1px solid #e2e8f0; padding: 8px; }
      </style>
      <div class="contract-content">
          {!! $textos['contrato_cabecera'] ?? '' !!}
          {!! $textos['contrato_marco_legal'] ?? '' !!}
          <hr style="margin: 2rem 0; border: 0; border-top: 1px dashed #cbd5e1;">
          @if(!empty($textos['servicio_recurrentes'])) {!! $textos['servicio_recurrentes'] !!} <br> @endif
          @if(!empty($textos['servicio_unicos'])) {!! $textos['servicio_unicos'] !!} <br> @endif
          <hr style="margin: 2rem 0; border: 0; border-top: 1px dashed #cbd5e1;">
          {!! $textos['contrato_condiciones_grales'] ?? '' !!}
          <hr style="margin: 2rem 0; border: 0; border-top: 1px dashed #cbd5e1;">
          {!! $textos['anexo_economico'] ?? '' !!} <br> {!! $textos['anexo_rgpd_ia'] ?? '' !!}
      </div>
      <div style="height:40px"></div>
      <div id="docEnd" style="height:2px;width:100%"></div>
      <div id="bottomHint" class="hint">Desplázate hasta el final del documento para habilitar la aceptación y la firma.</div>
      <div style="height:20px"></div> 
    </div>

    <form id="agreeRow" class="bar hidden" method="POST" action="{{ route('conversion.sign', ['token'=>$link->token]) }}">
      @csrf <input type="hidden" name="signature" id="signatureInput">
      <label class="agree-card agree-card--confirm">
        <div class="agree-card-left"><input id="agree" name="acepto" type="checkbox" value="1" /></div>
        <div class="agree-card-text"><div class="agree-main">Confirmar aceptación del contrato</div><div class="agree-sub">He leído el contrato y acepto los términos.</div></div>
      </label>
      <button id="openSignModalBtn" type="button" class="btn primary btn-lg" aria-disabled="true" disabled style="display:none;">Firmar y aceptar</button>
    </form>
  </div>

  <div id="signModal" class="modal-backdrop hidden" aria-hidden="true">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="signTitle">
      <div id="signTitle" style="font-weight:700;margin-bottom:8px">Firma el contrato</div>
      <div class="canvas-wrap"><canvas id="signatureCanvas" style="width:100%;height:220px;display:block;"></canvas></div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px">
        <button type="button" id="clearSig" class="btn">Borrar</button><button type="button" id="cancelSig" class="btn">Cancelar</button><button type="button" id="confirmSig" class="btn primary">Confirmar firma</button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
  <script>
    (function () {
      const liveClock=document.getElementById('liveClock'), pad2=n=>String(n).padStart(2,'0');
      setInterval(()=>{const d=new Date();liveClock.textContent=`${pad2(d.getDate())}/${pad2(d.getMonth()+1)}/${d.getFullYear()} ${pad2(d.getHours())}:${pad2(d.getMinutes())}:${pad2(d.getSeconds())}`},1000);

      const docEl=document.getElementById('doc'), endEl=document.getElementById('docEnd'), hintEl=document.getElementById('bottomHint'), barForm=document.getElementById('agreeRow'), agree=document.getElementById('agree'), openBtn=document.getElementById('openSignModalBtn'), modal=document.getElementById('signModal'), canvas=document.getElementById('signatureCanvas'), clearBtn=document.getElementById('clearSig'), cancelBtn=document.getElementById('cancelSig'), confirmBtn=document.getElementById('confirmSig'), sigInput=document.getElementById('signatureInput');
      let reachedBottom=false, sigPad=null;

      modal.classList.add('hidden'); modal.setAttribute('aria-hidden','true');

      // ⚠️ LÓGICA MEJORADA: Comprobamos si el documento es pequeño al inicio
      function checkContentSize() {
          if (docEl.scrollHeight <= docEl.clientHeight + 50) {
              reachedBottom = true;
              barForm.classList.remove('hidden');
              hintEl.classList.add('hidden');
              syncBtn();
          }
      }
      setTimeout(checkContentSize, 500); 

      const io = new IntersectionObserver((entries) => {
        entries.forEach(e => {
          if (e.isIntersecting && e.target === endEl) {
            reachedBottom=true; barForm.classList.remove('hidden'); hintEl.classList.add('hidden'); io.unobserve(endEl); syncBtn();
            setTimeout(()=>barForm.scrollIntoView({behavior:'smooth',block:'start'}),50);
          }
        });
      }, { root: docEl, threshold: 0.5 });
      io.observe(endEl);

      function syncBtn(){ const ok=agree.checked && reachedBottom; openBtn.disabled=!ok; openBtn.setAttribute('aria-disabled',String(!ok)); openBtn.style.display=ok?'inline-block':'none'; }
      agree.addEventListener('change',syncBtn);

      function resizeCanvas(){ const ratio=Math.max(window.devicePixelRatio||1,1), rect=canvas.getBoundingClientRect(); canvas.width=rect.width*ratio; canvas.height=rect.height*ratio; const ctx=canvas.getContext('2d'); ctx.scale(ratio,ratio); if(sigPad) sigPad.clear(); updateConfirmState(); }
      function openModal(){ if(openBtn.disabled)return; modal.classList.remove('hidden'); modal.setAttribute('aria-hidden','false'); setTimeout(()=>{ resizeCanvas(); if(!sigPad){ sigPad=new SignaturePad(canvas,{backgroundColor:'rgba(255,255,255,1)'}); sigPad.addEventListener('endStroke',updateConfirmState); sigPad.addEventListener('clear',updateConfirmState); } updateConfirmState(); },30); }
      function closeModal(){ modal.classList.add('hidden'); modal.setAttribute('aria-hidden','true'); }
      function updateConfirmState(){ const e=sigPad?sigPad.isEmpty():true; confirmBtn.disabled=e; confirmBtn.setAttribute('aria-disabled',String(e)); confirmBtn.style.opacity=e?'0.6':'1'; confirmBtn.style.cursor=e?'not-allowed':'pointer'; }

      window.addEventListener('resize',resizeCanvas); openBtn.addEventListener('click',openModal); clearBtn.addEventListener('click',()=>sigPad&&sigPad.clear()); cancelBtn.addEventListener('click',closeModal);
      confirmBtn.addEventListener('click',()=>{ if(confirmBtn.disabled||!sigPad||sigPad.isEmpty()){alert('Firma requerida');return;} if(!agree.checked){alert('Acepta términos');return;} sigInput.value=sigPad.toDataURL('image/png'); confirmBtn.disabled=true; barForm.submit(); });
    })();
  </script>
</body>
</html>