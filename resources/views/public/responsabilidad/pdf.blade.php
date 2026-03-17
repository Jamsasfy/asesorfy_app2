<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Documento de Exoneración — {{ $contrato->titulo }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1a1a1a;
            font-size: 11px;
            line-height: 1.7;
            margin: 40px 50px 80px 50px;
        }
        /* Footer en todas las páginas */
        @page {
            margin: 40px 50px 80px 50px;
        }
        footer {
            position: fixed;
            bottom: -60px;
            left: 0;
            right: 0;
            border-top: 1px solid #ccc;
            padding-top: 6px;
            font-size: 8px;
            color: #888;
            text-align: center;
        }
        .page-number:before {
            content: "Página " counter(page);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #1a1a1a;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .header h1 {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 4px 0;
        }
        .header p {
            font-size: 10px;
            color: #555;
            margin: 0;
        }
        h2 {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4px;
            margin-top: 20px;
            margin-bottom: 8px;
            letter-spacing: 0.3px;
        }
        p { margin: 0 0 8px 0; }
        .destacado {
            background: #f5f5f5;
            border-left: 3px solid #333;
            padding: 10px 14px;
            margin: 12px 0;
            border-radius: 4px;
        }
        .destacado-titulo {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 4px;
        }
        ul {
            margin: 6px 0 10px 20px;
            padding: 0;
        }
        ul li { margin-bottom: 3px; }
        .firmas {
            margin-top: 50px;
            width: 100%;
            border-collapse: collapse;
        }
        .firmas td {
            width: 50%;
            vertical-align: top;
            padding: 0 20px 0 0;
        }
        .firmas td:last-child { padding-right: 0; }
        .firma-linea {
            border-top: 1px solid #333;
            margin-top: 60px;
            padding-top: 6px;
            font-size: 10px;
            color: #555;
        }
        .firma-img { max-width: 180px; max-height: 70px; margin-top: 10px; }
        .hash-box {
            margin-top: 30px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 12px 14px;
            font-size: 9px;
            color: #555;
        }
        .hash-box .hash-valor {
            font-family: monospace;
            font-size: 9px;
            color: #1a1a1a;
            word-break: break-all;
            margin-top: 4px;
        }
        .hash-box .hash-legal {
            margin-top: 8px;
            font-size: 8.5px;
            color: #777;
            line-height: 1.5;
        }
    </style>
