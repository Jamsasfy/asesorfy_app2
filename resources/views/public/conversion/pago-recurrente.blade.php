<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Elegir pago mensual</title>
  <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">

  <style>
    :root{
      --bg:#0b1220; --card:#0f172a; --muted:#94a3b8; --border:#1f2a44;
      --ok:#22c55e; --btn:#16a34a; --btn-h:#15803d; --link:#93c5fd;
      --opt:#111827; --opt-b:#1f2937;
    }
    *{box-sizing:border-box} html,body{height:100%}
    body{margin:0;background:var(--bg);color:#e5e7eb;font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,Arial;}
    .wrap{max-width:1080px;margin:32px auto;padding:16px}
    .card{background:var(--card);border:1px solid var(--border);border-radius:18px;padding:28px;box-shadow:0 20px 40px rgba(0,0,0,.35)}
    .logo{display:flex;align-items:center;gap:12px;margin-bottom:18px}
    .logo img{height:40px;width:auto;display:block}
    .header{display:flex;align-items:center;gap:14px;margin-bottom:14px}
    .badge{width:38px;height:38px;display:grid;place-items:center;border-radius:999px;flex:0 0 auto;font-weight:800}
    .badge-rec{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.35);color:var(--ok)}
    .title{font-size:26px;font-weight:800;letter-spacing:.2px;line-height:1.15}
    .muted{color:var(--muted)}
    .panel{margin-top:18px;padding:18px 20px;border-radius:14px;background:var(--opt);border:1px solid var(--opt-b)}

    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px}

    .opt{display:block;cursor:pointer}
    .optbox{
      border:1px solid #334155;border-radius:14px;padding:16px 16px;
      background:rgba(2,6,23,.35);transition:.15s;
      display:flex;gap:12px;align-items:flex-start
    }
    .optbox:hover{border-color:#475569;background:rgba(2,6,23,.55)}
    .opt.is-selected .optbox{border-color:#38bdf8;box-shadow:0 0 0 2px rgba(56,189,248,.15)}

    .opt-ico{width:38px;height:38px;border-radius:12px;display:grid;place-items:center;flex:0 0 auto}
    .ico-same{background:rgba(99,91,255,.15);border:1px solid rgba(99,91,255,.35)}
    .ico-card{background:rgba(147,197,253,.10);border:1px solid rgba(147,197,253,.28)}
    .ico-sepa{background:rgba(56,189,248,.12);border:1px solid rgba(56,189,248,.32)}
    .opt-title{font-weight:800}
    .opt-desc{font-size:13px;color:var(--muted);margin-top:2px;line-height:1.35}

    .chip{
      display:inline-flex;align-items:center;gap:8px;
      margin-top:8px;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid rgba(148,163,184,.28);
      background:rgba(2,6,23,.35);
      color:#cbd5e1;
      font-size:12px;
      font-weight:700;
    }

    .btnrow{margin-top:16px;display:flex;gap:12px;flex-wrap:wrap}
    .btn{
      appearance:none;border:0;cursor:pointer;background:var(--btn);color:#fff;font-weight:800;
      padding:12px 16px;border-radius:12px;font-size:15px;text-decoration:none;display:inline-flex;align-items:center;gap:10px;justify-content:center
    }
    .btn:hover{background:var(--btn-h)}
    .btn-secondary{background:transparent;border:1px solid #475569;color:#cbd5e1}
    .btn-secondary:hover{background:#1e293b;border-color:#94a3b8}

    .err{margin-top:16px;padding:12px 16px;border-radius:8px;background:#fee2e2;color:#991b1b;border:1px solid #fecaca;font-size:.9rem}

    .hidden-inputs{position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden}

    @media(max-width:720px){
      .wrap{padding:12px;margin:20px auto}
      .card{padding:20px}
      .header{flex-direction:column;align-items:center;text-align:center;gap:10px}
      .grid2{grid-template-columns:1fr}
    }
  </style>
</head>
<body>
@php
  $cliente = $venta->cliente ?? null;

  $hasDefaultPM = $hasDefaultPM ?? false;
  $defaultPmLabel = $defaultPmLabel ?? null;
  $defaultPmType = $defaultPmType ?? null; // 'card' | 'sepa' | null

  // ✅ solo consideramos "tarjeta guardada" si ES CARD
  $hasDefaultCard = $hasDefaultPM && $defaultPmLabel && $defaultPmType === 'card';

  $preselectMetodo = old(
      'recurrente_metodo',
      $link->meta['recurrente_metodo'] ?? ($cliente->preferencia_pago_recurrente ?? 'tarjeta')
  );

  $preselectAccion = old(
      'tarjeta_accion',
      $link->meta['tarjeta_accion'] ?? ($hasDefaultCard ? 'usar_existente' : 'usar_otra')
  );

  // Método de pago inicial elegido (no implica que esté pagado)
  $pagoInicialMetodo = $pagoInicialMetodo ?? ($venta->pago_inicial_metodo ?? ($link->meta['pago_inicial_metodo'] ?? 'stripe'));

  // ✅ Si no hay servicios únicos, NO existe "importe inicial"
  $tieneUnico = $venta?->items?->contains(fn($i) => $i->servicio && $i->servicio->tipo->value === 'unico') ?? false;

  // ✅ Solo consideramos "pagado" si hay único y la venta lo marca como completado
  $pagoInicialCompletado = $tieneUnico ? ($venta?->tienePagoInicialCompletado() ?? false) : false;

  // ✅ Copy robusto (evita: "has pagado..." cuando no hay pago inicial o fue transferencia)
  $mensajeIntro = match (true) {
      $tieneUnico === false
          => 'No hay importe inicial. Ahora elige el método para tus cuotas mensuales.',

      $pagoInicialMetodo === 'stripe' && $pagoInicialCompletado
          => 'Has pagado el importe inicial con tarjeta. Puedes usar esa misma u otra distinta para las cuotas mensuales.',

      $pagoInicialMetodo === 'stripe' && !$pagoInicialCompletado
          => 'Has elegido pagar el importe inicial con tarjeta. Ahora elige el método para las cuotas mensuales.',

      $pagoInicialMetodo === 'transferencia'
          => 'Has elegido transferencia para el importe inicial. Ahora elige el método para las cuotas mensuales.',

      default
          => 'Elige el método para las cuotas mensuales.',
  };

  // ✅ Labels dinámicos para evitar "Usar otra tarjeta" cuando NO hay tarjeta guardada
  $tituloTarjetaNueva = $hasDefaultCard ? 'Usar otra tarjeta' : 'Pagar con tarjeta';
  $descTarjetaNueva = $hasDefaultCard
      ? 'Configurar una tarjeta distinta para la cuota mensual.'
      : 'Configurar una tarjeta para la cuota mensual.';
@endphp


<div class="wrap">
  <div class="card">

    <div class="logo">
      <img src="{{ asset('images/logo_dark.png') }}" alt="AsesorFy" onerror="this.replaceWith(document.createTextNode('AsesorFy'));">
    </div>

    <div class="header">
      <div class="badge badge-rec">↻</div>
      <div>
        <div class="title">Elige cómo pagar tu cuota mensual</div>
        <div class="muted">Configura el método para las cuotas recurrentes.</div>
      </div>
    </div>

    @if(session('error'))
      <div class="err">{{ session('error') }}</div>
    @endif

    <div class="panel">
      <div class="muted" style="font-size:13px;">
        {{ $mensajeIntro }}
      </div>

      <form method="POST" action="{{ route('conversion.pago-recurrente.store', ['token' => $link->token]) }}" style="margin-top:14px;">
        @csrf

        <div class="hidden-inputs">
          <input type="radio" name="recurrente_metodo" id="rm_tarjeta" value="tarjeta" @checked($preselectMetodo === 'tarjeta')>
          <input type="radio" name="recurrente_metodo" id="rm_domiciliacion" value="domiciliacion" @checked($preselectMetodo === 'domiciliacion')>

          <input type="radio" name="tarjeta_accion" id="ta_existente" value="usar_existente" @checked($preselectAccion === 'usar_existente')>
          <input type="radio" name="tarjeta_accion" id="ta_otra" value="usar_otra" @checked($preselectAccion === 'usar_otra')>
        </div>

        <div class="grid2">

          {{-- ✅ OPCIÓN 1: Usar tarjeta guardada (solo si ES TARJETA) --}}
          @if($hasDefaultCard)
            <div class="opt js-opt" data-method="tarjeta" data-action="usar_existente">
              <div class="optbox">
                <div class="opt-ico ico-same">💳</div>
                <div>
                  <div class="opt-title">Usar la tarjeta guardada</div>
                  <div class="opt-desc">
                    Cobro automático mensual con Stripe usando el método ya configurado.
                  </div>
                  <div class="chip">✅ {{ $defaultPmLabel }}</div>
                </div>
              </div>
            </div>
          @endif

          {{-- ✅ OPCIÓN 2: Tarjeta (dinámica) --}}
          <div class="opt js-opt" data-method="tarjeta" data-action="usar_otra">
            <div class="optbox">
              <div class="opt-ico ico-card">💳</div>
              <div>
                <div class="opt-title">{{ $tituloTarjetaNueva }}</div>
                <div class="opt-desc">
                  {{ $descTarjetaNueva }}
                  @if($hasDefaultCard)
                    <br><span class="muted">La actual ({{ $defaultPmLabel }}) se mantendrá sin cambios hasta que guardes la nueva.</span>
                  @endif
                </div>
              </div>
            </div>
          </div>

          {{-- ✅ OPCIÓN 3: SEPA --}}
          <div class="opt js-opt" data-method="domiciliacion" data-action="">
            <div class="optbox">
              <div class="opt-ico ico-sepa">🏦</div>
              <div>
                <div class="opt-title">Domiciliación (IBAN)</div>
                <div class="opt-desc">
                  Adeudo SEPA contra tu cuenta bancaria.
                  <br>El cargo puede reflejarse entre el día 1 y 15.
                </div>
              </div>
            </div>
          </div>

        </div>

        @error('recurrente_metodo')
          <div class="err" style="margin-top:12px;">{{ $message }}</div>
        @enderror

        @error('tarjeta_accion')
          <div class="err" style="margin-top:12px;">{{ $message }}</div>
        @enderror

        <div class="btnrow">
          <button class="btn" type="submit">Continuar</button>

          @php
            $tieneUnico = $venta->items->contains(fn($i)=>$i->servicio && $i->servicio->tipo->value === 'unico');
            $backRoute = $tieneUnico ? route('conversion.pago-inicial', ['token'=>$link->token]) : route('conversion.finished', ['token'=>$link->token]);
          @endphp
          <a class="btn btn-secondary" href="{{ $backRoute }}">Volver</a>
        </div>

        <div class="muted" style="margin-top:10px;font-size:13px;">
          🔒 Seguridad: la configuración de tarjeta/IBAN se gestiona con Stripe.
        </div>
      </form>
    </div>

    <div style="margin-top:18px;">
      <a class="muted" style="text-decoration:underline;color:var(--link)" href="https://asesorfy.net" target="_blank" rel="noopener noreferrer">Volver a AsesorFy</a>
    </div>

  </div>
</div>

<script>
  (function () {
    const opts = document.querySelectorAll('.js-opt');

    const rmTarjeta = document.getElementById('rm_tarjeta');
    const rmSepa = document.getElementById('rm_domiciliacion');
    const taExistente = document.getElementById('ta_existente');
    const taOtra = document.getElementById('ta_otra');

    function clearSelected() {
      opts.forEach(o => o.classList.remove('is-selected'));
    }

    function setCardActionEnabled(enabled) {
      if (!taExistente || !taOtra) return;
      taExistente.disabled = !enabled;
      taOtra.disabled = !enabled;

      if (!enabled) {
        taExistente.checked = false;
        taOtra.checked = false;
      } else {
        if (!taExistente.checked && !taOtra.checked) {
          taOtra.checked = true;
        }
      }
    }

    function setSelectedFromInputs() {
      clearSelected();

      const metodo = rmSepa.checked ? 'domiciliacion' : 'tarjeta';

      setCardActionEnabled(metodo === 'tarjeta');

      const accion = (taExistente && taExistente.checked) ? 'usar_existente' : 'usar_otra';

      opts.forEach(o => {
        const m = o.getAttribute('data-method');
        const a = o.getAttribute('data-action') || '';
        if (m === metodo) {
          if (metodo === 'domiciliacion') {
            o.classList.add('is-selected');
          } else {
            if (a === accion) o.classList.add('is-selected');
          }
        }
      });
    }

    opts.forEach(o => {
      o.addEventListener('click', () => {
        const method = o.getAttribute('data-method');
        const action = o.getAttribute('data-action') || '';

        if (method === 'domiciliacion') {
          rmSepa.checked = true;
        } else {
          rmTarjeta.checked = true;
          if (action === 'usar_existente' && taExistente) taExistente.checked = true;
          if (action === 'usar_otra' && taOtra) taOtra.checked = true;
        }

        setSelectedFromInputs();
      });
    });

    setSelectedFromInputs();
  })();
</script>

</body>
</html>
