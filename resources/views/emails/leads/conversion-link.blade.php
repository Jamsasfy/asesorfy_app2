<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completa tu alta en AsesorFy</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
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
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }
        .header {
            /* CAMBIO: Azul muy claro en lugar del oscuro */
            background-color: #e0f7ff; 
            padding: 24px;
            text-align: center;
            border-bottom: 1px solid #bae6fd;
        }
        .header img {
            height: 45px; /* Un pelín más grande para que destaque en fondo claro */
            width: auto;
        }
        .content {
            padding: 32px;
        }
        h1 {
            color: #0f172a;
            font-size: 22px;
            margin-top: 0;
            margin-bottom: 16px;
        }
        p {
            margin-bottom: 16px;
            color: #4b5563;
        }
        .btn-wrap {
            text-align: center;
            margin: 30px 0;
        }
        .btn {
            display: inline-block;
            background-color: #0ea5e9; /* Azul AsesorFy */
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 99px;
            font-weight: bold;
            font-size: 16px;
            box-shadow: 0 4px 6px rgba(14, 165, 233, 0.2);
        }
        .btn:hover {
            background-color: #0284c7;
        }
        .footer {
            background-color: #f8fafc;
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
        }
        .link-alt {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 10px;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container">
        {{-- Cabecera --}}
        <div class="header">
            {{-- Asegúrate de que el logo se ve bien sobre fondo claro (letras oscuras o azules) --}}
            <img src="{{ asset('images/logo.png') }}" alt="AsesorFy">
        </div>

        {{-- Contenido --}}
        <div class="content">
            <h1>Hola, {{ $lead->nombre }} 👋</h1>
            
            <p>
                Ya tenemos todo listo para gestionar tu alta. Solo necesitamos que completes unos breves datos y firmes el contrato de prestación de servicios.
            </p>

            <p>
                Es un proceso 100% digital y seguro que te llevará menos de 2 minutos.
            </p>

            <div class="btn-wrap">
                <a href="{{ route('conversion.show', ['token' => $link->token]) }}" class="btn">
                    Completar datos y firmar
                </a>
            </div>

            <p style="font-size: 0.95rem;">
                Si tienes alguna duda durante el proceso, puedes responder a este correo y tu asesor te ayudará encantado.
            </p>
        </div>

        {{-- Pie --}}
        <div class="footer">
            &copy; {{ date('Y') }} AsesorFy. Todos los derechos reservados.<br>
            <div class="link-alt">
                Si el botón no funciona, copia y pega este enlace:<br>
                {{ route('conversion.show', ['token' => $link->token]) }}
            </div>
        </div>
    </div>
</body>
</html>