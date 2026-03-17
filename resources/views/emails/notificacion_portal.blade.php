<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { 
            padding: 30px; 
            text-align: center; 
            border-radius: 10px 10px 0 0; 
            color: white;
        }
        .header.info { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); }
        .header.aviso { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .header.urgente { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); }
        .header.critico { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
        .message { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .button { 
            display: inline-block; 
            padding: 15px 30px; 
            background: #41c0e9; 
            color: white !important; 
            text-decoration: none; 
            border-radius: 8px; 
            font-weight: bold; 
            margin: 20px 0; 
        }
        .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header {{ $notificacion->tipo }}">
            <h1>
                @if($notificacion->tipo === 'info') 🔵
                @elseif($notificacion->tipo === 'aviso') 🟡
                @elseif($notificacion->tipo === 'urgente') 🟠
                @elseif($notificacion->tipo === 'critico') 🔴
                @endif
                {{ $notificacion->titulo }}
            </h1>
        </div>
        
        <div class="content">
            <p>Hola <strong>{{ $usuario->name }}</strong>,</p>
            
            <div class="message">
                {!! $notificacion->mensaje !!}
            </div>

            <div style="text-align: center;">
                <a href="{{ url('/portal') }}" class="button">
                    Ver en el Portal
                </a>
            </div>

            @if($notificacion->bloquea_portal)
            <div style="background: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; border-radius: 4px; margin-top: 20px;">
                <strong>⚠️ Atención:</strong> Esta notificación requiere tu confirmación de lectura en el portal.
            </div>
            @endif

            <p style="margin-top: 30px;">
                Saludos,<br>
                <strong>Equipo AsesorFy</strong>
            </p>
        </div>

        <div class="footer">
            <p>© {{ date('Y') }} AsesorFy. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
