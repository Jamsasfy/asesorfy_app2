<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #41c0e9 0%, #2d8bb3 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
        .button { display: inline-block; padding: 15px 30px; background: #41c0e9; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
        .button:hover { background: #2d8bb3; }
        .asesor-box { background: white; padding: 25px; border-left: 4px solid #41c0e9; margin: 20px 0; border-radius: 4px; }
        .telegram-box { background: #e8f4f8; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #0088cc; }
        .step { background: white; padding: 15px; margin: 10px 0; border-radius: 6px; border-left: 3px solid #41c0e9; }
        .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 14px; }
        .benefit { margin: 8px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👨‍💼 Tu asesor ha sido asignado</h1>
        </div>

        <div class="content">
            @if($esSociedad)
                <p>Hola <strong>{{ $nombreCliente }}</strong> ({{ $razonSocial }}),</p>
            @else
                <p>Hola <strong>{{ $nombreCliente }}</strong>,</p>
            @endif

            <p>Te informamos que tu asesor en AsesorFy ya ha sido asignado:</p>

            <div class="asesor-box">
                <h3 style="margin-top: 0;">👨‍💼 {{ $asesorNombre }}</h3>
                <p style="margin: 5px 0;"><strong>📧 Email:</strong> <a href="mailto:{{ $asesorEmail }}">{{ $asesorEmail }}</a></p>
            </div>

            <div class="telegram-box">
                <h3 style="margin-top: 0;">🔔 Comunícate por Telegram</h3>
                <p>Ya puedes activar Telegram para hablar directamente con tu asesor de forma rápida y cómoda.</p>

                <p><strong>Cómo activarlo:</strong></p>

                <div class="step">
                    <strong>1.</strong> Accede al portal de clientes
                </div>
                <div class="step">
                    <strong>2.</strong> Ve a "Mi Perfil" o "Configuración"
                </div>
                <div class="step">
                    <strong>3.</strong> Haz clic en "Conectar Telegram"
                </div>
                <div class="step">
                    <strong>4.</strong> Sigue las instrucciones en pantalla
                </div>

                <p><strong>Ventajas de Telegram:</strong></p>
                <div class="benefit">✅ Respuestas rápidas de tu asesor</div>
                <div class="benefit">✅ Envío fácil de documentos</div>
                <div class="benefit">✅ Notificaciones instantáneas</div>
                <div class="benefit">✅ Historial de conversaciones siempre disponible</div>
            </div>

            <div style="text-align: center;">
                <a href="{{ $portalUrl }}" class="button">🚀 Acceder al Portal</a>
            </div>

            <p>Si tienes alguna pregunta, no dudes en contactar con <strong>{{ $asesorNombre }}</strong> por email.</p>

            <p>¡Bienvenido al equipo AsesorFy!</p>
            <p><strong>Equipo AsesorFy</strong></p>
        </div>

        <div class="footer">
            <p>© {{ date('Y') }} AsesorFy. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html>
