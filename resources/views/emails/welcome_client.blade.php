<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a {{ config('app.name') }}</title>
    <style>
        body { font-family: 'Helvetica', 'Arial', sans-serif; background-color: #f3f4f6; margin: 0; padding: 0; color: #1f2937; line-height: 1.6; }
        .container { max-width: 600px; margin: 30px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); border: 1px solid #e5e7eb; }
        .header { background-color: #e0f7ff; padding: 24px; text-align: center; border-bottom: 1px solid #bae6fd; }
        .header img { height: 45px; width: auto; display: inline-block; }
        .content { padding: 40px 32px; }
        
        h1 { color: #0f172a; font-size: 24px; margin: 0 0 20px 0; font-weight: 700; text-align: center; }
        p { margin-bottom: 16px; color: #4b5563; font-size: 15px; }
        
        /* Caja destacada de próximos pasos */
        .steps-box {
            background-color: #f8fafc;
            border-left: 4px solid #3b82f6; /* Azul */
            padding: 20px;
            margin: 25px 0;
            border-radius: 4px;
        }
        .steps-title {
            font-weight: 800;
            color: #1e3a8a;
            font-size: 16px;
            margin-bottom: 10px;
            display: block;
        }

        .contact-box {
            margin-top: 30px;
            padding: 20px;
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 8px;
            text-align: center;
        }
        .contact-item {
            display: block;
            margin-bottom: 8px;
            color: #065f46;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
        }

        .footer { background-color: #f8fafc; padding: 24px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="{{ asset('images/logo.png') }}" alt="{{ config('app.name') }}">
        </div>

        <div class="content">
            <h1>¡Bienvenido a bordo, {{ $nombreCliente }}! 🚀</h1>
            
            <p>
                Gracias por confiar en <strong>{{ config('app.name') }}</strong>. 
                Hemos recibido tu pago y tu contrato correctamente. A partir de ahora empezamos contigo el proceso de bienvenida como cliente.
            </p>

            <div class="steps-box">
                <span class="steps-title">📅 Próximos Pasos (24-48h)</span>
                <p style="margin: 0; font-size: 15px; color: #334155;">
                    Tu asesor/a asignado/a se pondrá en contacto contigo por teléfono para:
                </p>
                <ul style="margin: 10px 0 0 0; padding-left: 20px; color: #4b5563;">
                    <li>Explicarte los siguientes pasos.</li>
                    <li>Resolver tus dudas iniciales.</li>
                    <li>Revisar los servicios contratados (Alta, Empresa, Capitalización, etc.).</li>
                    <li>Indicarte la documentación necesaria.</li>
                </ul>
            </div>

            <p>
                Queremos que todo el proceso sea lo más sencillo posible para ti. 
                Si el asesor/a no te puede localizar, te mandará un email para agendar una cita o para que puedas contestar por ese medio.
            </p>

            <p style="margin-top: 25px;">
                <strong>¿Necesitas comentarnos algo antes de la llamada?</strong>
            </p>

            <div class="contact-box">
                <a href="mailto:info@asesorfy.net" class="contact-item">📧 info@asesorfy.net</a>
                <a href="https://wa.me/34722873562" class="contact-item">💬 WhatsApp: 722 873 562</a>
                <span class="contact-item" style="font-weight: normal; font-size: 13px; margin-top: 5px;">(O respondiendo a este correo)</span>
            </div>

            <p style="text-align: center; margin-top: 30px; font-weight: bold; color: #0f172a;">
                Gracias de nuevo por tu confianza.
            </p>
        </div>

        <div class="footer">
            El equipo de {{ config('app.name') }}.<br>
            &copy; {{ date('Y') }} Todos los derechos reservados.
        </div>
    </div>
</body>
</html>