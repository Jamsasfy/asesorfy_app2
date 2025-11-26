{{-- resources/views/public/conversion/finished.blade.php --}}
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Firma completada</title>
  <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
  <style>
    :root {
      --bg:#0b1220;        /* fondo app */
      --card:#0f172a;      /* tarjeta */
      --muted:#94a3b8;     /* texto secundario */
      --border:#1f2a44;    /* bordes sutiles */
      --ok:#22c55e;        /* verde ok */
      --ok-strong:#16a34a; /* verde fuerte */
      --btn:#16a34a;       /* botón descargar */
      --btn-h:#15803d;     /* hover */
      --link:#93c5fd;      /* enlaces */
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

    /* ----- Cabecera ----- */
    .logo{
      display:flex; align-items:center; gap:12px; margin-bottom:18px;
    }
    .logo img{
      height:40px;
      width:auto; display:block;
    }

    .header{
      display:flex; align-items:center; gap:14px; margin-bottom:14px;
    }
    .badge-ok{
      width:38px;height:38px;display:grid;place-items:center;
      background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.35);
      color:var(--ok); border-radius:999px; flex:0 0 auto;
      font-weight:800;
    }
    .title{font-size:26px;font-weight:800;letter-spacing:.2px;line-height:1.15}
    .muted{color:var(--muted)}

    /* ----- Datos ----- */
    .grid{
      display:grid; grid-template-columns: 1fr 1fr; gap:10px 24px; margin-top:16px;
    }
    .label{font-size:13px;color:var(--muted);margin-bottom:3px}
    .value{font-size:16px;font-weight:600}
    .state{color:var(--ok-strong); font-weight:800}

    /* ----- Panel descarga / pago ----- */
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
    
    /* Estilos específicos para botón de pago */
    .btn-pay {
        background: #635bff !important; /* Morado Stripe */
        width: 100%;
        font-size: 1.1rem;
        padding: 14px;
        transition: transform 0.1s;
    }
    .btn-pay:hover {
        background: #5346e0 !important;
        transform: translateY(-2px);
    }

    .link{color:var(--link); text-decoration:underline}
    .legal{margin-top:10px; font-size:13px; color:var(--muted)}
    .footer-actions{margin-top:20px}

    /* ----- Responsive (móvil primero) ----- */
    @media (max-width: 720px){
      .wrap{padding:12px;margin:20px auto}
      .card{padding:20px;border-radius:16px}
      .logo{justify-content:center}
      .title{font-size:22px;text-align:center}
      .header{flex-direction:column;align-items:center;text-align:center;gap:10px}
      .grid{grid-template-columns:1fr; gap:10px}
      .value{font-size:15px}
      .panel{padding:16px}
      .actions .btn{width:100%; justify-content:center}
      .footer-actions{display:flex; justify-content:center}
    }
  </style>
</head>
<body>
  @php
    // Nos aseguramos de tener siempre un array, aunque venga vacío
    $form = $form ?? [];
  @endphp

  <div class="wrap">
    <div class="card">

      {{-- Logo AsesorFy --}}
      <div class="logo">
        <img src="{{ asset('images/logo.png') }}" alt="AsesorFy"
             onerror="this.replaceWith(document.createTextNode('AsesorFy'));">
      </div>

      {{-- Cabecera OK --}}
      <div class="header">
        <div class="badge-ok">✔</div>
        <div>
          <div class="title">¡Firma completada!</div>
          <div class="muted">Hemos recibido tu aceptación y registro con sello de tiempo. Contrato con AsesorFy firmado correctamente.</div>
        </div>
      </div>

      {{-- Datos visibles al cliente --}}
      <div class="grid">
        <div>
          <div class="label">Nombre</div>
          <div class="value">
            {{ $form['nombre'] ?? $lead->nombre ?? '—' }}
          </div>
        </div>

        <div>
          <div class="label">Apellidos</div>
          <div class="value">
            {{ $form['apellidos'] ?? '—' }}
          </div>
        </div>

        <div>
          <div class="label">Email</div>
          <div class="value">
            {{ $form['email'] ?? $lead->email ?? '—' }}
          </div>
        </div>

        <div>
          <div class="label">Teléfono</div>
          <div class="value">
            {{ $form['telefono'] ?? $lead->tfn ?? '—' }}
          </div>
        </div>

        <div>
          <div class="label">DNI / CIF</div>
          <div class="value">
            {{ $form['dni'] ?? $form['dni_nie'] ?? $lead->dni ?? $lead->cif ?? '—' }}
          </div>
        </div>

        <div>
          <div class="label">Estado</div>
          <div class="value state">Firmado</div>
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

        <div class="legal">
          Enviaremos una copia por email junto con las instrucciones para continuar.
          Este envío cumple con la normativa aplicable de servicios de la sociedad de la información y consumo.
        </div>
      </div>

      {{-- BLOQUE 2: PAGO PENDIENTE (Stripe) --}}
      {{-- Solo aparece si el controlador pasa una $factura pendiente --}}
      @if (session('error'))
    <div style="
        margin-bottom: 16px;
        padding: 12px 16px;
        border-radius: 8px;
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
        font-size: 0.9rem;
    ">
        {{ session('error') }}
    </div>
@endif
      @if(isset($factura) && $factura)
        <div class="panel" style="background: #fff7ed; border-color: #fdba74; margin-top: 20px;">
            <div style="text-align: center;">
                <h2 style="color: #9a3412; margin-top: 0; font-size: 1.3rem;">⚠️ Finalizar Contratación</h2>
                <p style="color: #7c2d12; font-size: 0.95rem; margin-bottom: 15px;">
                    El contrato está firmado, pero para activar el servicio de <strong>Constitución/Capitalización/Alta</strong> es necesario abonar la provisión de fondos.
                </p>
                
                <div style="font-size: 2rem; font-weight: 800; color: #0f172a; margin: 15px 0;">
                    {{ number_format($factura->total_factura, 2, ',', '.') }} €
                </div>

                <div class="actions" style="justify-content: center;">
                    <a href="{{ route('payment.pay', $factura->id) }}" class="btn btn-pay">
                        💳 Pagar con Tarjeta (Seguro)
                    </a>
                </div>
                
                <div class="legal" style="text-align: center; margin-top: 12px; color: #7c2d12;">
                    Pago seguro procesado por Stripe. <br>
                    Si prefieres transferencia bancaria, contacta con tu asesor.
                </div>
            </div>
        </div>
      @endif

      {{-- Botón volver --}}
      <div class="footer-actions">
        <a class="link" href="https://asesorfy.net" target="_blank" rel="noopener noreferrer">Volver a AsesorFy</a>
      </div>

    </div>
  </div>
</body>
</html>