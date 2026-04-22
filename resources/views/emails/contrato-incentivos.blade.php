<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #0ea5e9; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background-color: #ffffff; padding: 30px; border: 1px solid #e5e7eb; }
        .alert { background-color: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; }
        .button { display: inline-block; background-color: #0ea5e9; color: white !important; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">📄 {{ $tipo }} de Incentivos</h1>
            <p style="margin: 5px 0 0 0;">AsesorFy</p>
        </div>

        <div class="content">
            <p>Hola <strong>{{ $comercial->name }}</strong>,</p>

            <p>Se ha generado tu <strong>{{ $tipo }} de Incentivos Comerciales</strong> que requiere tu firma digital.</p>

            <div class="alert">
                <strong>⚠️ Acción requerida:</strong><br>
                Debes firmar digitalmente este contrato para poder comenzar a percibir comisiones según las reglas asignadas.
            </div>

            <p><strong>Contenido del contrato:</strong></p>
            <ul>
                <li>Reglas de comisión asignadas</li>
                <li>Mínimos mensuales y porcentajes</li>
                <li>Condiciones de cálculo y penalizaciones</li>
            </ul>

            <p>Encontrarás el contrato adjunto en este email. Para firmarlo digitalmente, haz clic en el siguiente botón:</p>

            <div style="text-align: center;">
                <a href="{{ $urlFirma }}" class="button">✍️ Firmar Contrato Ahora</a>
            </div>

            <p style="font-size: 14px; color: #666; margin-top: 30px;">
                <strong>Importante:</strong> Este link es personal e intransferible. No lo compartas con nadie.
                La firma digital tiene la misma validez legal que una firma manuscrita.
            </p>

            <p>Si tienes dudas sobre el contrato, contacta con tu coordinador antes de firmarlo.</p>

            <p>Saludos,<br><strong>Equipo AsesorFy</strong></p>
        </div>

        <div class="footer">
            <p>Este es un email automático. Por favor, no respondas a este mensaje.</p>
            <p>AsesorFy | www.asesorfy.net</p>
        </div>
    </div>
</body>
</html>
