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
        .info-box { background: white; padding: 20px; border-left: 4px solid #41c0e9; margin: 20px 0; border-radius: 4px; }
        .warning { background: #fef3c7; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #f59e0b; }
        .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">

        {{-- Cabecera diferenciada --}}
        @if($context === 'creacion_manual')
        <div class="header">
            <h1>🔓 Nuevo acceso al portal</h1>
        </div>
        @else
        <div class="header">
            <h1>🎉 ¡Bienvenido a AsesorFy!</h1>
        </div>
        @endif

        <div class="content">

            {{-- Saludo diferenciado --}}
            @if($context === 'creacion_manual')
                <p>Hola <strong>{{ $userName }}</strong>,</p>
                <p>Se te ha dado acceso al portal de clientes de <strong>{{ $razonSocial }}</strong> en AsesorFy.</p>
            @else
                <p>Hola <strong>{{ $nombreCliente }}</strong>,</p>
                <p>¡Enhorabuena! Tu cuenta en AsesorFy ha sido creada exitosamente.</p>
            @endif

            {{-- Caja de información --}}
            <div class="info-box">
                <p><strong>📧 Tu email de acceso:</strong> {{ $email }}</p>
                @if($context === 'bienvenida_cliente')
                    @if($asesor)
                        <p><strong>👨‍💼 Tu asesor asignado:</strong> {{ $asesorNombre }}</p>
                    @endif
                    @if($suscripcion)
                        <p><strong>📦 Servicio contratado:</strong> {{ $servicioNombre }}</p>
                    @endif
                @endif
            </div>

            <h3>🔐 Activa tu cuenta</h3>
            <p>Para comenzar a usar el portal de clientes, haz clic en el botón de abajo y crea tu contraseña:</p>

            <div style="text-align: center;">
                <a href="{{ $activationUrl }}" class="button">✨ Activar mi cuenta</a>
            </div>

            <div class="warning">
                <strong>⏰ Importante:</strong> Este enlace expira el <strong>{{ $expiresAt }}</strong> (72 horas).
            </div>

            <p>Una vez activada tu cuenta, podrás:</p>
            <ul>
                <li>📄 Ver y descargar tus facturas</li>
                <li>📁 Acceder a tus documentos</li>
                <li>💬 Comunicarte con tu asesor</li>
                <li>💳 Gestionar tu método de pago</li>
            </ul>

            <p>Si tienes alguna pregunta, no dudes en contactar con tu asesor.</p>

            <p>¡Bienvenido a bordo!</p>
            <p><strong>Equipo AsesorFy</strong></p>
        </div>

        <div class="footer">
            <p>© {{ date('Y') }} AsesorFy. Todos los derechos reservados.</p>
            <p style="font-size: 12px;">Si no solicitaste este acceso, puedes ignorar este email.</p>
        </div>
    </div>
</body>
</html>
