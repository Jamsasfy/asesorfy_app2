<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pago inicial | AsesorFy</title>
  <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
  <link href="https://fonts.googleapis.com/css2?family=Varela+Round&display=swap" rel="stylesheet">

  <style>
    :root { 
      --bg:#0f172a; 
      --card:#1e293b; 
      --text:#f1f5f9; 
      --muted:#94a3b8; 
      --ok:#22c55e; 
      --warn:#f59e0b;
      --danger:#ef4444;
      --blue:#60a5fa;
      --border:#334155;
      --btn:#16a34a;
      --btn-h:#15803d;
    }

    *{box-sizing:border-box}
    body{ margin:0; font-family:"Varela Round", sans-serif; background:var(--bg); color:var(--text); }
    a{ color:inherit; }

    .wrap{ max-width:900px; margin:40px auto; padding:20px; }
    .card{ 
      background:var(--card); 
      border-radius:24px; 
      padding:40px; 
      box-shadow:0 25px 50px -12px rgba(0,0,0,0.5); 
      border:1px solid var(--border); 
    }

    .logo img { height: 45px; display:block; margin: 0 auto 18px; }
    .icon-ok { 
      width:70px; height:70px; 
      background:rgba(59,130,246,0.10); 
      border:2px solid rgba(96,165,250,.9);
      color:var(--blue); 
      border-radius:50%; 
      display:grid; place-items:center; 
      font-size:30px; 
      margin:0 auto 16px; 
    }

    .header { text-align:center; margin-bottom:30px; }
    h1 { margin:0 0 10px; font-size:30px; }
    .subhead{ color:var(--muted); margin:0; line-height:1.6; }

    .data-grid { 
      display:grid; grid-template-columns: repeat(3, 1fr); gap:20px; 
      background:#0f172a; padding:20px; border-radius:16px; 
      margin: 18px 0 26px;
      border:1px solid var(--border); 
    }
    .lbl { font-size:12px; text-transform:uppercase; color:var(--muted); font-weight:700; margin-bottom:4px; }
    .val { font-size:15px; font-weight:600; color:#fff; word-break: break-word; }

    /* ESTADOS */
    .status-box { background:#0f172a; border-radius:16px; padding:24px; margin-bottom:16px; border-left:5px solid transparent; border:1px solid var(--border); }
    .status-box.info { border-left-color: #3b82f6; background: linear-gradient(90deg, rgba(59,130,246,0.06) 0%, rgba(15,23,42,1) 100%); }
    .status-box.success { border-left-color: #22c55e; background: linear-gradient(90deg, rgba(34,197,94,0.06) 0%, rgba(15,23,42,1) 100%); }
    .status-box.warn { border-left-color: #f59e0b; background: linear-gradient(90deg, rgba(245,158,11,0.06) 0%, rgba(15,23,42,1) 100%); }
    .status-box.danger { border-left-color: #ef4444; background: linear-gradient(90deg, rgba(239,68,68,0.06) 0%, rgba(15,23,42,1) 100%); }

    .box-header { display:flex; justify-content:space-between; align-items:flex-start; gap:14px; margin-bottom:10px; }
    .box-title { font-size:18px; font-weight:800; display:flex; align-items:center; gap:10px; }
    .box-amount { font-size:24px; font-weight:900; color:#fff; text-align:right; white-space:nowrap; }
    .box-body { font-size:14px; color:var(--muted); line-height:1.7; }

    .text-green { color:#4ade80; font-weight:800; }
    .text-orange { color:#fbbf24; font-weight:800; }
    .text-blue { color:#60a5fa; font-weight:800; }
    .text-red { color:#f87171; font-weight:800; }
    .text-white { color:#fff; font-weight:800; }

    /* TABLA SERVICIOS */
    .services-table { margin-top: 10px; border-top: 1px solid var(--border); padding-top: 18px; }
    .st-head { font-size: 12px; text-transform: uppercase; color: var(--muted); font-weight: 800; margin-bottom: 12px; letter-spacing: 0.05em; }
    .st-row { display: flex; justify-content: space-between; align-items: flex-start; gap:16px; padding: 12px 0; border-bottom: 1px dashed var(--border); }
    .st-row:last-child { border-bottom: 0; }
    .st-name { font-weight: 700; font-size: 15px; color: #fff; }
    .st-meta { font-size: 12px; color: var(--muted); display: block; margin-top: 4px; line-height:1.45; }
    .st-price { font-weight: 900; color: #fff; text-align: right; white-space:nowrap; }
    .st-price small{ font-size:11px; font-weight:700; color:var(--muted); margin-left:6px; }

    .old{ text-decoration: line-through; color: #94a3b8; font-weight:700; font-size:12px; margin-top:4px; }
    .disc{ color:#fbbf24; font-weight:900; font-size:12px; margin-top:4px; }

    /* MÉTODOS PAGO */
    .pay-methods { margin-top: 18px; }
    .pm-head { font-size: 12px; text-transform: uppercase; color: var(--muted); font-weight: 800; margin: 18px 0 12px; letter-spacing: 0.05em; }
    .opt{ display:block; cursor:pointer; user-select:none; }
    .optbox{
      border:1px solid #334155; border-radius:16px; padding:16px 16px;
      background:rgba(2,6,23,.35); transition:.15s;
      display:flex; gap:12px; align-items:flex-start
    }
    .optbox:hover{ border-color:#475569; background:rgba(2,6,23,.55); transform: translateY(-1px); }
    .opt.is-selected .optbox{ border-color:#38bdf8; box-shadow:0 0 0 2px rgba(56,189,248,.15) }

    .opt-ico{ width:40px;height:40px;border-radius:14px;display:grid;place-items:center;flex:0 0 auto; background:rgba(148,163,184,.10); border:1px solid rgba(148,163,184,.20); }
    .opt-title{ font-weight:900; }
    .opt-desc{ font-size:13px; color:var(--muted); margin-top:2px; line-height:1.45; }

    .btnrow{ margin-top:16px; display:flex; gap:12px; flex-wrap:wrap }
    .btn{
      appearance:none; border:0; cursor:pointer; background:var(--btn); color:#fff; font-weight:900;
      padding:12px 16px; border-radius:12px; font-size:15px; text-decoration:none; display:inline-flex; align-items:center; gap:10px; justify-content:center
    }
    .btn:hover{ background:var(--btn-h) }
    .btn-secondary{ background:transparent; border:1px solid #475569; color:#cbd5e1 }
    .btn-secondary:hover{ background:#1e293b; border-color:#94a3b8 }

    .err{ margin-top:16px; padding:12px 16px; border-radius:12px; background:rgba(239,68,68,.12); color:#fecaca; border:1px solid rgba(239,68,68,.28); font-size:.9rem; line-height:1.4 }
    .hidden-inputs{ position:absolute; left:-9999px; top:auto; width:1px; height:1px; overflow:hidden }

    .footer { text-align:center; margin-top:26px; font-size:13px; }
    .footer a { color:var(--muted); text-decoration: underline; }

    @media(max-width:700px){ 
      .data-grid{ grid-template-columns:1fr; } 
      .card{ padding:26px; }
      .box-header{ flex-direction:column; align-items:flex-start; }
      .box-amount{ text-align:left; }
      .st-row{ flex-direction:column; align-items:flex-start; }
      .st-price{ text-align:left; }
    }

   


  </style>
</head>
<body>
@php
    // -------------------------
    // Datos base
    // -------------------------
    $cliente = $venta->cliente ?? null;
    $form = $link->meta['form_data'] ?? [];

    $titular = trim(($form['nombre'] ?? '') . ' ' . ($form['apellidos'] ?? ''));
    if (!$titular) $titular = trim(($cliente->nombre ?? '') . ' ' . ($cliente->apellidos ?? ''));
    if (!$titular) $titular = $cliente->razon_social ?? '—';

    // Items
    $itemsUnicos = $venta->items->filter(fn($i) => $i->servicio && $i->servicio->tipo->value === 'unico');
    $itemsRecurrentes = $venta->items->filter(fn($i) => $i->servicio && $i->servicio->tipo->value === 'recurrente');

    // IVA
    $cpCliente   = $form['cp'] ?? ($cliente->codigo_postal ?? '');
    $provCliente = $form['provincia'] ?? ($cliente->provincia ?? '');
    $porcentajeIva = \App\Models\Cliente::getPorcentajeImpuesto($cpCliente, $provCliente);
    $factorIva = 1 + ($porcentajeIva / 100);

    // Base aplicada (lo que cobras hoy)
    $baseInicial = (float) $itemsUnicos->sum(fn($i) => (float) ($i->subtotal_aplicado ?? 0));

    // Base "antes"
    $baseInicialOriginal = (float) $itemsUnicos->sum(function ($i) {
        $qty = (float) ($i->cantidad ?? 1);
        if ($qty <= 0) $qty = 1;

        $orig =
            (float) ($i->subtotal_base ?? 0)
            ?: ((float) ($i->precio_base_original ?? 0) * $qty)
            ?: ((float) ($i->precio_base ?? 0) * $qty)
            ?: ((float) ($i->servicio?->precio_base ?? 0) * $qty);

        return max(0, (float) $orig);
    });

    $descuentoBase  = max(0, round($baseInicialOriginal - $baseInicial, 2));
    $descuentoTotal = ($descuentoBase > 0.009) ? round($descuentoBase * $factorIva, 2) : 0.0;

    $totalServiciosAntes = round($baseInicialOriginal * $factorIva, 2); // IVA incl.
    $ivaInicial   = round($baseInicial * ($porcentajeIva / 100), 2);
    $totalInicial = round($baseInicial * $factorIva, 2);

    // Método preseleccionado
    $preselect = old('pago_inicial_metodo', $venta->pago_inicial_metodo ?? ($link->meta['pago_inicial_metodo'] ?? 'stripe'));

    // CTA volver
    $backRoute = route('conversion.contract', ['token' => $link->token]);
@endphp

<div class="wrap">
  <div class="card">

    <div class="header">
      <div class="logo">
        <img src="{{ asset('images/logo_dark.png') }}" alt="AsesorFy"
             onerror="this.replaceWith(document.createTextNode('AsesorFy'));">
      </div>

      <h1 class="h1" style="color:#43beeb;">Pago inicial</h1>

      <p class="subhead">
        Este pago activa tus <span class="text-white">servicios de inicio</span>.
        @if($itemsRecurrentes->isNotEmpty())
          Después configurarás la <span class="text-white">cuota mensual</span> (tarjeta o IBAN).
        @else
          No hay cuota mensual asociada a esta contratación.
        @endif
      </p>
    </div>

    @if(session('error'))
      <div class="err">{{ session('error') }}</div>
    @endif

    {{-- DATOS CLIENTE --}}
    <div class="data-grid">
      <div><div class="lbl">Titular</div><div class="val">{{ $titular  }}</div></div>
      <div><div class="lbl">DNI / CIF</div><div class="val">{{ $form['cif'] ?? $form['dni'] ?? '—' }}</div></div>
      <div><div class="lbl">Email</div><div class="val">{{ $form['email'] ?? '—' }}</div></div>
    </div>

    {{-- ESTADO / RESUMEN --}}
    <div class="status-box info">
      <div class="box-header">
        <div class="box-title" style="color: var(--blue);">🧾 Lo que pagas hoy</div>
        <div class="box-amount" style="color: var(--blue);">
          {{ number_format($totalInicial, 2, ',', '.') }} €
          <small style="font-size:11px;font-weight:800;color:var(--muted);">IVA inc.</small>
        </div>
      </div>

      <div class="box-body">
        Base imponible: <span class="text-white">{{ number_format($baseInicial, 2, ',', '.') }} €</span>
        · IVA ({{ (int)$porcentajeIva }}%): <span class="text-white">{{ number_format($ivaInicial, 2, ',', '.') }} €</span>
        @if($descuentoTotal > 0.00001)
          <br>
          <span class="text-orange">Descuento aplicado hoy: -{{ number_format($descuentoTotal, 2, ',', '.') }} € (IVA inc.)</span>
        @endif
      </div>

      <div class="services-table">
        <div class="st-head">Servicios incluidos en este pago (pago único)</div>

        @forelse($itemsUnicos as $item)
          @php
            $nombre = $item->nombre_personalizado ?? $item->servicio->nombre ?? 'Servicio';
            $qty = (float) ($item->cantidad ?? 1);
            if ($qty <= 0) $qty = 1;

            $lineBaseAplicada = (float) ($item->subtotal_aplicado ?? 0);

            $lineBaseAntes =
              (float) ($item->subtotal_base ?? 0)
              ?: ((float) ($item->precio_base_original ?? 0) * $qty)
              ?: $lineBaseAplicada;

            $lineBaseAntes = max($lineBaseAntes, $lineBaseAplicada);
            $lineDiscBase  = max(0, $lineBaseAntes - $lineBaseAplicada);

            $lineTotalAntes    = round($lineBaseAntes * $factorIva, 2);
            $lineTotalAplicado = round($lineBaseAplicada * $factorIva, 2);
            $lineDiscTotal     = round($lineDiscBase * $factorIva, 2);
          @endphp

          <div class="st-row">
            <div>
              <div class="st-name">
                {{ $nombre }}
                @if($qty > 1)
                  <span style="font-weight:600;color:var(--muted);">(x{{ (int)$qty }})</span>
                @endif
              </div>
              <span class="st-meta">
                Activación / servicio de inicio. Este concepto se cobra una sola vez.
              </span>
            </div>

            <div class="st-price">
              {{ number_format($lineTotalAplicado, 2, ',', '.') }} €
              <small>IVA inc.</small>

              @if($lineDiscTotal > 0.00001)
                <div class="old">{{ number_format($lineTotalAntes, 2, ',', '.') }} €</div>
                <div class="disc">Descuento: -{{ number_format($lineDiscTotal, 2, ',', '.') }} €</div>
              @endif
            </div>
          </div>
        @empty
          <div class="box-body">No hay servicios de pago único en esta contratación.</div>
        @endforelse
      </div>
    </div>

    {{-- MÉTODO DE PAGO --}}
    <div class="status-box warn">
      <div class="box-header">
        <div class="box-title" style="color:#fbbf24;">⚡ Elige cómo pagar</div>
        <div class="box-amount" style="color:#fbbf24;">
          {{ number_format($totalInicial, 2, ',', '.') }} €
          <small style="font-size:11px;font-weight:800;color:var(--muted);">IVA inc.</small>
        </div>
      </div>

      <div class="box-body">
        Selecciona el método para completar el pago inicial.
        <br><span class="text-white">Recomendado:</span> tarjeta para activación inmediata.
      </div>

      <form method="POST" action="{{ route('conversion.pago-inicial.store', ['token' => $link->token]) }}" class="pay-methods">
        @csrf

        <div class="hidden-inputs">
          <input type="radio" name="pago_inicial_metodo" id="m_stripe" value="stripe" @checked($preselect === 'stripe')>
          <input type="radio" name="pago_inicial_metodo" id="m_transferencia" value="transferencia" @checked($preselect === 'transferencia')>
        </div>

        <div class="pm-head">Métodos disponibles</div>

        <div class="opt js-opt" data-method="stripe">
          <div class="optbox">
            <div class="opt-ico">💳</div>
            <div>
              <div class="opt-title">Tarjeta (Stripe)</div>
              <div class="opt-desc">
                Confirmación inmediata.
                @if($itemsRecurrentes->isNotEmpty())
                  Si luego hay cuota mensual, podrás <strong>usar esta misma tarjeta</strong> o elegir otra.
                @endif
              </div>
            </div>
          </div>
        </div>

        <div class="opt js-opt" data-method="transferencia" style="margin-top:10px;">
          <div class="optbox">
            <div class="opt-ico">🏦</div>
            <div>
              <div class="opt-title">Transferencia bancaria</div>
              <div class="opt-desc">
                Te mostraremos los datos (IBAN y concepto). La activación se realizará tras verificar el pago.
                @if($itemsRecurrentes->isNotEmpty())
                  Después podrás configurar la cuota mensual (si aplica).
                @endif
              </div>
            </div>
          </div>
        </div>

        @error('pago_inicial_metodo')
          <div class="err">{{ $message }}</div>
        @enderror

        <div class="btnrow">
          <button class="btn" type="submit">Continuar</button>
          <a class="btn btn-secondary" href="{{ $backRoute }}">Volver</a>
        </div>

        <div class="box-body" style="margin-top:10px;">
          🔒 Pago seguro: con tarjeta se procesa directamente con Stripe.
        </div>
      </form>
    </div>

    <div class="footer">
      <a href="https://asesorfy.net" target="_blank" rel="noopener noreferrer">Volver a la web principal</a>
    </div>

  </div>
</div>

<script>
(function () {
  const opts = document.querySelectorAll('.js-opt');
  const mStripe = document.getElementById('m_stripe');
  const mTransfer = document.getElementById('m_transferencia');

  function clearSelected(){ opts.forEach(o => o.classList.remove('is-selected')); }

  function syncSelected(){
    clearSelected();
    const current = (mTransfer && mTransfer.checked) ? 'transferencia' : 'stripe';
    opts.forEach(o => {
      if (o.getAttribute('data-method') === current) o.classList.add('is-selected');
    });
  }

  opts.forEach(o => {
    o.addEventListener('click', () => {
      const method = o.getAttribute('data-method');
      if (method === 'transferencia') mTransfer.checked = true;
      else mStripe.checked = true;
      syncSelected();
    });
  });

  syncSelected();
})();
</script>

</body>
</html>
