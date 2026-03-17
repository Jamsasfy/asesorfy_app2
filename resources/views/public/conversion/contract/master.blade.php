<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Contrato de Servicios</title>

    <style>

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1f2937;
            line-height: 1.55;
        }

        h1 {
            font-size: 18px;
            text-align: left;
            margin-bottom: 18px;
            color: #0f172a;
            text-transform: uppercase;
        }

        h2 {
            font-size: 15px;
            margin-top: 0;
            color: #30D5C8;
            text-align: left;
            font-weight: bold;
        }

        h3 {
            font-size: 12px;
            font-weight: bold;
            color: #4b5563;
            margin-top: 18px;
            margin-bottom: 8px;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3px;
        }

        p, li {
            font-size: 11px;
            text-align: justify;
            margin-bottom: 10px;
        }

        ul, ol {
            padding-left: 20px;
            margin-bottom: 10px;
        }

        .page-break { page-break-after: always; }

        .logo { height: 40px; margin-bottom: 18px; }

        .service-table {
            width: 100%;
            border-collapse: collapse;
            margin: 14px 0;
        }

        .service-table th {
            font-size: 11px;
            font-weight: bold;
            text-align: left;
            padding: 6px 3px;
            color: #4b5563;
            border-bottom: 1px solid #d1d5db;
        }

        .service-table td {
            padding: 6px 3px;
            font-size: 11px;
            border-bottom: 1px dashed #e5e7eb;
        }

        .service-table tr:last-child td { border-bottom: none; }

        .signature-block {
            margin-top: 16px;
            padding: 8px;
            border: 1px dashed #cbd5e1;
            background: #f8fafc;
            text-align: center;
            font-size: 10px;
            color: #475569;
        }

        .client-sign img {
            max-width: 220px;
            max-height: 80px;
            display: block;
        }

        @page { margin: 22mm 18mm 25mm 18mm; }
        footer {
            position: fixed;
            bottom: -20px;
            left: 0;
            right: 0;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
            font-size: 8px;
            color: #94a3b8;
            text-align: center;
        }
        .page-number:before {
            content: "Pagina " counter(page);
        }
    </style>
</head>

<body>
    <footer>
        <div style="text-align:center; margin-bottom:3px;">
            Firmado digitalmente el {{ $signedAt->format('d/m/Y H:i') }}
            @if(isset($hashFirma) && $hashFirma)
                — SHA256: <span style="font-family:monospace;">{{ $hashFirma }}</span>
            @endif
        </div>
        <div style="text-align:center;">
            <span class="page-number"></span>
        </div>
    </footer>

@php
    $servicesSummary = $servicesSummary ?? (data_get($blueprint ?? [], 'servicios', []) ?? []);

    $toBool = static function ($v): bool {
        if (is_bool($v)) return $v;
        if ($v === null || $v === '') return false;
        $parsed = filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed ?? (bool) $v;
    };

    // ✅ Bloqueo global (misma lógica que contract / procesarTextosLegales)
    $bloqueoRecurrente = collect($servicesSummary)->contains(function ($s) use ($toBool) {
        if (($s['tipo'] ?? '') !== 'unico') return false;

        $esEditable = $toBool($s['es_editable'] ?? false);
        return $esEditable
            ? $toBool($s['requiere_proyecto'] ?? false)
            : $toBool($s['servicio_requiere_proyecto'] ?? false);
    });
@endphp

    <img src="{{ public_path('images/logo.png') }}" class="logo" alt="AsesorFy">

    {!! $textos['contrato_cabecera'] ?? '' !!}

    <div class="page-break"></div>

    {!! $textos['contrato_marco_legal'] ?? '' !!}

    <div class="page-break"></div>

    @if(!empty($textos['servicio_recurrentes']))
        <h2>Servicios Recurrentes</h2>
        {!! $textos['servicio_recurrentes'] !!}
    @endif

    @if(!empty($textos['servicio_unicos']))
        <h2>Servicios de Pago Único</h2>
        {!! $textos['servicio_unicos'] !!}
    @endif

    <div class="page-break"></div>

    {!! $textos['contrato_condiciones_grales'] ?? '' !!}

    <div class="page-break"></div>

    {!! $textos['anexo_economico'] ?? '' !!}
    <br>
    {!! $textos['anexo_rgpd_ia'] ?? '' !!}

    <div class="page-break"></div>

    <h2>Firma del Contrato</h2>
    <p>
        En conformidad con lo expuesto en el presente contrato, ambas partes firman digitalmente
        el documento, otorgándole plena validez jurídica.
    </p>

    <table style="width:100%; margin-top: 35px;">
        <tr>
            <td style="width:50%; vertical-align:top; padding-right: 25px;">
                <h3>AsesorFy</h3>
                <div class="signature-block">
                    Firmado electrónicamente por AsesorFy<br>
                    {{ $signedAt->format('d/m/Y H:i') }}
                </div>
            </td>

            <td style="width:50%; vertical-align:top; padding-left: 25px;">
                <h3>El Cliente</h3>
                {{ $form['nombre'] ?? '' }} {{ $form['apellidos'] ?? '' }}<br>
                DNI/CIF: {{ $form['dni'] ?? '—' }}

                <div class="client-sign" style="margin-top: 15px;">
                @if(isset($signatureDataUri))
                    <img src="{{ $signatureDataUri }}">
                    <small style="font-size:9px; color:#475569;">
                        Firmado digitalmente el {{ $signedAt->format('d/m/Y H:i') }}<br>
                        IP: {{ $clientIp ?? 'No registrada' }}
                    </small>
                @else
                    <div style="height:80px; border-bottom:1px solid #ccc;"></div>
                @endif
                </div>
            </td>
        </tr>
    </table>

    @if(isset($hashFirma) && $hashFirma)
    <div style="margin-top:30px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; padding:12px 14px; font-size:9px; color:#555;">
        <strong>[SHA256] Verificacion de integridad del documento</strong>
        <div style="font-family:monospace; font-size:9px; color:#1a1a1a; word-break:break-all; margin-top:4px;">SHA256: {{ $hashFirma }}</div>
        <div style="margin-top:8px; font-size:8.5px; color:#777; line-height:1.5;">
            El código hash SHA-256 que figura en este documento es una huella digital única generada a partir del contenido íntegro del PDF en el momento de su firma. Cualquier alteración posterior del documento, por mínima que sea, produciría un hash completamente diferente, lo que permitiría detectar cualquier manipulación.
            Este mecanismo de verificación está reconocido como medio de prueba de integridad documental conforme al Reglamento (UE) 910/2014 del Parlamento Europeo (eIDAS), la Ley 6/2020 reguladora de determinados aspectos de los servicios electrónicos de confianza, y es admisible como prueba en procedimientos judiciales y administrativos de acuerdo con la Ley 1/2000 de Enjuiciamiento Civil.
            IP de firma: {{ $clientIp ?? 'No registrada' }} — Email: {{ $form['email'] ?? '—' }} — Fecha: {{ $signedAt->format('d/m/Y H:i') }}
        </div>
    </div>
    @endif

</body>
</html>
