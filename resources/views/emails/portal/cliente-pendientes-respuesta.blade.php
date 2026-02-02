<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tienes documentos pendientes - {{ config('app.name') }}</title>

    <link href="https://fonts.googleapis.com/css2?family=Varela+Round&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Varela Round', 'Helvetica', 'Arial', sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 0;
            color: #1f2937;
            line-height: 1.6;
        }

        .container {
            max-width: 600px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }

        .header {
            background-color: #e0f7ff;
            padding: 24px;
            text-align: center;
            border-bottom: 1px solid #bae6fd;
        }

        .header img {
            height: 45px;
            width: auto;
            display: inline-block;
        }

        .content {
            padding: 44px 36px;
        }

        h1 {
            color: #0f172a;
            font-size: 24px;
            margin: 0 0 20px 0;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        p {
            margin-bottom: 20px;
            color: #4b5563;
            font-size: 15px;
        }

        .highlight {
            background-color: #f0f9ff;
            border-left: 4px solid #0ea5e9;
            padding: 15px;
            margin: 24px 0;
            border-radius: 6px;
            font-size: 14px;
            color: #0369a1;
        }

        .btn-wrap {
            text-align: center;
            margin: 26px 0 8px 0;
        }

        .btn {
            display: inline-block;
            background-color: #41c0e9;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 22px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 15px;
        }

        .url-box {
            width: 100%;
            margin-top: 12px;
            background: #fff;
            border-radius: 8px;
            border: 1px solid #dbeafe;
            font-size: 12px;
            border-collapse: collapse;
            padding: 12px;
            color: #1e293b;
            word-break: break-all;
        }

        .contact-box {
            margin-top: 30px;
            padding: 18px;
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 12px;
            text-align: center;
        }

        .contact-item {
            display: block;
            margin-bottom: 8px;
            color: #065f46;
            font-weight: 700;
            font-size: 15px;
            text-decoration: none;
        }

        .footer {
            background-color: #f8fafc;
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}">
    </div>

    <div class="content">
        <h1>TIENES DOCUMENTOS PENDIENTES</h1>

        <p>
            Hola{{ isset($userName) && $userName ? ', ' . e($userName) : '' }} 👋
        </p>

        <p>
            Tienes <strong>documentos pendientes de respuesta</strong> en el <strong>Portal de {{ config('app.name') }}</strong>.
        </p>

        <div class="highlight">
            <strong>✅ Qué tienes que hacer:</strong><br>
            Entra en <strong>Mis documentos</strong> y responde a las aclaraciones pendientes.
        </div>

        <div class="btn-wrap">
            <a href="{{ $url }}" class="btn">Ir a Mis documentos</a>
        </div>

        <p style="margin-top: 16px;">
            Si el botón no funciona, copia y pega este enlace en tu navegador:
        </p>

        <div class="url-box">{{ $url }}</div>

        <div class="contact-box">
            <a href="mailto:info@asesorfy.net" class="contact-item">📧 info@asesorfy.net</a>
            <a href="https://wa.me/34722873562" class="contact-item">💬 WhatsApp: 722 873 562</a>
            <span class="contact-item" style="font-weight: normal; font-size: 13px; margin-top: 5px;">(O respondiendo a este correo)</span>
        </div>
    </div>

    <div class="footer">
        &copy; {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
    </div>
</div>
</body>
</html>
