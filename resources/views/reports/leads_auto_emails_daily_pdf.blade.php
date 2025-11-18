<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe diario Boot IA – {{ $date->format('d/m/Y') }}</title>
    <style>
        @page {
            margin: 20px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            line-height: 1.2;
        }

        h1 {
            font-size: 16px;
            margin: 0 0 6px 0;
        }

        h2 {
            font-size: 12px;
            margin: 10px 0 4px 0;
        }

        .small {
            font-size: 8px;
            color: #555;
            margin-bottom: 6px;
        }

        ul {
            margin: 4px 0 6px 16px;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 2px 3px;
            vertical-align: top;
        }

        th {
            background: #f3f4f6;
            font-weight: bold;
            font-size: 9px;
        }

        td {
            font-size: 8px;
            word-wrap: break-word;
            word-break: break-all;
        }

        .text-center { text-align: center; }
        .mt-2 { margin-top: 8px; }
    </style>
</head>
<body>
    <h1>Informe diario – Boot IA Fy</h1>
    <p class="small">Fecha del informe: {{ $date->format('d/m/Y') }}</p>

    <h2>Resumen general</h2>
    <ul>
        <li>Total de registros en log: <strong>{{ $total }}</strong></li>
        <li>Total con errores (failed): <strong>{{ $totalErrores }}</strong></li>
    </ul>

    @if(! empty($stats))
        <h2>Resumen por estado y resultado</h2>
        <table>
            <thead>
            <tr>
                <th style="width: 18%;">Estado lead</th>
                <th style="width: 8%;" class="text-center">Total</th>
                <th>Detalle por status</th>
            </tr>
            </thead>
            <tbody>
            @foreach($stats as $estado => $data)
                <tr>
                    <td>{{ $estado }}</td>
                    <td class="text-center">{{ $data['total'] }}</td>
                    <td>
                        sent: {{ $data['sent'] ?? 0 }}&nbsp;&nbsp;
                        rate_limited: {{ $data['rate_limited'] ?? 0 }}&nbsp;&nbsp;
                        skipped: {{ $data['skipped'] ?? 0 }}&nbsp;&nbsp;
                        failed: {{ $data['failed'] ?? 0 }}
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @if($logs->isNotEmpty())
        <h2 class="mt-2">Detalle de envíos (día {{ $date->format('d/m/Y') }})</h2>
        <table>
            <thead>
            <tr>
                <th style="width: 5%;">Hora</th>
                <th style="width: 12%;">Lead</th>
                <th style="width: 18%;">Email</th>
                <th style="width: 10%;">Estado lead</th>
                <th style="width: 10%;">Status envío</th>
                <th style="width: 5%;" class="text-center">Intento</th>
                <th style="width: 12%;">Plantilla</th>
                <th>Asunto</th>
            </tr>
            </thead>
            <tbody>
            @foreach($logs as $log)
                <tr>
                    <td class="text-center">
                        {{ optional($log->sent_at ?? $log->created_at)->format('H:i') }}
                    </td>
                    <td>{{ $log->lead->nombre ?? '-' }}</td>
                    <td>{{ $log->lead->email ?? '-' }}</td>
                    <td>{{ $log->estado ?? '-' }}</td>
                    <td>{{ $log->status ?? '-' }}</td>
                    <td class="text-center">{{ $log->intento ?? '-' }}</td>
                    <td>{{ $log->template_identifier ?? '-' }}</td>
                    <td>{{ $log->subject ?? '-' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @else
        <p>No hubo envíos automáticos registrados en el día indicado.</p>
    @endif

    <p class="small mt-2">
        Generado automáticamente por Boot IA Fy (AsesorFy).
    </p>
</body>
</html>
