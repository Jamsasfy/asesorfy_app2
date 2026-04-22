<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe Mensual de Comisiones</title>
    <style>
        @page {
            margin: 100px 50px 80px 50px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10pt;
            color: #1f2937;
            line-height: 1.4;
        }

        header {
            position: fixed;
            top: -100px;
            left: 0;
            right: 0;
            height: 80px;
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            padding: 20px 50px;
            color: white;
        }

        header h1 {
            font-size: 18pt;
            margin-bottom: 5px;
        }

        header p {
            font-size: 11pt;
            opacity: 0.9;
        }

        footer {
            position: fixed;
            bottom: -80px;
            left: 0;
            right: 0;
            height: 60px;
            background: #f3f4f6;
            border-top: 2px solid #0ea5e9;
            padding: 15px 50px;
            font-size: 8pt;
            color: #6b7280;
            text-align: center;
        }

        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 14pt;
            font-weight: bold;
            color: #0ea5e9;
            margin-bottom: 12px;
            padding-bottom: 5px;
            border-bottom: 2px solid #0ea5e9;
        }

        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .info-row {
            display: table-row;
        }

        .info-label {
            display: table-cell;
            width: 35%;
            font-weight: bold;
            padding: 6px 0;
            color: #4b5563;
        }

        .info-value {
            display: table-cell;
            padding: 6px 0;
            color: #1f2937;
        }

        .result-box {
            background: #f0f9ff;
            border: 2px solid #0ea5e9;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            margin: 20px 0;
        }

        .result-box.success {
            background: #f0fdf4;
            border-color: #22c55e;
        }

        .result-box.warning {
            background: #fef3c7;
            border-color: #f59e0b;
        }

        .result-icon {
            font-size: 32pt;
            margin-bottom: 10px;
        }

        .result-title {
            font-size: 16pt;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .result-message {
            font-size: 11pt;
            color: #4b5563;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        table thead {
            background: #f3f4f6;
        }

        table th {
            padding: 10px 8px;
            text-align: left;
            font-weight: bold;
            color: #374151;
            border-bottom: 2px solid #0ea5e9;
            font-size: 9pt;
        }

        table td {
            padding: 8px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 9pt;
        }

        table tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 8pt;
            font-weight: bold;
        }

        .badge-success {
            background: #dcfce7;
            color: #166534;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .total-row {
            font-weight: bold;
            background: #f9fafb;
            font-size: 10pt;
        }

        .hash-section {
            margin-top: 30px;
            padding: 15px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
        }

        .hash-label {
            font-size: 8pt;
            color: #6b7280;
            margin-bottom: 5px;
        }

        .hash-value {
            font-family: 'Courier New', monospace;
            font-size: 7pt;
            color: #374151;
            word-break: break-all;
        }
    </style>
