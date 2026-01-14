<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contrato Firmado - {{ config('app.name') }}</title>
  <style>
    body { font-family: Helvetica, Arial, sans-serif; background:#f3f4f6; margin:0; padding:0; color:#1f2937; line-height:1.6; }
    .container { max-width:600px; margin:30px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 4px 6px rgba(0,0,0,.05); border:1px solid #e5e7eb; }
    .header { background:#e0f7ff; padding:24px; text-align:center; border-bottom:1px solid #bae6fd; }
    .header img { height:45px; width:auto; display:inline-block; }
    .content { padding:40px 32px; }
    h1 { color:#0f172a; font-size:24px; margin:0 0 20px; font-weight:700; }
    p { margin-bottom:18px; color:#4b5563; font-size:15px; }
    .highlight { background:#f0fdf4; border-left:4px solid #22c55e; padding:15px; margin:22px 0; border-radius:4px; font-size:14px; color:#166534; }
    .note { background:#fff7ed; border-left:4px solid #fb923c; padding:12px 15px; margin:18px 0; border-radius:4px; font-size:13px; color:#7c2d12; }
    .btn { display:inline-block; background:#0ea5e9; color:#fff !important; text-decoration:none; padding:12px 18px; border-radius:8px; font-weight:700; }
    .footer { background:#f8fafc; padding:24px; text-align:center; font-size:12px; color:#94a3b8; border-top:1px solid #e2e8f0; }
  </style>
</head>
<body>

@php
  $clienteNombre = $venta?->cliente?->razon_social ?: ($lead->nombre ?? 'Hola');
@endphp

<div class="container">
  <div class="header">
    <img src="{{ url(asset('images/logo.png')) }}" alt="{{ config('app.name') }}">
  </div>

  <div class="content">
    <h1>¡Hola, {{ $clienteNombre }}! 👋</h1>

    <p>
      Te confirmamos que hemos recibido tu firma correctamente.
      El proceso de alta ha quedado registrado.
    </p>

    <div class="highlight">
      <strong>📄 Contrato adjunto:</strong><br>
      Encontrarás una copia en PDF de tu contrato de servicios firmado adjunta a este correo.
    </div>

    <p>
      Si te quedaste a medias (por ejemplo, para terminar el pago o completar algún paso),
      puedes retomar el proceso desde este enlace:
    </p>

    <p style="text-align:center; margin:22px 0 10px;">
      <a href="{{ $resumeUrl }}" class="btn">Retomar mi alta</a>
    </p>

    <div class="note">
      <strong>Nota:</strong> si ya completaste todos los pasos (por ejemplo, ya realizaste el pago),
      no necesitas hacer nada más. Si pulsas el botón igualmente, verás el estado actualizado.
    </div>

    <p style="font-size:12px; color:#9ca3af;">
      Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
      <span style="font-family: monospace;">{{ $resumeUrl }}</span>
    </p>

    <p style="margin-top:20px;">
      Guarda este email como justificante de tu contratación.
    </p>
  </div>

  <div class="footer">
    &copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
  </div>
</div>

</body>
</html>
