<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Bienvenido a AsesorFy</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>

<style>
    /* ---------- TIPOGRAFÍA ---------- */
    @import url('https://fonts.googleapis.com/css2?family=Varela+Round&display=swap');

    /* RESET / CLIENT-SPECIFIC */
    body, table, td, a { -webkit-text-size-adjust:100%; }
    table { border-collapse:collapse !important; }
    img { border:0; outline:none; text-decoration:none; height:auto; line-height:100%; }

    /* MOBILE */
    @media screen and (max-width: 600px) {
        .wrapper { padding: 16px !important; }
        .inner  { padding: 20px !important; }
        .logo img { width:120px !important; }
    }
</style>
</head>

<body style="margin:0; padding:0; background:#f3f6fb;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6fb;">
    <tr>
        <td align="center" style="padding:24px;">

            {{-- CONTENEDOR --}}
            <table role="presentation" class="wrapper" width="620"
                  style="background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.06);">

                {{-- LOGO --}}
                <tr>
                    <td align="center" class="logo" style="padding:30px 20px; background:#eef2fa;">
                        <img src="{{ asset('images/logo.png') }}"
                             alt="Logo de AsesorFy"
                             width="140"
                             style="display:block; width:140px; max-width:100%; height:auto;">
                    </td>
                </tr>

                {{-- CUERPO --}}
                <tr>
                    <td class="inner" align="center" style="
                        padding:32px;
                        font-family:'Varela Round', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
                        color:#374151; font-size:16px; line-height:1.6;">

                        <h1 style="margin:0 0 20px 0; font-size:24px; color:#222;">
                            ¡Bienvenido a AsesorFy, {{ $user->name }}!
                        </h1>

                        <p>Te hemos dado de alta en la plataforma de trabajo de AsesorFy.</p>
                        <p>Tu email de acceso es este mismo: <strong>{{ $user->email }}</strong></p>
                        <p><strong>Tu administrador o coordinador te facilitará la contraseña</strong> para tu primer inicio de sesión.</p>

                        <p>Puedes acceder a la plataforma desde este enlace:</p>

                        <a href="{{ $accessUrl }}" style="
                            display:inline-block;
                            padding:12px 20px;
                            margin-top:15px;
                            background:#42c0e9;
                            color:#ffffff;
                            text-decoration:none;
                            border-radius:5px;
                            font-weight:bold;
                            font-family:'Varela Round', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;">
                            Acceder a la Plataforma
                        </a>

                        <p style="margin-top:25px; font-size:0.9em; color:#777;">
                            Si tienes problemas con el botón, copia y pega esta URL:<br>
                            <a href="{{ $accessUrl }}" style="color:#42c0e9; word-break:break-all;">{{ $accessUrl }}</a>
                        </p>
                    </td>
                </tr>
            </table>

            {{-- FOOTER --}}
            <div style="
                padding:14px;
                font-family:'Varela Round', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
                color:#9ca3af; font-size:12px;">
                © {{ date('Y') }} AsesorFy. Todos los derechos reservados.
            </div>

        </td>
    </tr>
</table>
</body>
</html>