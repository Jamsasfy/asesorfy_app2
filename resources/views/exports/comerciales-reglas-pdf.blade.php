<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comerciales y Reglas de Comisión</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 10px;
            color: #1f2937;
            background: #fff;
        }

        .header {
            background-color: #1e3a8a;
            color: #fff;
            padding: 12px 16px;
            margin-bottom: 16px;
        }

        .header h1 {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .header p {
            font-size: 9px;
            opacity: 0.8;
            margin-top: 4px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }

        thead tr {
            background-color: #1e40af;
            color: #fff;
        }

        thead th {
            padding: 7px 8px;
            text-align: left;
            font-weight: bold;
            letter-spacing: 0.3px;
            border-right: 1px solid #3b82f6;
        }

        thead th:last-child {
            border-right: none;
        }

        tbody tr:nth-child(even) {
            background-color: #f0f4ff;
        }

        tbody tr:nth-child(odd) {
            background-color: #fff;
        }

        tbody td {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e7eb;
            border-right: 1px solid #f3f4f6;
            vertical-align: top;
        }

        tbody td:last-child {
            border-right: none;
        }

        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8px;
            font-weight: bold;
        }

        .badge-green  { background-color: #d1fae5; color: #065f46; }
        .badge-blue   { background-color: #dbeafe; color: #1e40af; }
        .badge-gray   { background-color: #f3f4f6; color: #374151; }

        .footer {
            margin-top: 14px;
            font-size: 8px;
            color: #9ca3af;
            text-align: right;
        }

        .total-row {
            background-color: #eff6ff !important;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Comerciales — Reglas de Comisión</h1>
        <p>Generado el {{ now()->format('d/m/Y H:i') }} &nbsp;·&nbsp; {{ $users->count() }} {{ $users->count() === 1 ? 'comercial' : 'comerciales' }}</p>
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($columnas as $col)
                    <th>{{ $labels[$col] ?? $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($users as $user)
                @php
                    $activas      = $user->asignacionesReglas->where('activa', true);
                    $obligatorias = $user->asignacionesReglas->where('es_obligatoria', true);
                    $minimoTotal  = $user->asignacionesReglas->sum(fn ($a) => $a->regla?->minimo_mensual ?? 0);
                    $comisionMedia = $activas->isEmpty()
                        ? 0
                        : round($activas->avg(fn ($a) => $a->regla?->porcentaje_comision ?? 0), 2);
                @endphp
                <tr>
                    @foreach ($columnas as $col)
                        <td>
                            @switch($col)
                                @case('name')
                                    {{ $user->name }}
                                    @break
                                @case('email')
                                    {{ $user->email }}
                                    @break
                                @case('num_reglas')
                                    <span class="badge badge-gray">{{ $user->asignacionesReglas->count() }}</span>
                                    @break
                                @case('reglas_nombres')
                                    @if ($activas->isEmpty())
                                        <span style="color:#9ca3af;">—</span>
                                    @else
                                        @foreach ($activas as $asig)
                                            @if ($asig->regla)
                                                <span class="badge badge-blue">{{ $asig->regla->nombre }}</span>&nbsp;
                                            @endif
                                        @endforeach
                                    @endif
                                    @break
                                @case('reglas_obligatorias')
                                    @if ($obligatorias->isEmpty())
                                        <span style="color:#9ca3af;">—</span>
                                    @else
                                        @foreach ($obligatorias as $asig)
                                            @if ($asig->regla)
                                                <span class="badge badge-green">{{ $asig->regla->nombre }}</span>&nbsp;
                                            @endif
                                        @endforeach
                                    @endif
                                    @break
                                @case('minimo_mensual_total')
                                    <span class="badge badge-green">{{ number_format($minimoTotal, 2, ',', '.') }} €</span>
                                    @break
                                @case('comision_media')
                                    <span class="badge badge-blue">{{ number_format($comisionMedia, 2, ',', '.') }} %</span>
                                    @break
                            @endswitch
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columnas) }}" style="text-align:center; color:#9ca3af; padding:20px;">
                        No hay comerciales seleccionados.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        AsesorFy &nbsp;·&nbsp; Exportado el {{ now()->format('d/m/Y \a \l\a\s H:i') }}
    </div>

</body>
</html>
