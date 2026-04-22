<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Acceso - AsesorFy</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #41c0e9 0%, #2d8bb3 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #f9fafb; padding: 30px; border-radius: 0 0 10px 10px; }
        .button { display: inline-block; padding: 15px 30px; background: #41c0e9; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; margin: 20px 0; }
        .button:hover { background: #2d8bb3; }
        .info-box { background: white; padding: 20px; border-left: 4px solid #41c0e9; margin: 20px 0; border-radius: 4px; }
        .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🏢 Nuevo Acceso Concedido</h1>
        </div>

        <div class="content">
            <p>Hola <strong>{{ $userName }}</strong>,</p>

            <p>Te informamos que ahora tienes acceso al portal de clientes de:</p>

            <div class="info-box">
                🏢 <strong>{{ $razonSocial }}</strong>
            </div>

            <p>Puedes acceder inmediatamente con tus credenciales habituales:</p>

            <div style="text-align: center; margin: 30px 0;">
                <a href="{{ $portalUrl }}" class="button">🔐 Acceder al Portal</a>
            </div>

            <div class="info-box">
                <strong>💡 Recuerda:</strong> Usa el mismo email y contraseña que ya tienes configurados.
            </div>

            <p>Si tienes alguna duda o necesitas ayuda, no dudes en contactar con tu asesor.</p>

            <p><strong>Equipo AsesorFy</strong></p>
        </div>

        <div class="footer">
            <p>Este es un correo automático, por favor no respondas a este mensaje.</p>
            <p>&copy; {{ date('Y') }} AsesorFy - Asesoría Fiscal, Contable y Laboral</p>
        </div>
    </div>
</body>
</html>
