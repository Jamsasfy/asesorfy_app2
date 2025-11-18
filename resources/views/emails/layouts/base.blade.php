@php
    // Variables: $subject, $bodyHtml (ya procesado)
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>{{ $subject ?? config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>

<style>
    /* --------------- TIPOGRAFÍA --------------- */
    @import url('https://fonts.googleapis.com/css2?family=Varela+Round&display=swap');

    /* RESET MÓVIL */
    body, table, td, a { -webkit-text-size-adjust:100%; }
    table { border-collapse:collapse !important; }
    img { border:0; outline:none; text-decoration:none; }

    /* MOBILE */
    @media screen and (max-width: 600px) {
        .container {
            width: 100% !important;
            padding: 16px !important;
        }
        .inner {
            padding: 20px !important;
        }
        .logo img {
            width: 120px !important;
        }
    }
</style>

</head>
<body style="margin:0; padding:0; background:#f3f6fb;">

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f6fb;">
    <tr>
        <td align="center" style="padding:24px;">
            
            {{-- CONTENEDOR --}}
            <table role="presentation" class="container" width="620"
                style="background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.06);">

                {{-- LOGO --}}
                <tr>
                    <td align="center" class="logo" style="padding:30px 20px; background:#eef2fa;">
                        <img src="{{ asset('images/logo.png') }}" 
                             alt="Logo" 
                             width="140"
                             style="display:block; width:140px; max-width:100%; height:auto;">
                    </td>
                </tr>

                {{-- CUERPO --}}
                <tr>
                    <td class="inner" style="
                        padding:32px;
                        font-family:'Varela Round', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
                        color:#374151;
                        font-size:16px;
                        line-height:1.6;">
                        
                        {!! $bodyHtml !!}
                    </td>
                </tr>

                {{-- FIRMA --}}
                <tr>
                    <td style="padding:24px 32px; border-top:1px solid #eef2f7; background:#fafbff;
                        font-family:'Varela Round', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
                        color:#6b7280; font-size:12px;">

                        <p style="margin:0 0 6px 0;">Saludos,</p>
                        <p style="margin:0 0 6px 0;"><strong>Equipo {{ config('app.name') }}</strong></p>

                    <p style="margin:0;">
                        <a href="{{ config('app.public_url') }}" style="color:#6b7280; text-decoration:underline;">
                            {{ parse_url(config('app.public_url'), PHP_URL_HOST) ?? config('app.public_url') }}
                        </a>
                        &nbsp;|&nbsp;
                        {{ config('mail.reply_to.address') ?? config('mail.from.address') }}
                    </p>



                    </td>
                </tr>
            </table>

            {{-- FOOTER --}}
            <div style="
                padding:14px;
                font-family:'Varela Round', system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
                color:#9ca3af; font-size:12px;">
                © {{ date('Y') }} {{ config('app.name') }}. Todos los derechos reservados.
            </div>

        </td>
    </tr>
</table>

</body>
</html>