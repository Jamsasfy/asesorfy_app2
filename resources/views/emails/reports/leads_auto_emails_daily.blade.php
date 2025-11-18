<p>Buenos días,</p>

<p>
Adjuntamos el informe diario de los emails automáticos enviados por el Boot IA Fy
correspondiente al día <strong>{{ $date->format('d/m/Y') }}</strong>.
</p>

<ul>
    <li>Total de registros en el log: <strong>{{ $total }}</strong></li>
    <li>Total con errores (failed): <strong>{{ $totalErrores }}</strong></li>
</ul>

@if(! empty($stats))
    <p>Resumen por estado y resultado:</p>
    <ul>
        @foreach($stats as $estado => $data)
            <li>
                <strong>{{ $estado }}</strong>:
                sent: {{ $data['sent'] ?? 0 }},
                rate_limited: {{ $data['rate_limited'] ?? 0 }},
                skipped: {{ $data['skipped'] ?? 0 }},
                failed: {{ $data['failed'] ?? 0 }},
                total: {{ $data['total'] ?? 0 }}
            </li>
        @endforeach
    </ul>
@endif

<p>
En el PDF adjunto tienes el detalle completo de todos los envíos automáticos
realizados por el Boot IA Fy en ese día.
</p>

<p>
También puedes revisar más detalle en el panel de
<strong>Log envíos automáticos</strong> dentro de AsesorFy.
</p>

<p>Saludos,<br>Boot IA Fy</p>
