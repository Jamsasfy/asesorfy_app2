<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #10b981; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
        .content { background-color: #ffffff; padding: 30px; border: 1px solid #e5e7eb; }
        .success-box { background-color: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin: 20px 0; }
        .info-box { background-color: #f0f9ff; border-left: 4px solid #0ea5e9; padding: 15px; margin: 20px 0; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0;">✅ Contratos Firmados</h1>
            <p style="margin: 5px 0 0 0;">Contratos de Incentivos</p>
        </div>

        <div class="content">
            <p>Hola <strong>{{ $comercial->name }}</strong>,</p>

            <div class="success-box">
                <p style="margin: 0;">
                    <strong>✅ Contratos de Incentivos</strong><br>
                    Adjunto a este email encontrarás {{ $cantidadContratos == 1 ? 'tu contrato firmado' : "tus {$cantidadContratos} contratos firmados" }} en formato PDF.
                </p>
            </div>

            @if($cantidadContratos > 1)
            <p><strong>Documentos adjuntos:</strong></p>
            <ul style="margin: 0; padding-left: 20px;">
                @foreach($contratos as $contrato)
                <li>
                    <strong>{{ $contrato->tipo === 'base' ? 'Contrato Base' : 'Anexo' }}</strong>
                    — Firmado el {{ $contrato->fecha_firma->format('d/m/Y') }} a las {{ $contrato->fecha_firma->format('H:i') }}
                </li>
                @endforeach
            </ul>
            @else
            <p>
                Firmado el <strong>{{ $contratos->first()->fecha_firma->format('d/m/Y') }}</strong>
                a las <strong>{{ $contratos->first()->fecha_firma->format('H:i') }}</strong>.
            </p>
            @endif

            <div class="info-box">
                <p style="margin: 0 0 10px 0;"><strong>ℹ️ Información importante:</strong></p>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Este contrato es el documento oficial que rige tu sistema de incentivos</li>
                    <li>Las comisiones se calcularán según las reglas especificadas</li>
                    <li>Conserva este documento para futuras referencias</li>
                    <li>Cualquier modificación requerirá un nuevo anexo firmado</li>
                </ul>
            </div>

            <p>Si tienes dudas sobre el contrato o el sistema de incentivos, contacta con tu coordinador.</p>

            <p>¡Muchas gracias!</p>

            <p>Saludos,<br><strong>Equipo AsesorFy</strong></p>
        </div>

        <div class="footer">
            <p>Este es un email automático. Por favor, no respondas a este mensaje.</p>
            <p>AsesorFy | www.asesorfy.net</p>
        </div>
    </div>
</body>
</html>
