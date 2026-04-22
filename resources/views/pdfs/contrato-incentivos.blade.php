<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Contrato de Incentivos - {{ $comercial->name }}</title>
    <style>
        @page {
            margin: 120px 50px 100px 50px;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
        }

        header {
            position: fixed;
            top: -100px;
            left: 0;
            right: 0;
            height: 80px;
            text-align: center;
            border-bottom: 2px solid #0ea5e9;
            padding-bottom: 10px;
        }

        footer {
            position: fixed;
            bottom: -80px;
            left: 0;
            right: 0;
            height: 60px;
            font-size: 8pt;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }

        .page-number:before {
            content: counter(page);
        }

        h1 {
            color: #0ea5e9;
            font-size: 18pt;
            margin-bottom: 5px;
            text-align: center;
        }

        h2 {
            color: #0ea5e9;
            font-size: 14pt;
            margin-top: 20px;
            margin-bottom: 10px;
            border-bottom: 1px solid #0ea5e9;
            padding-bottom: 5px;
        }

        h3 {
            color: #333;
            font-size: 11pt;
            margin-top: 15px;
            margin-bottom: 8px;
        }

        .datos-comercial {
            background-color: #f0f9ff;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }

        .datos-comercial p {
            margin: 5px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        table th {
            background-color: #0ea5e9;
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-size: 9pt;
        }

        table td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9pt;
        }

        table tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .badge-obligatoria {
            background-color: #dc2626;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 7pt;
            font-weight: bold;
        }

        .alert {
            padding: 12px;
            border-left: 4px solid;
            margin: 15px 0;
            border-radius: 4px;
        }

        .alert-info {
            background-color: #f0f9ff;
            border-color: #0ea5e9;
        }

        .alert-warning {
            background-color: #fef3c7;
            border-color: #f59e0b;
        }

        .clausula {
            margin: 15px 0;
            text-align: justify;
        }

        .firma-section {
            margin-top: 40px;
            page-break-inside: avoid;
        }

        .firma-box {
            border: 1px solid #ddd;
            padding: 15px;
            margin: 20px 0;
            min-height: 100px;
        }

        .hash-box {
            background-color: #f3f4f6;
            padding: 10px;
            font-family: 'Courier New', monospace;
            font-size: 8pt;
            word-break: break-all;
            margin-top: 20px;
        }

        ul {
            margin: 10px 0;
            padding-left: 20px;
        }

        li {
            margin: 5px 0;
        }

        strong {
            color: #0ea5e9;
        }
    </style>
</head>
<body>
    <header>
        <h1>CONTRATO DE INCENTIVOS COMERCIALES</h1>
        <p style="margin: 0; font-size: 9pt; color: #666;">
            @if($tipo === 'anexo')
                ANEXO AL CONTRATO BASE
            @else
                CONTRATO BASE
            @endif
        </p>
    </header>

    <footer>
        <div style="text-align: center;">
            <p style="margin: 0;">
                <strong>{{ $empresaDatos['razon_social'] ?? 'AsesorFy S.L.' }}</strong> |
                CIF: {{ $empresaDatos['cif'] ?? 'B12345678' }} |
                {{ $empresaDatos['direccion'] ?? 'Dirección no configurada' }} |
                Tel: {{ $empresaDatos['telefono'] ?? '956 123 456' }} |
                {{ $empresaDatos['web'] ?? 'www.asesorfy.net' }}
            </p>
            <p style="margin: 5px 0 0 0;">
                Página <span class="page-number"></span> |
                Documento generado el {{ now()->format('d/m/Y H:i') }}
            </p>
        </div>
    </footer>

    <main>
        <!-- DATOS DEL COMERCIAL -->
        <div class="datos-comercial">
            <h3 style="margin-top: 0;">Datos del Comercial</h3>
            <p><strong>Nombre:</strong>
                {{ $comercial->trabajador->nombre ?? $comercial->name }}
                {{ $comercial->trabajador->apellidos ?? '' }}
            </p>
            <p><strong>Email:</strong> {{ $comercial->email }}</p>
            <p><strong>DNI:</strong> {{ $comercial->trabajador->dni_o_cif ?? 'No especificado' }}</p>
            <p><strong>Fecha de alta como comercial:</strong> {{ $comercial->fecha_inicio_comercial?->format('d/m/Y') ?? 'No especificado' }}</p>
            @if($tipo === 'anexo' && $contratoBase)
            <p><strong>Contrato base:</strong> Firmado el {{ $contratoBase->fecha_firma->format('d/m/Y') }}</p>
            @endif
        </div>

        @if($tipo === 'base')
        <!-- CONTRATO BASE -->
        <h2>1. OBJETO DEL CONTRATO</h2>
        <div class="clausula">
            El presente contrato tiene por objeto establecer las condiciones y reglas del sistema de incentivos
            por cumplimiento de objetivos comerciales aplicables al trabajador, en adelante el <strong>Comercial</strong>,
            en el marco de su relación laboral con <strong>AsesorFy</strong>.
        </div>

        <h2>2. REGLAS DE COMISIÓN ASIGNADAS</h2>
        <p>El Comercial tiene asignadas las siguientes reglas de comisión:</p>

        <table>
            <thead>
                <tr>
                    <th style="width: 35%;">Regla</th>
                    <th style="width: 20%;">Mínimo Mensual</th>
                    <th style="width: 15%;">Porcentaje</th>
                    <th style="width: 15%;">Penalización</th>
                    <th style="width: 15%;">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reglas as $regla)
                <tr>
                    <td>
                        {{ $regla['nombre'] }}
                        @if($regla['es_obligatoria'])
                        <span class="badge-obligatoria">⚠️ OBLIGATORIA</span>
                        @endif
                    </td>
                    <td>€{{ number_format($regla['minimo'], 2, ',', '.') }}</td>
                    <td>{{ $regla['porcentaje'] }}%</td>
                    <td>{{ $regla['penalizacion'] }} meses</td>
                    <td>{{ $regla['activa'] ? 'Activa' : 'Inactiva' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        @if($tieneObligatorias)
        <div class="alert alert-warning">
            <strong>⚠️ Importante:</strong> Las reglas marcadas como <strong>OBLIGATORIAS</strong> deben alcanzar
            el mínimo mensual establecido. En caso de no alcanzar el mínimo en una o más reglas obligatorias,
            se anularán las comisiones de <strong>TODAS</strong> las reglas para ese mes.
        </div>
        @endif

        <h2>3. CÁLCULO DE COMISIONES</h2>
        <div class="clausula">
            <h3>3.1. Base de Cálculo</h3>
            <p>Las comisiones se calcularán mensualmente sobre la facturación neta del Comercial, aplicando
            el porcentaje establecido en cada regla sobre la cantidad que exceda del mínimo mensual.</p>

            <p><strong>Fórmula:</strong> Comisión = (Facturación Neta - Mínimo) × Porcentaje</p>

            <h3>3.2. Penalización por Bajas Anticipadas</h3>
            <p>Las bajas de servicios recurrentes antes del periodo establecido en cada regla se descontarán
            de la facturación bruta del mes en que se produzca la baja.</p>

            <h3>3.3. Bonos Excepcionales</h3>
            <p>La empresa se reserva el derecho de otorgar bonos excepcionales por logros específicos,
            los cuales se sumarán al total de comisiones independientemente del cumplimiento de mínimos.</p>
        </div>

        <h2>4. PERIODO DE PRUEBA</h2>
        <div class="clausula">
            @if($comercial->fecha_inicio_comercial && $comercial->meses_prueba)
            <p>El Comercial tiene establecido un <strong>período de prueba de {{ $comercial->meses_prueba }} meses</strong>
            desde su fecha de alta ({{ $comercial->fecha_inicio_comercial->format('d/m/Y') }}).</p>

            <p>Durante este período:</p>
            <ul>
                <li>Los informes mensuales de comisiones son formativos</li>
                <li>No se aplican penalizaciones por no alcanzar mínimos</li>
                <li>Los meses en período de prueba no cuentan para el cálculo de despido por bajo rendimiento</li>
            </ul>
            @else
            <p>No aplica período de prueba.</p>
            @endif
        </div>

        <h2>5. CONDICIONES DE DESPIDO POR BAJO RENDIMIENTO</h2>
        <div class="clausula">
            <div class="alert alert-info">
                <strong>📋 Política de la empresa:</strong><br>
                No alcanzar los mínimos establecidos en las reglas obligatorias durante:
                <ul style="margin: 10px 0;">
                    <li><strong>{{ $config->meses_consecutivos_despido }} meses consecutivos</strong>, o</li>
                    <li><strong>{{ $config->meses_alternos_despido }} meses alternos en un período de {{ $config->periodo_meses_alternos }} meses</strong></li>
                </ul>
                puede resultar en:
                <ul style="margin: 10px 0;">
                    <li>Revisión de la situación laboral por parte del departamento de RRHH</li>
                    <li>Constitución de falta leve según convenio colectivo</li>
                    <li>Posible extinción de la relación laboral</li>
                </ul>
            </div>
        </div>

        <h2>6. ABONO DE INCENTIVOS</h2>
        <div class="clausula">
            <p>Los incentivos aprobados se abonarán <strong>en bruto</strong> en la nómina del mes siguiente
            al de su generación, estando sujetos a las retenciones fiscales y de Seguridad Social que correspondan
            según la situación del trabajador.</p>
        </div>

        <h2>7. MODIFICACIONES</h2>
        <div class="clausula">
            <p>Cualquier modificación en las reglas de comisión asignadas (adición, eliminación o cambio de parámetros)
            requerirá la firma de un <strong>ANEXO</strong> a este contrato base. El Comercial no podrá percibir
            comisiones sobre reglas no firmadas.</p>
        </div>

        <h2>8. ACEPTACIÓN Y FIRMA</h2>
        <div class="clausula">
            <p>El Comercial declara haber leído y comprendido todas las cláusulas de este contrato, y las acepta
            en su totalidad mediante su firma digital.</p>
        </div>

        @else
        <!-- CONTRATO ANEXO -->
        <h2>ANEXO AL CONTRATO BASE</h2>
        <div class="clausula">
            <p>El presente documento constituye un <strong>ANEXO</strong> al contrato base de incentivos firmado
            el {{ $contratoBase->fecha_firma->format('d/m/Y') }}.</p>

            <p>Este anexo modifica únicamente las reglas de comisión asignadas al Comercial. Las demás cláusulas
            del contrato base permanecen vigentes sin modificación.</p>
        </div>

        <h2>NUEVAS REGLAS DE COMISIÓN</h2>
        <p>A partir de la firma de este anexo, las reglas de comisión asignadas al Comercial son:</p>

        <table>
            <thead>
                <tr>
                    <th style="width: 35%;">Regla</th>
                    <th style="width: 20%;">Mínimo Mensual</th>
                    <th style="width: 15%;">Porcentaje</th>
                    <th style="width: 15%;">Penalización</th>
                    <th style="width: 15%;">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reglas as $regla)
                <tr>
                    <td>
                        {{ $regla['nombre'] }}
                        @if($regla['es_obligatoria'])
                        <span class="badge-obligatoria">⚠️ OBLIGATORIA</span>
                        @endif
                    </td>
                    <td>€{{ number_format($regla['minimo'], 2, ',', '.') }}</td>
                    <td>{{ $regla['porcentaje'] }}%</td>
                    <td>{{ $regla['penalizacion'] }} meses</td>
                    <td>{{ $regla['activa'] ? 'Activa' : 'Inactiva' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="alert alert-info">
            <strong>Validez:</strong> Este anexo entra en vigor a partir de su firma y sustituye
            cualquier asignación de reglas anterior.
        </div>
        @endif

        <!-- FIRMA -->
        <div class="firma-section">
            <h2>FIRMA DEL COMERCIAL</h2>

            @if($firmado)
            <div class="firma-box">
                <p><strong>Firmado digitalmente por:</strong> {{ $comercial->name }} {{ $comercial->trabajador->apellidos ?? '' }}</p>
                <p><strong>Email:</strong> {{ $comercial->email }}</p>
                <p><strong>Fecha y hora:</strong> {{ $fecha_firma->format('d/m/Y H:i:s') }}</p>
                <p><strong>IP:</strong> {{ $ip_firma }}</p>

                @if($firma_imagen)
                <div style="margin-top: 15px;">
                    <img src="{{ $firma_imagen }}" alt="Firma" style="max-width: 300px; height: auto; border: 1px solid #ddd;">
                </div>
                @endif
            </div>
            @else
            <div class="firma-box">
                <p style="text-align: center; color: #999; padding: 40px 0;">
                    [Firma Digital Pendiente]
                </p>
            </div>
            @endif

            <!-- HASH -->
            <div class="hash-box">
                <p style="margin: 0 0 5px 0; font-weight: bold;">HASH SHA-256 DEL DOCUMENTO:</p>
                <p style="margin: 0;">{{ $hash }}</p>
                <p style="margin: 10px 0 0 0; font-size: 7pt; color: #666;">
                    Este hash garantiza la integridad del documento. Cualquier modificación invalidará esta firma.
                </p>
            </div>
        </div>
    </main>
</body>
</html>