</head>
<body>

    {{-- Footer fijo en todas las páginas --}}
    <footer>
        <div style="text-align:center; margin-bottom:3px;">
            Firmado digitalmente el {{ $signedAt->format('d/m/Y H:i') }}
            @if($hashFirma) — SHA256: <span style="font-family:monospace;">{{ $hashFirma }}</span>@endif
        </div>
        <div style="text-align:center;">
            <span class="page-number"></span>
        </div>
    </footer>
    <div class="header">
        <h1>Documento de Instrucción Expresa, Asunción de Riesgos y Exoneración de Responsabilidad</h1>
        <p>{{ config('app.name') }} — {{ $signedAt->format('d/m/Y H:i') }}</p>
    </div>

    <h2>1. Partes</h2>
    <p>
        De una parte, <strong>{{ config('app.name') }}</strong>, en adelante, <strong>LA ASESORÍA</strong>.
    </p>
    <p>
        Y de otra parte,
        <strong>{{ trim(($contrato->cliente->nombre ?? '') . ' ' . ($contrato->cliente->apellidos ?? '')) ?: $contrato->cliente->razon_social }}</strong>,
        con DNI/NIF/CIF <strong>{{ $contrato->cliente->dni_cif ?? '—' }}</strong>,
        en adelante, <strong>EL CLIENTE</strong>.
    </p>
    <p>Ambas partes, reconociéndose capacidad legal suficiente para obligarse, suscriben el presente Documento de Instrucción Expresa, Asunción de Riesgos y Exoneración de Responsabilidad.</p>

    <h2>2. Objeto del Documento</h2>
    <p>
        El presente documento tiene por objeto dejar constancia de que <strong>LA ASESORÍA</strong> ha informado
        expresa, clara y suficientemente a <strong>EL CLIENTE</strong> de que la actuación, instrucción, solicitud
        o criterio que éste pretende seguir no resulta jurídicamente recomendable, puede no ajustarse al criterio
        técnico de <strong>LA ASESORÍA</strong> o puede implicar riesgos fiscales, contables, laborales,
        administrativos, sancionadores o de cualquier otra naturaleza jurídica.
    </p>
    <p>
        Pese a ello, <strong>EL CLIENTE</strong> manifiesta de forma libre, consciente, expresa e inequívoca
        su voluntad de mantener dicha instrucción, solicitando a <strong>LA ASESORÍA</strong> que actúe conforme
        a lo indicado por <strong>EL CLIENTE</strong>, bajo su exclusiva responsabilidad.
    </p>

    <h2>3. Actuación o Instrucción Expresa del Cliente</h2>
    <div class="destacado">
        <div class="destacado-titulo">{{ $contrato->titulo }}</div>
        <div>{{ $contrato->descripcion }}</div>
    </div>
    <p>A efectos de este documento, la actuación anteriormente descrita será denominada en adelante, <strong>la ACTUACIÓN</strong>.</p>

    <h2>4. Advertencia Previa e Información al Cliente</h2>
    <p>
        <strong>LA ASESORÍA</strong> declara, y <strong>EL CLIENTE</strong> reconoce expresamente, que antes
        de la firma del presente documento se le ha informado de forma suficiente, comprensible y directa de
        que la ACTUACIÓN puede conllevar, entre otras, alguna o varias de las siguientes consecuencias:
    </p>
    <ul>
        <li>Requerimientos de la Administración.</li>
        <li>Regularizaciones tributarias o administrativas.</li>
        <li>Denegaciones.</li>
        <li>Pérdidas de derechos, beneficios o incentivos.</li>
        <li>Recargos, intereses o sanciones.</li>
        <li>Responsabilidades frente a terceros.</li>
        <li>Nulidad, ineficacia o impugnación de actuaciones.</li>
        <li>Cualesquiera otras consecuencias jurídicas, económicas o administrativas desfavorables.</li>
    </ul>
    <p>
        <strong>EL CLIENTE</strong> declara que ha comprendido íntegramente dichas advertencias, que ha tenido
        ocasión de formular preguntas y que ha recibido una explicación suficiente sobre los riesgos asociados.
    </p>

    <h2>5. Instrucción Expresa del Cliente</h2>
    <p>
        <strong>EL CLIENTE</strong> manifiesta expresamente que, aun habiendo sido advertido por <strong>LA ASESORÍA</strong>
        de los riesgos, inconvenientes o posible improcedencia de la ACTUACIÓN, mantiene su decisión y ordena
        expresamente que se proceda conforme a su criterio.
    </p>

    <h2>6. Asunción Íntegra de Riesgos por el Cliente</h2>
    <p>
        <strong>EL CLIENTE</strong> acepta y asume de forma expresa, plena e irrevocable que todos los riesgos
        derivados de la ACTUACIÓN son asumidos exclusivamente por él, incluyendo los riesgos fiscales, laborales,
        contables, mercantiles, administrativos, civiles, sancionadores o de cualquier otra índole.
    </p>

    <h2>7. Exoneración Expresa de Responsabilidad</h2>
    <p>
        <strong>EL CLIENTE</strong> exonera expresa, total y completamente a <strong>LA ASESORÍA</strong>,
        así como a sus administradores, socios, empleados, asesores, colaboradores y representantes, de toda
        responsabilidad derivada directa o indirectamente de la ACTUACIÓN descrita en el presente documento.
    </p>

    <h2>8. Ausencia de Garantía y Reserva de Criterio Profesional</h2>
    <p>
        La firma del presente documento no supone en ningún caso que <strong>LA ASESORÍA</strong> comparta,
        valide o recomiende la ACTUACIÓN, ni que garantice un resultado favorable, ni que asuma responsabilidad
        alguna por la viabilidad, legalidad material o consecuencias futuras de la ACTUACIÓN.
    </p>

    <h2>9. Indemnidad</h2>
    <p>
        <strong>EL CLIENTE</strong> se obliga a mantener plenamente indemne a <strong>LA ASESORÍA</strong>
        frente a cualquier daño, perjuicio, coste, reclamación, gasto, sanción, recargo, defensa jurídica o
        responsabilidad que pudiera derivarse de la ACTUACIÓN.
    </p>

    <h2>10. Prevalencia Documental</h2>
    <p>
        El presente documento complementa el contrato principal o relación profesional existente entre las partes
        y, respecto de la ACTUACIÓN aquí descrita, prevalecerá sobre cualquier manifestación verbal previa o simultánea.
    </p>

    <h2>11. Validez y Firma</h2>
    <p>
        <strong>EL CLIENTE</strong> declara que firma el presente documento de forma libre, consciente e informada,
        sin error, violencia, intimidación o dolo.
    </p>
    <p>
        En prueba de conformidad, las partes firman el presente documento en
        {{ $contrato->cliente->localidad ?? '—' }}, a {{ $signedAt->locale('es')->isoFormat('D [de] MMMM [de] YYYY') }}.
    </p>

    <table class="firmas">
        <tr>
            <td>
                <strong>{{ config('app.name') }}</strong>
                <div class="firma-linea">
                    Firmado electrónicamente por {{ config('app.name') }}<br>
                    {{ $signedAt->format('d/m/Y H:i') }}
                </div>
            </td>
            <td>
                <strong>EL CLIENTE</strong><br>
                {{ trim(($contrato->cliente->nombre ?? '') . ' ' . ($contrato->cliente->apellidos ?? '')) ?: $contrato->cliente->razon_social }}<br>
                DNI/NIF/CIF: {{ $contrato->cliente->dni_cif ?? '—' }}
                <div style="margin-top:10px;">
                    @if(isset($signatureDataUri))
                        <img src="{{ $signatureDataUri }}" class="firma-img" alt="Firma cliente">
                        <div style="font-size:9px; color:#475569; margin-top:4px;">
                            Firmado digitalmente el {{ $signedAt->format('d/m/Y H:i') }}<br>
                            IP: {{ $clientIp }}
                        </div>
                    @else
                        <div style="height:60px; border-bottom:1px solid #ccc;"></div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- Hash y validez legal --}}
    @if($hashFirma)
    <div class="hash-box">
        <strong>[SHA256] Verificación de integridad del documento</strong>
        <div class="hash-valor">SHA256: {{ $hashFirma }}</div>
        <div class="hash-legal">
            El código hash SHA-256 que figura en este documento es una huella digital única generada a partir del contenido íntegro del PDF en el momento de su firma. Cualquier alteración posterior del documento, por mínima que sea, produciría un hash completamente diferente, lo que permitiría detectar cualquier manipulación.
            Este mecanismo de verificación está reconocido como medio de prueba de integridad documental conforme al Reglamento (UE) 910/2014 del Parlamento Europeo (eIDAS), la Ley 6/2020 reguladora de determinados aspectos de los servicios electrónicos de confianza, y es admisible como prueba en procedimientos judiciales y administrativos de acuerdo con la Ley 1/2000 de Enjuiciamiento Civil.
            IP de firma: {{ $clientIp }} — Email: {{ $contrato->cliente->email_contacto }} — Fecha: {{ $signedAt->format('d/m/Y H:i') }}
        </div>
    </div>
    @endif

</body>
</html>