<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Documento pendiente de firma - {{ config('app.name') }}</title>
  <style>
    body { font-family: Helvetica, Arial, sans-serif; background:#f3f4f6; margin:0; padding:0; color:#1f2937; line-height:1.6; }
    .container { max-width:600px; margin:30px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 4px 6px rgba(0,0,0,.05); border:1px solid #e5e7eb; }
    .header { background:#e0f7ff; padding:24px; text-align:center; border-bottom:1px solid #bae6fd; }
    .header img { height:45px; width:auto; display:inline-block; }
    .content { padding:40px 32px; }
    h1 { color:#0f172a; font-size:24px; margin:0 0 20px; font-weight:700; }
    p { margin-bottom:18px; color:#4b5563; font-size:15px; }
    .note { background:#fff7ed; border-left:4px solid #fb923c; padding:12px 15px; margin:18px 0; border-radius:4px; font-size:13px; color:#7c2d12; }
    .btn { display:inline-block; background:#0ea5e9; color:#fff !important; text-decoration:none; padding:12px 18px; border-radius:8px; font-weight:700; }
    .footer { background:#f8fafc; padding:24px; text-align:center; font-size:12px; color:#94a3b8; border-top:1px solid #e2e8f0; }
  </style>
</head>
<body>
<div class="container">
  <div class="header">
    <img src="{{ url(asset('images/logo.png')) }}" alt="{{ config('app.name') }}">
  </div>
  <div class="content">
    <h1>Documento pendiente de firma 📄</h1>

    @if($contrato->cliente->tipo_cliente_id == 1)
        {{-- AUTÓNOMO --}}
        <p>Hola, <strong>{{ $contrato->cliente->nombre }} {{ $contrato->cliente->apellidos }}</strong>.</p>
    @else
        {{-- SOCIEDAD --}}
        <p>Hola, <strong>{{ $contrato->cliente->nombre }} {{ $contrato->cliente->apellidos }}</strong> ({{ $contrato->cliente->razon_social }}).</p>
    @endif

    <p>Tu asesor <strong>{{ $contrato->asesor->name }}</strong> ha generado un documento que requiere tu firma.</p>

    <div class="note">
      <strong>Asunto:</strong> {{ $contrato->titulo }}
    </div>

    <p>Por favor, accede al siguiente enlace para leer y firmar el documento:</p>

    <p style="text-align:center; margin:22px 0 10px;">
      <a href="{{ $url }}" class="btn">📝 Firmar documento</a>
    </p>

    <p style="font-size:12px; color:#9ca3af;">
      Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
      <span style="font-family: monospace;">{{ $url }}</span>
    </p>

    <p>Un saludo,<br><strong>El equipo de {{ config('app.name') }}</strong></p>
  </div>
  <div class="footer">
    &copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
  </div>
</div>
</body>
</html>
