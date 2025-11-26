<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Enlace no disponible</title>
  <style>
    :root { --bg:#0b1220; --card:#0f172a; --muted:#94a3b8; --border:#1f2937; --ok:#22c55e; --warn:#f59e0b; --danger:#ef4444; --btn:#16a34a; }
    *{box-sizing:border-box} body{margin:0;background:var(--bg);color:#e5e7eb;font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto}
    .wrap{max-width:980px;margin:32px auto;padding:16px}
    .card{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:24px;box-shadow:0 10px 30px rgba(0,0,0,.35)}
    .row{display:flex;gap:18px;align-items:flex-start}
    .icon{width:46px;height:46px;border-radius:999px;display:grid;place-items:center;flex:0 0 auto}
    .icon.used{background:#1e293b;color:#fbbf24;border:1px solid #334155}
    .icon.expired{background:#1e293b;color:#f87171;border:1px solid #334155}
    h1{margin:0 0 6px;font-size:22px}
    p{margin:0 0 10px;color:var(--muted)}
    .box{margin-top:18px;background:#0b1220;border:1px dashed #22304a;border-radius:12px;padding:14px}
    .btn{display:inline-block;background:var(--btn);color:#fff;border:0;border-radius:10px;padding:.75rem 1.0rem;font-weight:600;text-decoration:none}
    .muted{color:var(--muted)}
    .top{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
    .brand{display:flex;align-items:center;gap:10px}
    .brand .logo{width:38px;height:38px;border-radius:8px;display:grid;place-items:center;background:#111827;border:1px solid #1f2937}
    @media (max-width:700px){ .row{flex-direction:column} }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top">
    
        <div class="logo">
          <img src="{{ asset('images/logo.png') }}" alt="AsesorFy" style="height:24px;width:auto;display:block"
               onerror="this.replaceWith(document.createTextNode('AF'));">
        </div>
      
  
    </div>

    <div class="card">
      <div class="row">
        @php $reason = $reason ?? 'invalid'; @endphp

        <div class="icon {{ $reason === 'expired' ? 'expired' : 'used' }}">
          @if($reason === 'expired') ⏳ @else ⚠️ @endif
        </div>

        <div>
          <h1>
            @if($reason === 'expired')
              Enlace caducado
            @elseif($reason === 'used')
              Este enlace ya fue usado
            @else
              Enlace no válido
            @endif
          </h1>

          <p>
            @if($reason === 'expired')
              El enlace de firma ha caducado por seguridad. Generaremos uno nuevo si lo solicitas.
            @elseif($reason === 'used')
              Este enlace ya se utilizó para completar la firma. Si necesitas una copia del contrato,
              puedes descargarla desde el email que te enviamos o pedir un nuevo acceso.
            @else
              El enlace no es válido o está incompleto.
            @endif
          </p>

          @isset($lead)
            <div class="box muted" style="font-size:.95rem">
              @if($lead->nombre)
                <div><strong>Nombre:</strong> {{ $lead->nombre }}</div>
              @endif
              @if($lead->email)
                <div><strong>Email:</strong> {{ $lead->email }}</div>
              @endif
            </div>
          @endisset

          <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap">
            <a class="btn" href="https://asesorfy.net">Volver a AsesorFy</a>
           
          </div>

          <p class="muted" style="margin-top:12px;font-size:.9rem">
            Si crees que es un error, contacta con tu asesor para que te reenvíe un enlace vigente.
          </p>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
