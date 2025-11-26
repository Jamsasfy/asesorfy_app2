<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pago realizado - AsesorFy</title>
  <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
  <style>
    :root {
      --bg:#020617;
      --card:#020617;
      --card-inner:#0f172a;
      --muted:#94a3b8;
      --border:#1f2937;
      --ok:#22c55e;
      --ok-soft:rgba(34,197,94,.12);
      --ok-border:rgba(34,197,94,.45);
      --btn:#22c55e;
      --btn-hover:#16a34a;
    }

    *{box-sizing:border-box;margin:0;padding:0}
    body{
      min-height:100vh;
      margin:0;
      font-family: system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
      background:
        radial-gradient(circle at top left, rgba(56,189,248,.25), transparent 55%),
        radial-gradient(circle at bottom right, rgba(34,197,94,.18), transparent 55%),
        var(--bg);
      color:#e5e7eb;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:24px 16px;
    }

    .shell{
      width:100%;
      max-width:900px;
      background:radial-gradient(circle at top,rgba(148,163,184,.35),transparent 55%);
      border-radius:24px;
      padding:1px;
    }
    .card{
      background:radial-gradient(circle at top,rgba(15,23,42,1),#020617 65%);
      border-radius:24px;
      padding:24px 22px;
      border:1px solid rgba(15,23,42,.85);
      box-shadow:0 18px 45px rgba(0,0,0,.6);
      display:flex;
      flex-direction:column;
      gap:22px;
    }

    .header{
      display:flex;
      align-items:flex-start;
      gap:14px;
    }
    .badge-ok{
      width:40px;height:40px;
      border-radius:999px;
      display:grid;
      place-items:center;
      background:var(--ok-soft);
      border:1px solid var(--ok-border);
      color:var(--ok);
      font-size:20px;
      flex:0 0 auto;
    }
    .title-wrap h1{
      font-size:24px;
      font-weight:700;
      letter-spacing:.02em;
      margin-bottom:4px;
    }
    .title-wrap p{
      font-size:14px;
      color:var(--muted);
    }

    .content{
      display:grid;
      grid-template-columns: minmax(0,1.2fr) minmax(0,1fr);
      gap:20px;
    }
    @media (max-width: 720px){
      .card{padding:20px 18px}
      .content{grid-template-columns:1fr}
    }

    .panel{
      background:rgba(15,23,42,.9);
      border-radius:18px;
      border:1px solid var(--border);
      padding:18px 16px;
    }

    .section-title{
      font-size:13px;
      text-transform:uppercase;
      letter-spacing:.16em;
      color:var(--muted);
      margin-bottom:10px;
    }

    .pill{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:5px 10px;
      border-radius:999px;
      font-size:12px;
      background:rgba(15,23,42,.9);
      border:1px solid var(--border);
      color:var(--muted);
      margin-bottom:8px;
    }
    .pill-ok{
      border-color:var(--ok-border);
      color:var(--ok);
      background:var(--ok-soft);
    }

    .grid{
      display:grid;
      grid-template-columns: 1.1fr 1fr;
      gap:8px 16px;
      font-size:14px;
    }
    .label{color:var(--muted)}
    .value{font-weight:500}

    .amount{
      font-size:22px;
      font-weight:700;
      margin-bottom:6px;
    }

    .amount-row{
      display:flex;
      justify-content:space-between;
      font-size:14px;
      margin-top:6px;
    }
    .amount-row span:last-child{
      font-weight:500;
    }

    .actions{
      display:flex;
      flex-wrap:wrap;
      gap:10px;
      margin-top:14px;
    }
    .btn{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:9px 16px;
      border-radius:999px;
      border:1px solid transparent;
      font-size:14px;
      font-weight:500;
      cursor:pointer;
      text-decoration:none;
      transition:.18s all ease-out;
      white-space:nowrap;
    }
    .btn-primary{
      background:var(--btn);
      border-color:var(--btn);
      color:#022c22;
      box-shadow:0 10px 25px rgba(34,197,94,.35);
    }
    .btn-primary:hover{
      background:var(--btn-hover);
      border-color:var(--btn-hover);
      transform:translateY(-1px);
      box-shadow:0 14px 30px rgba(22,163,74,.4);
    }
    .btn-ghost{
      background:transparent;
      border-color:var(--border);
      color:var(--muted);
    }
    .btn-ghost:hover{
      border-color:#4b5563;
      color:#e5e7eb;
      background:rgba(15,23,42,.8);
    }

    .footer-note{
      margin-top:6px;
      font-size:12px;
      color:var(--muted);
    }
  </style>
</head>
<body>
  <main class="shell">
    <section class="card">
      <header class="header">
        <div class="badge-ok">✓</div>
        <div class="title-wrap">
          <h1>Pago recibido correctamente</h1>
          <p>
            Hemos registrado tu pago en AsesorFy y tu factura queda marcada como pagada.
            En breve tu asesor continuará con la gestión de tu alta.
          </p>
        </div>
      </header>

      <section class="content">
        {{-- Columna izquierda: información del cliente / factura --}}
        <div class="panel">
          <div class="section-title">Factura</div>

          @if(isset($factura))
            <div class="pill pill-ok">
              Factura {{ $factura->serie ?? '' }}{{ $factura->numero_factura ?? '' }}
            </div>

            <div class="grid">
              <div class="label">Cliente</div>
              <div class="value">
                {{ optional($factura->cliente)->razon_social
                    ?? optional($factura->cliente)->razon_social
                    ?? 'Cliente AsesorFy' }}
              </div>

              <div class="label">Estado</div>
              <div class="value">Pagada</div>

              <div class="label">Fecha de emisión</div>
              <div class="value">
                @php
                  $fecha = $factura->fecha_emision ?? $factura->created_at;
                @endphp
                {{ $fecha ? $fecha->format('d/m/Y') : '—' }}
              </div>

              <div class="label">ID de transacción</div>
              <div class="value">
                {{ $factura->stripe_payment_intent_id ?? 'Registrado en Stripe' }}
              </div>
            </div>
          @else
            <p class="label">El pago se ha registrado correctamente.</p>
          @endif
        </div>

        {{-- Columna derecha: importes y acciones --}}
        <div class="panel">
          <div class="section-title">Importe</div>

          @if(isset($factura))
            <p class="amount">
              {{ number_format($factura->total_factura ?? 0, 2, ',', '.') }} €
            </p>

            <div class="amount-row">
              <span>Base imponible</span>
              <span>{{ number_format($factura->base_imponible ?? 0, 2, ',', '.') }} €</span>
            </div>
            <div class="amount-row">
              <span>IVA</span>
              <span>{{ number_format($factura->total_iva ?? 0, 2, ',', '.') }} €</span>
            </div>
          @else
            <p class="amount">Pago confirmado</p>
          @endif

          <p style="margin-top:10px;font-size:14px;color:var(--muted);">
            Te enviaremos la factura al correo facilitado durante el proceso.
            Si no la encuentras, revisa también tu carpeta de spam.
          </p>

          <div class="actions">
            {{-- Ajusta esta URL al panel real de AsesorFy --}}
            <a href="https://asesorfy.net" class="btn btn-primary">
              Ir al panel de AsesorFy
            </a>

            {{-- Aquí en un futuro podrías enlazar a la descarga de la factura si ya lo tienes --}}
            {{--
            @if(isset($factura))
              <a href="{{ route('facturas.download', $factura) }}" class="btn btn-ghost">
                Descargar factura
              </a>
            @endif
            --}}
          </div>

          <p class="footer-note">
            Si detectas cualquier incidencia con el cobro, contacta con soporte
            indicando tu número de factura y el email utilizado en el pago.
          </p>
        </div>
      </section>
    </section>
  </main>
</body>
</html>