</head>
<body style="font-family: 'DejaVu Sans', sans-serif; margin: 20px; padding: 20px;">
    <header>
        <h1>INFORME MENSUAL DE COMISIONES</h1>
        <p>{{ $empresaDatos['razon_social'] }} - {{ $fecha->locale('es')->isoFormat('MMMM [de] YYYY') }}</p>
    </header>

    <footer>
        <strong>{{ $empresaDatos['razon_social'] }}</strong> |
        CIF: {{ $empresaDatos['cif'] }} |
        {{ $empresaDatos['direccion'] }} |
        Tel: {{ $empresaDatos['telefono'] }} |
        {{ $empresaDatos['web'] }}
    </footer>

    {{-- Datos del Comercial --}}
    <div class="section">
        <div class="section-title">INFORMACIÓN DEL COMERCIAL</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Nombre:</div>
                <div class="info-value">
                    {{ $comercial->trabajador->nombre ?? $comercial->name }}
                    {{ $comercial->trabajador->apellidos ?? '' }}
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">DNI:</div>
                <div class="info-value">{{ $comercial->trabajador->dni_o_cif ?? 'N/A' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Periodo:</div>
                <div class="info-value">{{ $fecha->locale('es')->isoFormat('MMMM [de] YYYY') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Fecha de aprobación:</div>
                <div class="info-value">{{ now()->format('d/m/Y H:i') }}</div>
            </div>
        </div>
    </div>

    {{-- Resultado --}}
    <div class="result-box {{ $superaMinimos ? 'success' : 'warning' }}">
        <div class="result-icon" style="font-size: 48pt; font-weight: bold; color: {{ $superaMinimos ? '#16a34a' : '#f59e0b' }};">
            {{ $superaMinimos ? '✓' : '!' }}
        </div>
        <div class="result-title">
            {{ $superaMinimos ? '¡ENHORABUENA!' : 'OBJETIVOS NO ALCANZADOS' }}
        </div>
        <div class="result-message">
            @if($superaMinimos)
                Has superado los objetivos establecidos para este mes.
                Tu esfuerzo y dedicación han dado excelentes resultados.
            @else
                No se han alcanzado los mínimos establecidos este mes.
                Revisa el detalle a continuación y sigue trabajando para el próximo periodo.
            @endif
        </div>

        @if($superaMinimos && $historial->total_comisiones_calculado > 0)
            <div class="result-message" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #bfdbfe; font-size: 10pt; color: #1e40af;">
                <strong>Información de pago:</strong> De acuerdo con el Contrato de Incentivos Comerciales firmado,
                las comisiones generadas se abonarán en la nómina del mes siguiente (a mes vencido),
                aplicándose las retenciones fiscales y deducciones de Seguridad Social correspondientes
                según la normativa vigente.
            </div>
        @endif
    </div>

    {{-- Detalle Completo de Ventas --}}
    <div class="section">
        <div class="section-title">DETALLE COMPLETO DE VENTAS DEL MES</div>

        @if($ventas->count() > 0)
            <table>
                <thead>
                    <tr>
                        <th style="width: 25%;">Cliente</th>
                        <th style="width: 12%;">Fecha</th>
                        <th style="width: 40%;">Servicio</th>
                        <th style="width: 10%; text-align: center;">Tipo</th>
                        <th style="width: 13%; text-align: right;">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @php $totalVentas = 0; @endphp
                    @foreach($ventas as $venta)
                        @foreach($venta->items as $item)
                            @php $totalVentas += $item->precio_unitario; @endphp
                            <tr>
                                <td>{{ $venta->cliente->nombre_fiscal ?? $venta->cliente->razon_social }}</td>
                                <td>{{ \Carbon\Carbon::parse($venta->fecha_venta)->format('d/m/Y') }}</td>
                                <td>{{ $item->servicio->nombre }}</td>
                                <td style="text-align: center;">
                                    <span class="status-badge {{ $item->servicio->tipo === 'recurrente' ? 'badge-success' : 'badge-info' }}">
                                        {{ strtoupper($item->servicio->tipo->value) }}
                                    </span>
                                </td>
                                <td style="text-align: right;">€{{ number_format($item->precio_unitario, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                    <tr class="total-row">
                        <td colspan="4" style="text-align: right;">TOTAL FACTURADO:</td>
                        <td style="text-align: right;">€{{ number_format($totalVentas, 2, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        @else
            <p style="text-align: center; padding: 30px; color: #6b7280;">
                No se registraron ventas en este periodo.
            </p>
        @endif
    </div>

    {{-- Detalle por Regla de Comisión --}}
    @foreach($desgloseReglas as $desglose)
        <div class="section" style="page-break-inside: avoid;">
            <div class="section-title">
                REGLA: {{ $desglose['regla_nombre'] }}
            </div>

            @php
                $tipoRegla = \App\Models\ComisionRegla::find($desglose['regla_id'])->tipo_servicio ?? 'recurrente';
                $ventasRegla = collect();

                foreach($ventas as $venta) {
                    foreach($venta->items as $item) {
                        if ($item->servicio->tipo->value === $tipoRegla) {
                            $ventasRegla->push([
                                'cliente'  => $venta->cliente->nombre_fiscal ?? $venta->cliente->razon_social,
                                'fecha'    => $venta->fecha_venta,
                                'servicio' => $item->servicio->nombre,
                                'importe'  => $item->precio_unitario,
                            ]);
                        }
                    }
                }

                $supera    = $desglose['facturacion_neta'] >= $desglose['minimo_requerido'];
                $diferencia = $desglose['facturacion_neta'] - $desglose['minimo_requerido'];
            @endphp

            @if($ventasRegla->count() > 0)
                <table>
                    <thead>
                        <tr>
                            <th style="width: 30%;">Cliente</th>
                            <th style="width: 15%;">Fecha</th>
                            <th style="width: 40%;">Servicio</th>
                            <th style="width: 15%; text-align: right;">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ventasRegla as $venta)
                            <tr>
                                <td>{{ $venta['cliente'] }}</td>
                                <td>{{ \Carbon\Carbon::parse($venta['fecha'])->format('d/m/Y') }}</td>
                                <td>{{ $venta['servicio'] }}</td>
                                <td style="text-align: right;">€{{ number_format($venta['importe'], 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p style="text-align: center; padding: 20px; color: #6b7280; font-style: italic;">
                    No hubo ventas aplicables a esta regla en el periodo.
                </p>
            @endif

            {{-- Resumen de la Regla --}}
            <div style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 15px; margin-top: 15px;">
                <table style="margin: 0;">
                    <tr>
                        <td style="border: none; padding: 5px 0; width: 60%;"><strong>Mínimo requerido:</strong></td>
                        <td style="border: none; padding: 5px 0; text-align: right;">€{{ number_format($desglose['minimo_requerido'], 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 5px 0;"><strong>Facturación alcanzada:</strong></td>
                        <td style="border: none; padding: 5px 0; text-align: right;">€{{ number_format($desglose['facturacion_neta'], 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td style="border: none; padding: 5px 0;"><strong>Diferencia:</strong></td>
                        <td style="border: none; padding: 5px 0; text-align: right; color: {{ $supera ? '#16a34a' : '#dc2626' }}; font-weight: bold;">
                            {{ $supera ? '+' : '' }}€{{ number_format($diferencia, 2, ',', '.') }}
                        </td>
                    </tr>
                    @if($supera)
                        <tr>
                            <td style="border: none; padding: 5px 0;"><strong>Porcentaje de comisión:</strong></td>
                            <td style="border: none; padding: 5px 0; text-align: right;">{{ number_format($desglose['porcentaje_comision'], 2) }}%</td>
                        </tr>
                        <tr style="background: #dcfce7;">
                            <td style="border: none; padding: 8px 0;"><strong>COMISIÓN GENERADA:</strong></td>
                            <td style="border: none; padding: 8px 0; text-align: right; font-size: 12pt; font-weight: bold; color: #16a34a;">
                                €{{ number_format($desglose['comision'], 2, ',', '.') }}
                            </td>
                        </tr>
                    @else
                        <tr style="background: #fee2e2;">
                            <td style="border: none; padding: 8px 0;"><strong>NO ALCANZADO</strong></td>
                            <td style="border: none; padding: 8px 0; text-align: right; font-weight: bold; color: #dc2626;">
                                Faltaron €{{ number_format(abs($diferencia), 2, ',', '.') }}
                            </td>
                        </tr>
                    @endif
                </table>
            </div>
        </div>
    @endforeach

    {{-- Resumen Final de Comisiones --}}
    <div class="section">
        <div class="section-title">RESUMEN FINAL DE COMISIONES</div>

        <table>
            <thead>
                <tr>
                    <th style="width: 50%;">Regla</th>
                    <th style="width: 20%; text-align: right;">Facturado</th>
                    <th style="width: 15%; text-align: center;">Estado</th>
                    <th style="width: 15%; text-align: right;">Comisión</th>
                </tr>
            </thead>
            <tbody>
                @foreach($desgloseReglas as $desglose)
                    @php $supera = $desglose['facturacion_neta'] >= $desglose['minimo_requerido']; @endphp
                    <tr>
                        <td><strong>{{ $desglose['regla_nombre'] }}</strong></td>
                        <td style="text-align: right;">€{{ number_format($desglose['facturacion_neta'], 2, ',', '.') }}</td>
                        <td style="text-align: center;">
                            <span class="status-badge {{ $supera ? 'badge-success' : 'badge-danger' }}" style="font-weight: bold; font-size: 11pt;">
                                {{ $supera ? '✓' : '✗' }}
                            </span>
                        </td>
                        <td style="text-align: right;">€{{ number_format($desglose['comision'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3" style="text-align: right;">SUBTOTAL COMISIONES:</td>
                    <td style="text-align: right;">€{{ number_format($historial->total_comisiones_calculado, 2, ',', '.') }}</td>
                </tr>
                @if($historial->total_bonos > 0)
                    <tr>
                        <td colspan="3" style="text-align: right; padding: 8px; border-bottom: 1px solid #e5e7eb;">
                            <strong>BONOS ADICIONALES:</strong>
                            @if(isset($historial->datos_adicionales['bono_descripcion']))
                                <br><span style="font-size: 9pt; color: #6b7280;">{{ $historial->datos_adicionales['bono_descripcion'] }}</span>
                            @endif
                        </td>
                        <td style="text-align: right; padding: 8px; border-bottom: 1px solid #e5e7eb; font-weight: bold;">
                            €{{ number_format($historial->total_bonos, 2, ',', '.') }}
                        </td>
                    </tr>
                    <tr style="background: #0ea5e9; color: white;">
                        <td colspan="3" style="padding: 12px 8px; font-size: 12pt; font-weight: bold; text-align: right;">TOTAL FINAL (Comisiones + Bonos):</td>
                        <td style="padding: 12px 8px; text-align: right; font-size: 14pt; font-weight: bold;">€{{ number_format($historial->total_final, 2, ',', '.') }}</td>
                    </tr>
                @else
                    <tr style="background: #0ea5e9; color: white;">
                        <td colspan="3" style="padding: 12px 8px; font-size: 12pt; font-weight: bold; text-align: right;">TOTAL COMISIONES DEL MES:</td>
                        <td style="padding: 12px 8px; text-align: right; font-size: 14pt; font-weight: bold;">€{{ number_format($historial->total_comisiones_calculado, 2, ',', '.') }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>

    {{-- Aviso Legal si no alcanza objetivos --}}
    @if(!$superaMinimos)
        <div class="section" style="page-break-inside: avoid;">
            <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; border-radius: 6px;">
                <p style="margin: 0 0 10px 0; font-weight: bold; color: #92400e; font-size: 11pt;">
                    Información importante sobre objetivos no alcanzados
                </p>
                <p style="margin: 0; font-size: 9pt; line-height: 1.6; color: #78350f;">
                    De acuerdo con lo establecido en el <strong>Contrato de Incentivos Comerciales</strong> firmado,
                    la no consecucion de los minimos mensuales establecidos sera objeto de revision por el
                    Departamento de Recursos Humanos. Este informe forma parte de la documentacion oficial
                    para el seguimiento del rendimiento y cumplimiento de objetivos segun lo estipulado en
                    la clausula de "Consecucion de Minimos" del contrato vigente.
                </p>
                <p style="margin: 10px 0 0 0; font-size: 9pt; line-height: 1.6; color: #78350f;">
                    Te animamos a revisar el detalle de este informe y a trabajar en las areas de mejora
                    identificadas para el proximo periodo. El equipo esta disponible para apoyarte en el
                    cumplimiento de tus objetivos.
                </p>
            </div>
        </div>
    @endif

    {{-- Hash de Integridad --}}
    <div class="hash-section">
        <div class="hash-label">INTEGRIDAD DEL DOCUMENTO</div>
        <div class="hash-value">SHA-256: {{ $hash }}</div>
    </div>
</body>
</html>
