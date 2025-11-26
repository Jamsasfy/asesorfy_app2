<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pago no completado - AsesorFy</title>
  <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
  <style>
    :root {
      --bg:#020617;
      --card:#020617;
      --card-inner:#0f172a;
      --muted:#9ca3af;
      --border:#1f2937;
      --danger:#ef4444;
      --danger-soft:rgba(239,68,68,.12);
      --danger-border:rgba(248,113,113,.6);
      --btn:#22c55e;
      --btn-hover:#16a34a;
    }

    *{box-sizing:border-box;margin:0;padding:0}
    body{
      min-height:100vh;
      margin:0;
      font-family: system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
      background:
        radial-gradient(circle at top left, rgba(248,113,113,.24), transparent 55%),
        radial-gradient(circle at bottom right, rgba(59,130,246,.18), transparent 55%),
        var(--bg);
      color:#e5e7eb;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:24px 16px;
    }

    .shell{
      width:100%;
      max-width:820px;
      background:radial-gradient(circle at top,rgba(248,113,113,.35),transparent 55%);
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
    .badge-danger{
      width:40px;height:40px;
      border-radius:999px;
      display:grid;
      place-items:center;
      background:var(--danger-soft);
      border:1px solid var(--danger-border);
      color:var(--danger);
      font-size:20px;
      flex:0 0 auto;
    }
    .title-wrap h1{
      font-size:22px;
      font-weight:700;
      letter-spacing:.02em;
      margin-bottom:4px;
    }
    .title-wrap p{
      font-size:14px;
      color:var(--muted);
    }

    .panel{
      background:rgba(15,23,42,.9);
      border-radius:18px;
      border:1px solid var(--border);
      padding:16px 16px 14px;
      font-size:14px;
    }

    .section-title{
      font-size:12px;
      text-transform:uppercase;
      letter-spacing:.16em;
      color:var(--muted);
      margin-bottom:8px;
    }

    .pill{
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:5px 10px;
      border-radius:999px;
      font-size:12px;
      border:1px solid var(--danger-border);
      background:var(--danger-soft);
      color:var(--danger);
      margin-bottom:8px;
    }

    .grid{
      display:grid;
      grid-template-columns: 1.1fr 1fr;
      gap:8px 16px;
      font-size:14px;
    }
    .label{color:var(--muted)}
    .value{font-weight:500}

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

    .hint{
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
        <div class="badge-danger">!</div>
        <div class="title-wrap">
          <h1>El pago no se ha completado</h1>
          <p>
            El proceso de pago se ha cancelado o se ha producido un error con la tarjeta.
            No se ha realizado ningún cargo.
          </p>
        </div>
      </header>

      <section class="panel">
        <div class="section-title">Detalles del intento</div>

        @if(isset($factura))
          <div class="pill">
            Factura {{ $factura->serie ?? '' }}{{ $factura->numero_factura ?? '' }} &mdash; pendiente de pago
          </div>

          <div class="grid">
            <div class="label">Cliente</div>
            <div class="value">
              {{ optional($factura->cliente)->razon_social
                  ?? optional($factura->cliente)->razon_social
                  ?? 'Cliente AsesorFy' }}
            </div>

            <div class="label">Importe</div>
            <div class="value">
              {{ number_format($factura->total_factura ?? 0, 2, ',', '.') }} €
            </div>

            <div class="label">Estado actual</div>
            <div class="value">
              {{ $factura->estado->name ?? 'PENDIENTE_PAGO' }}
            </div>
          </div>
        @else
          <p class="label">
            El intento de pago se ha cancelado. Puedes volver a intentarlo cuando quieras.
          </p>
        @endif

        <div class="actions">
          @if(isset($factura))
            <a href="{{ route('payment.pay', $factura->id) }}" class="btn btn-primary">
              Intentar pagar de nuevo
            </a>
          @endif

          <a href="https://asesorfy.net" class="btn btn-ghost">
            Volver a AsesorFy
          </a>
        </div>

        <p class="hint">
          Si el problema persiste, revisa los datos de tu tarjeta o contacta con tu banco.
          También puedes contactar con soporte de AsesorFy indicando tu número de factura.
        </p>
      </section>
    </section>
  </main>
</body>
</html>
