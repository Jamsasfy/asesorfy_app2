<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Información sobre tu servicio - {{ config('app.name') }}</title>
  <style>
    body { font-family: Helvetica, Arial, sans-serif; background:#f3f4f6; margin:0; padding:0; color:#1f2937; line-height:1.6; }
    .container { max-width:600px; margin:30px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 4px 6px rgba(0,0,0,.05); border:1px solid #e5e7eb; }
    .header { background:#e0f7ff; padding:24px; text-align:center; border-bottom:1px solid #bae6fd; }
    .header img { height:45px; width:auto; display:inline-block; }
    .content { padding:40px 32px; }
    h1 { color:#0f172a; font-size:24px; margin:0 0 20px; font-weight:700; }
    p { margin-bottom:18px; color:#4b5563; font-size:15px; }
    .note { background:#fff7ed; border-left:4px solid #fb923c; padding:12px 15px; margin:18px 0; border-radius:4px; font-size:13px; color:#7c2d12; }
    .footer { background:#f8fafc; padding:24px; text-align:center; font-size:12px; color:#94a3b8; border-top:1px solid #e2e8f0; }
  </style>
</head>
<body>
<div class="container">
  <div class="header">
    <img src="{{ url(asset('images/logo.png')) }}" alt="{{ config('app.name') }}">
  </div>
  <div class="content">
    @if($cliente->tipo_cliente_id == 1)
        <h1>Hola, {{ $cliente->nombre }} {{ $cliente->apellidos }}</h1>
    @else
        <h1>Hola, {{ $cliente->nombre }} {{ $cliente->apellidos }} ({{ $cliente->razon_social }})</h1>
    @endif

    <p>Queremos informarte de que el servicio de asesoría recurrente asociado a tu expediente no será activado.</p>

    <div class="note">
      <strong>¿Qué significa esto?</strong><br>
      Te informamos de que el servicio de asesoría mensual asociado a tu expediente no será activado. No se realizará ningún cargo adicional en tu cuenta.
    </div>

    <p>Si tienes cualquier duda o quieres retomar el servicio en el futuro, no dudes en ponerte en contacto con nosotros.</p>

    <p>Un saludo,<br><strong>El equipo de {{ config('app.name') }}</strong></p>
  </div>
  <div class="footer">
    &copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
  </div>
</div>
</body>
</html>
