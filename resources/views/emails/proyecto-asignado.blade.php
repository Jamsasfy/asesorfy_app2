<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Nuevo proyecto asignado - {{ config('app.name') }}</title>
  <style>
    body { font-family: Helvetica, Arial, sans-serif; background:#f3f4f6; margin:0; padding:0; color:#1f2937; line-height:1.6; }
    .container { max-width:600px; margin:30px auto; background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 4px 6px rgba(0,0,0,.05); border:1px solid #e5e7eb; }
    .header { background:#e0f7ff; padding:24px; text-align:center; border-bottom:1px solid #bae6fd; }
    .header img { height:45px; width:auto; display:inline-block; }
    .content { padding:40px 32px; }
    h1 { color:#0f172a; font-size:24px; margin:0 0 20px; font-weight:700; }
    p { margin-bottom:18px; color:#4b5563; font-size:15px; }
    .highlight { background:#f0fdf4; border-left:4px solid #22c55e; padding:15px; margin:22px 0; border-radius:4px; font-size:14px; color:#166534; }
    .data-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:16px; margin:18px 0; font-size:14px; }
    .data-box div { margin-bottom:6px; }
    .data-box span { color:#6b7280; }
    .data-box strong { color:#0f172a; }
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
    <h1>¡Hola, {{ $asesor->name }}! 👋</h1>

    <p>Se te ha asignado un nuevo proyecto. Aquí tienes los detalles:</p>

    <div class="data-box">
      <div><span>Proyecto:</span> <strong>{{ $proyecto->nombre }}</strong></div>
      <div><span>Cliente:</span> <strong>{{ $proyecto->cliente->razon_social ?? '—' }}</strong></div>
      <div><span>Estado:</span> <strong>{{ $proyecto->estado?->value ?? $proyecto->estado }}</strong></div>
      @if($proyecto->descripcion)
        <div><span>Descripción:</span> <strong>{{ Str::limit($proyecto->descripcion, 200) }}</strong></div>
      @endif
    </div>

    <div class="highlight">
      <strong>📋 Acción requerida:</strong><br>
      Accede al panel para revisar el proyecto y comenzar a trabajar en él.
    </div>

    <p style="text-align:center; margin:22px 0 10px;">
      <a href="{{ route('filament.admin.resources.proyectos.view', ['record' => $proyecto->id]) }}" class="btn">
        Ver Proyecto
      </a>
    </p>
  </div>

  <div class="footer">
    &copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
  </div>
</div>
</body>
</html>
