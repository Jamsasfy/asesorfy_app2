<div style="font-family: Arial, sans-serif; line-height:1.5">
    <h2>Vincula tu Telegram con AsesorFy</h2>

    <p>
        Hola {{ $cliente->razon_social ?? ($cliente->nombre ?? '👋') }},
    </p>

    <p>
        Para chatear con tu asesor por Telegram, pulsa aquí:
    </p>

    <p>
        <a href="{{ $url }}" style="display:inline-block; padding:10px 14px; background:#2AABEE; color:#fff; border-radius:10px; text-decoration:none; font-weight:700">
            Vincular Telegram
        </a>
    </p>

    <p style="color:#666; font-size:12px">
        Si no has solicitado esto, ignora este email.
    </p>
</div>
