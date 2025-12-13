<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Contrato de Servicios</title>

    <!-- Ajustes generales -->
    <style>
        @page { margin: 22mm 18mm; }

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
            color: #30D5C8; /* turquesa corporativo */
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

        .page-break {
            page-break-after: always;
        }

        /* Logo */
        .logo {
            height: 40px;
            margin-bottom: 18px;
        }

        /* Tabla servicios minimalista */
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

        .service-table tr:last-child td {
            border-bottom: none;
        }

        .signature-box {
            margin-top: 40px;
        }

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
    </style>
</head>

<body>

    <!-- LOGO ASESORFY -->
    <img src="{{ public_path('images/logo.png') }}" class="logo" alt="AsesorFy">

    <!-- BLOQUE 1: CABECERA -->
    <h1>Contrato de Prestación de Servicios</h1>

    {!! $textos['contrato_cabecera'] ?? '' !!}

    <div class="page-break"></div>

    <!-- BLOQUE 2: MARCO LEGAL -->
    {!! $textos['contrato_marco_legal'] ?? '' !!}

    <div class="page-break"></div>

    <!-- BLOQUE 3: SERVICIOS (dinámicos) -->
    @if(!empty($textos['servicio_recurrentes']))
        <h2>Servicios Recurrentes</h2>
        {!! $textos['servicio_recurrentes'] !!}
    @endif

    @if(!empty($textos['servicio_unicos']))
        <h2>Servicios de Pago Único</h2>
        {!! $textos['servicio_unicos'] !!}
    @endif

    <div class="page-break"></div>

    <!-- BLOQUE 4: CONDICIONES GENERALES -->
    {!! $textos['contrato_condiciones_grales'] ?? '' !!}

    <div class="page-break"></div>

    <!-- BLOQUE 5: ANEXOS -->
    {!! $textos['anexo_economico'] ?? '' !!}
    <br>
    {!! $textos['anexo_rgpd_ia'] ?? '' !!}

    <div class="page-break"></div>

    <!-- BLOQUE 6: FIRMAS -->
    <h2>Firma del Contrato</h2>
    <p>
        En conformidad con lo expuesto en el presente contrato, ambas partes firman digitalmente
        el documento, otorgándole plena validez jurídica.
    </p>

    <table style="width:100%; margin-top: 35px;">
        <tr>
            <!-- Firma AsesorFy -->
            <td style="width:50%; vertical-align:top; padding-right: 25px;">
                <h3>AsesorFy</h3>
                <div class="signature-block">
                    Firmado electrónicamente por AsesorFy<br>
                    {{ $signedAt->format('d/m/Y H:i') }}
                </div>
            </td>

            <!-- Firma cliente -->
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

</body>
</html>
