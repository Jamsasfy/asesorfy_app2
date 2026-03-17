<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Restringido - {{ config('app.name') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #334155 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        /* Efecto de partículas/grid de IA */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                linear-gradient(rgba(59, 130, 246, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(59, 130, 246, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: gridMove 20s linear infinite;
        }
        
        @keyframes gridMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        
        .container {
            background: rgba(30, 41, 59, 0.95);
            backdrop-filter: blur(20px);
            max-width: 500px;
            width: 100%;
            border-radius: 16px;
            border: 1px solid rgba(59, 130, 246, 0.2);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5), 0 0 100px rgba(59, 130, 246, 0.1);
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
            position: relative;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .header {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            padding: 40px 30px;
            text-align: center;
            border-bottom: 1px solid rgba(59, 130, 246, 0.2);
            position: relative;
        }
        
        .header::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, #3b82f6, transparent);
        }
        
        .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 36px;
            box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3);
            position: relative;
        }
        
        .icon::before {
            content: '';
            position: absolute;
            inset: -3px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            z-index: -1;
            opacity: 0.5;
            filter: blur(10px);
        }
        
        .header h1 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #f1f5f9;
            letter-spacing: -0.5px;
        }
        
        .header p {
            font-size: 15px;
            color: #94a3b8;
        }
        
        .content {
            padding: 40px 30px;
        }
        
        .info-text {
            color: #cbd5e1;
            font-size: 15px;
            line-height: 1.7;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .contact-box {
            background: rgba(15, 23, 42, 0.5);
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid rgba(59, 130, 246, 0.1);
        }
        
        .contact-title {
            font-weight: 600;
            color: #e2e8f0;
            margin-bottom: 16px;
            font-size: 14px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            color: #cbd5e1;
            font-size: 15px;
        }
        
        .contact-item:last-child {
            margin-bottom: 0;
        }
        
        .contact-icon {
            width: 36px;
            height: 36px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 18px;
        }
        
        .contact-link {
            color: #60a5fa;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }
        
        .contact-link:hover {
            color: #3b82f6;
        }
        
        .btn-logout {
            display: block;
            width: 100%;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 16px;
            border: none;
            border-radius: 10px;
            text-align: center;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .btn-logout::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn-logout:hover::before {
            left: 100%;
        }
        
        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(59, 130, 246, 0.4);
        }
        
        .footer {
            text-align: center;
            padding: 20px;
            color: #64748b;
            font-size: 13px;
            border-top: 1px solid rgba(59, 130, 246, 0.1);
        }
        
        /* Animación de pulso en el ícono */
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .icon {
            animation: pulse 2s ease-in-out infinite;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="icon">🔒</div>
        <h1>Acceso Restringido</h1>
        <p>Sistema de Autenticación AsesorFy</p>
    </div>

    <div class="content">
        <p class="info-text">
            Tu acceso al portal ha sido temporalmente desactivado.<br>
            Contacta con nuestro equipo de soporte para reactivar tu cuenta.
        </p>

        <div class="contact-box">
            <div class="contact-title">Soporte Técnico</div>
            
            <div class="contact-item">
                <div class="contact-icon">📧</div>
                <div>
                    <a href="mailto:info@asesorfy.net" class="contact-link">info@asesorfy.net</a>
                </div>
            </div>
            
            <div class="contact-item">
                <div class="contact-icon">💬</div>
                <div>
                    <a href="https://wa.me/34722873562" class="contact-link" target="_blank">+34 722 873 562</a>
                    <span style="color: #64748b; font-size: 13px; margin-left: 6px;">(WhatsApp)</span>
                </div>
            </div>
        </div>

        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="btn-logout">
                Cerrar Sesión
            </button>
        </form>
    </div>

    <div class="footer">
        &copy; {{ date('Y') }} {{ config('app.name') }} - Sistema Inteligente de Gestión
    </div>
</div>

</body>
</html>
