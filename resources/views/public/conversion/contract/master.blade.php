<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Contrato de Servicios</title>
    <style>
        @page { margin: 25mm 20mm; }
        body { 
            font-family: 'Helvetica', sans-serif; 
            font-size: 11px; 
            color: #1f2937; 
            line-height: 1.5; 
        }
        
        /* Estilos para el contenido que viene del RichEditor */
        h1 { font-size: 18px; text-transform: uppercase; color: #0f172a; margin-bottom: 20px; }
        h2 { font-size: 16px; color: #0ea5e9; margin-top: 0; }
        h3 { 
            font-size: 12px; 
            font-weight: bold; 
            color: #334155; 
            margin-top: 20px; 
            margin-bottom: 10px; 
            text-transform: uppercase; 
            border-bottom: 1px solid #e2e8f0; 
            padding-bottom: 4px;
        }
        p { margin-bottom: 10px; text-align: justify; }
        ul, ol { margin-bottom: 10px; padding-left: 20px; }
        li { margin-bottom: 4px; text-align: justify; }
        strong { font-weight: bold; color: #000; }
        
        /* Utilidades */
        .page-break { page-break-after: always; }
        .logo { height: 45px; margin-bottom: 20px; }
        .signature-box { margin-top: 40px; border-top: 1px solid #cbd5e1; pt: 10px; }
        
        /* Estilos para la tabla de servicios inyectada */
        table.services-table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 11px; }
        table.services-table th { background: #f1f5f9; padding: 8px; text-align: left; color: #475569; font-weight: bold; }
        table.services-table td { border-bottom: 1px solid #e2e8f0; padding: 8px; vertical-align: top; }
        table.services-table tr:last-child td { border-bottom: 0; }
        .total-row td { background: #f8fafc; font-weight: bold; color: #0f172a; border-top: 2px solid #e2e8f0; }
    </style>
</head>
<body>

    {{-- LOGO --}}
    <img src="{{ public_path('images/logo.png') }}" class="logo" alt="AsesorFy">

    {{-- 1. CABECERA Y OBJETO --}}
    <div class="section">
        {!! $textos['contrato_cabecera'] ?? '' !!}
    </div>

    {{-- 2. MARCO LEGAL --}}
    <div class="section">
        {!! $textos['contrato_marco_legal'] ?? '' !!}
    </div>

    <div class="page-break"></div>

    {{-- 3. ANEXOS DE SERVICIOS (Dinámicos) --}}
    {{-- Solo se imprimen si existen en el array (el controlador decide si enviarlos) --}}
    
    @if(!empty($textos['servicio_recurrentes']))
        <div class="section">
            {!! $textos['servicio_recurrentes'] !!}
        </div>
        <br>
    @endif

    @if(!empty($textos['servicio_unicos']))
        <div class="section">
            {!! $textos['servicio_unicos'] !!}
        </div>
        <br>
    @endif

    <div class="page-break"></div>

    {{-- 4. CONDICIONES GENERALES --}}
    <div class="section">
        {!! $textos['contrato_condiciones_grales'] ?? '' !!}
    </div>

    <div class="page-break"></div>

    {{-- 5. ANEXOS ECONÓMICO Y RGPD --}}
    <div class="section">
        {!! $textos['anexo_economico'] ?? '' !!}
    </div>
    
    <br>
    
    <div class="section">
        {!! $textos['anexo_rgpd_ia'] ?? '' !!}
    </div>

    <div class="page-break"></div>

   {{-- 6. FIRMAS --}}
    <div style="margin-top: 50px;">
        <h3>ACEPTACIÓN Y FIRMA</h3>
        <p>Y en prueba de conformidad con todo lo expuesto, ambas partes firman el presente contrato en un único efecto jurídico.</p>
        
        <table style="width: 100%; margin-top: 40px;">
            <tr>
                {{-- LADO ASESORFY --}}
               {{-- LADO ASESORFY --}}
                <td style="width: 50%; vertical-align: top; padding-right: 20px;">
                    {{-- Eliminados [AFY_RAZON] y La Dirección --}}
                    <br><br><br>
                    <div style="padding: 10px; border: 1px dashed #ccc; background: #f9f9f9; text-align: center; font-size: 10px; color: #666;">
                        FIRMADO ELECTRÓNICAMENTE<br>
                        POR ASESORFY<br>
                        {{-- Usamos $signedAt para que coincida fecha y hora de la firma --}}
                        {{ $signedAt->format('d/m/Y H:i') }}
                    </div>
                </td>
                
                {{-- LADO CLIENTE --}}
                <td style="width: 50%; vertical-align: top; padding-left: 20px;">
                    <strong>EL CLIENTE</strong><br>
                    {{ $form['nombre'] ?? '' }} {{ $form['apellidos'] ?? '' }}<br>
                    DNI/CIF: {{ $form['dni'] ?? '—' }}
                    <br><br>
                    
                    @if(isset($signatureDataUri))
                        <div style="border-bottom: 1px solid #000; display: inline-block;">
                            <img src="{{ $signatureDataUri }}" style="max-width: 200px; max-height: 80px;">
                        </div>
                        <br>
                        <small style="color: #666; font-size: 9px;">
                            Firmado digitalmente el {{ $signedAt->format('d/m/Y H:i') }}<br>
                            <strong>IP: {{ $clientIp ?? 'No registrada' }}</strong>
                        </small>
                    @else
                        <div style="height: 80px; border-bottom: 1px solid #ccc; background: #f9f9f9;"></div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

</body>
</html>