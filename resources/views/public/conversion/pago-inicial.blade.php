<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Pago inicial | AsesorFy</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">

    <style>
        :root{
            --bg0:#070b14;
            --bg1:#0b1220;
            --card:rgba(15, 23, 42, .72);
            --card2:rgba(2, 6, 23, .55);
            --border:rgba(148, 163, 184, .18);
            --border2:rgba(148, 163, 184, .28);
            --text:#e5e7eb;
            --muted:#9ca3af;
            --muted2:#94a3b8;

            --brand:#38bdf8;   /* cyan */
            --brand2:#6366f1;  /* indigo */
            --ok:#22c55e;
            --warn:#f59e0b;
            --danger:#ef4444;

            --btn:#16a34a;
            --btn-h:#15803d;
            --btn2:transparent;
            --shadow:0 20px 60px rgba(0,0,0,.45);
        }

        *{box-sizing:border-box}
        html,body{height:100%}
        body{
            margin:0;
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial;
            color:var(--text);
            background:
                radial-gradient(1200px 700px at 15% 10%, rgba(56,189,248,.20), transparent 55%),
                radial-gradient(1000px 650px at 85% 20%, rgba(99,102,241,.16), transparent 55%),
                radial-gradient(900px 600px at 50% 100%, rgba(34,197,94,.10), transparent 60%),
                linear-gradient(180deg, var(--bg0), var(--bg1));
        }

        a{color:inherit}
        .wrap{max-width:1120px;margin:34px auto;padding:16px}
        .shell{
            border:1px solid var(--border);
            background:linear-gradient(180deg, rgba(15,23,42,.78), rgba(2,6,23,.55));
            border-radius:22px;
            box-shadow:var(--shadow);
            overflow:hidden;
        }

        .topbar{
            display:flex;align-items:center;justify-content:space-between;
            padding:18px 22px;
            border-bottom:1px solid var(--border);
        }
        .logo{display:flex;align-items:center;gap:12px}
        .logo img{height:34px;width:auto;display:block}
        .logo .fallback{font-weight:900;letter-spacing:.2px}

        .step{
            display:flex;align-items:center;gap:10px;
            color:var(--muted2);
            font-size:13px;
            white-space:nowrap;
        }
        .pill{
            display:inline-flex;align-items:center;gap:8px;
            padding:7px 10px;
            border-radius:999px;
            background:rgba(56,189,248,.10);
            border:1px solid rgba(56,189,248,.25);
            color:#bae6fd;
            font-weight:800;
            font-size:12px;
        }

        .content{padding:22px}
        .grid{
            display:grid;
            grid-template-columns: 1.05fr .95fr;
            gap:14px;
        }

        .panel{
            background:var(--card);
            border:1px solid var(--border);
            border-radius:18px;
            padding:18px;
            backdrop-filter: blur(10px);
        }

        .titleRow{
            display:flex;gap:12px;align-items:flex-start;justify-content:space-between;
            margin-bottom:10px;
        }
        .h1{
            font-size:22px;
            font-weight:900;
            letter-spacing:.2px;
            margin:0;
            line-height:1.2;
        }
        .sub{
            margin:6px 0 0 0;
            color:var(--muted2);
            font-size:13px;
            line-height:1.45;
        }

        .badgeIcon{
            width:38px;height:38px;border-radius:14px;
            display:grid;place-items:center;
            background:rgba(99,102,241,.12);
            border:1px solid rgba(99,102,241,.26);
            color:#c7d2fe;
            flex:0 0 auto;
        }

        .money{
            margin-top:10px;
            padding:14px 14px;
            border-radius:16px;
            background:linear-gradient(180deg, rgba(2,6,23,.45), rgba(2,6,23,.25));
            border:1px solid var(--border);
        }
        .money .label{color:var(--muted2);font-size:12px;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
        .money .amt{margin-top:6px;font-size:40px;font-weight:1000;letter-spacing:-.6px}
        .money .meta{margin-top:6px;color:var(--muted2);font-size:13px}

        .list{
            margin-top:12px;
            border:1px solid var(--border);
            border-radius:16px;
            overflow:hidden;
        }
        .listHead{
            padding:12px 14px;
            background:rgba(2,6,23,.40);
            border-bottom:1px solid var(--border);
            display:flex;align-items:center;justify-content:space-between;
            gap:10px;
        }
        .listHead .t{font-weight:900}
        .listHead .s{color:var(--muted2);font-size:12px}

        .row{
            display:flex;align-items:flex-start;justify-content:space-between;gap:12px;
            padding:12px 14px;
            background:rgba(15,23,42,.40);
            border-bottom:1px solid rgba(148,163,184,.12);
        }
        .row:last-child{border-bottom:0}
        .row .name{font-weight:850}
        .row .desc{color:var(--muted2);font-size:12px;margin-top:2px;line-height:1.35}
        .row .price{font-weight:950;white-space:nowrap}

        .totals{
            margin-top:12px;
            border:1px solid var(--border);
            border-radius:16px;
            padding:12px 14px;
            background:rgba(2,6,23,.28);
        }
        .totalsLine{
            display:flex;align-items:center;justify-content:space-between;
            padding:6px 0;
            color:var(--muted2);
            font-size:13px;
        }
        .totalsLine strong{color:var(--text)}
        .totalsLine.total{
            margin-top:6px;
            padding-top:10px;
            border-top:1px dashed rgba(148,163,184,.25);
            color:var(--text);
            font-size:14px;
            font-weight:900;
        }

        .note{
            margin-top:12px;
            padding:12px 14px;
            border-radius:16px;
            border:1px solid rgba(56,189,248,.22);
            background:rgba(56,189,248,.08);
            color:#bae6fd;
            font-size:13px;
            line-height:1.45;
        }

        /* Opciones */
        .optGrid{display:grid;grid-template-columns:1fr;gap:10px;margin-top:12px}
        .opt{
            cursor:pointer;
            user-select:none;
        }
        .optBox{
            display:flex;gap:12px;align-items:flex-start;
            padding:14px 14px;
            border-radius:16px;
            background:var(--card2);
            border:1px solid rgba(148,163,184,.22);
            transition:.15s;
        }
        .optBox:hover{transform:translateY(-1px);border-color:rgba(148,163,184,.35)}
        .opt.is-selected .optBox{
            border-color:rgba(56,189,248,.55);
            box-shadow:0 0 0 3px rgba(56,189,248,.14);
        }
        .optIco{
            width:40px;height:40px;border-radius:14px;display:grid;place-items:center;flex:0 0 auto;
            background:rgba(148,163,184,.10);
            border:1px solid rgba(148,163,184,.20);
        }
        .optTitle{font-weight:950}
        .optDesc{margin-top:3px;color:var(--muted2);font-size:13px;line-height:1.35}

        .hidden-inputs{position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden}

        .actions{
            margin-top:14px;
            display:flex;gap:12px;flex-wrap:wrap;align-items:center;
        }
        .btn{
            appearance:none;border:0;cursor:pointer;
            padding:12px 16px;border-radius:14px;
            font-weight:950;font-size:15px;
            display:inline-flex;align-items:center;justify-content:center;gap:10px;
            text-decoration:none;
        }
        .btnPrimary{background:var(--btn);color:#fff}
        .btnPrimary:hover{background:var(--btn-h)}
        .btnSecondary{
            background:transparent;
            border:1px solid rgba(148,163,184,.25);
            color:var(--text);
        }
        .btnSecondary:hover{border-color:rgba(148,163,184,.40);background:rgba(15,23,42,.35)}
        .security{
            margin-top:10px;
            color:var(--muted2);
            font-size:12px;
            display:flex;align-items:center;gap:8px;
        }

        .err{
            margin-top:12px;
            padding:12px 14px;
            border-radius:14px;
            background:rgba(239,68,68,.12);
            border:1px solid rgba(239,68,68,.28);
            color:#fecaca;
            font-size:13px;
            line-height:1.35;
        }

        .footerLink{margin:18px 2px 0;color:#93c5fd;text-decoration:underline;display:inline-block}

        @media(max-width:920px){
            .grid{grid-template-columns:1fr}
            .money .amt{font-size:36px}
        }
    </style>
</head>
<body>
@php
    // -------------------------
    // Datos base
    // -------------------------
    $cliente = $venta->cliente ?? null;
    $form = $link->meta['form_data'] ?? [];

    // Items
    $itemsUnicos = $venta->items->filter(fn($i) => $i->servicio && $i->servicio->tipo->value === 'unico');
    $itemsRecurrentes = $venta->items->filter(fn($i) => $i->servicio && $i->servicio->tipo->value === 'recurrente');

    // IVA (mismo criterio que finished)
    $cpCliente   = $form['cp'] ?? ($cliente->codigo_postal ?? '');
    $provCliente = $form['provincia'] ?? ($cliente->provincia ?? '');

    $porcentajeIva = \App\Models\Cliente::getPorcentajeImpuesto($cpCliente, $provCliente);
    $factorIva = 1 + ($porcentajeIva / 100);

    $baseInicial = (float) $itemsUnicos->sum(fn($i) => (float) $i->subtotal_aplicado);
    $ivaInicial  = round($baseInicial * ($porcentajeIva / 100), 2);
    $totalInicial = round($baseInicial * $factorIva, 2);

    // Método preseleccionado
    $preselect = old('pago_inicial_metodo', $venta->pago_inicial_metodo ?? ($link->meta['pago_inicial_metodo'] ?? 'stripe'));

    // CTA volver
    $backRoute = route('conversion.contract', ['token' => $link->token]);
@endphp

<div class="wrap">
    <div class="shell">

        <div class="topbar">
            <div class="logo">
                <img src="{{ asset('images/logo_dark.png') }}" alt="AsesorFy"
                     onerror="this.replaceWith(Object.assign(document.createElement('div'),{className:'fallback',textContent:'AsesorFy'}));">
            </div>

            <div class="step">
                <span class="pill">Paso 1 · Pago inicial</span>
                <span>Después: cuota mensual (si aplica)</span>
            </div>
        </div>

        <div class="content">
            <div class="grid">

                {{-- IZQ: resumen ultra claro --}}
                <div class="panel">
                    <div class="titleRow">
                        <div>
                            <h1 class="h1">Lo que pagas hoy</h1>
                            <p class="sub">
                                Este pago activa tus <strong>servicios de inicio</strong>.
                                @if($itemsRecurrentes->isNotEmpty())
                                    La cuota mensual se configura después (tarjeta o IBAN).
                                @else
                                    No hay cuota mensual asociada a esta contratación.
                                @endif
                            </p>
                        </div>
                        <div class="badgeIcon">€</div>
                    </div>

                    <div class="money">
                        <div class="label">Importe total a abonar ahora</div>
                        <div class="amt">{{ number_format($totalInicial, 2, ',', '.') }} €</div>
                        <div class="meta">IVA incluido ({{ (int) $porcentajeIva }}%) · Base {{ number_format($baseInicial, 2, ',', '.') }} €</div>
                    </div>

                    <div class="list">
                        <div class="listHead">
                            <div class="t">Servicios incluidos en este pago</div>
                            <div class="s">Pago único</div>
                        </div>

                        @foreach($itemsUnicos as $item)
                            @php
                                $nombre = $item->nombre_personalizado ?? $item->servicio->nombre ?? 'Servicio';
                                $qty = (float) ($item->cantidad ?? 1);
                                $lineBase = (float) ($item->subtotal_aplicado ?? 0);
                                $lineTotal = round($lineBase * $factorIva, 2);
                            @endphp
                            <div class="row">
                                <div>
                                    <div class="name">
                                        {{ $nombre }}
                                        @if($qty > 1)
                                            <span style="color:var(--muted2);font-weight:800"> (x{{ (int)$qty }})</span>
                                        @endif
                                    </div>
                                    <div class="desc">
                                        Activación / servicio de inicio. Este concepto se cobra una sola vez.
                                    </div>
                                </div>
                                <div class="price">{{ number_format($lineTotal, 2, ',', '.') }} €</div>
                            </div>
                        @endforeach
                    </div>

                    <div class="totals">
                        <div class="totalsLine">
                            <span>Base imponible</span>
                            <strong>{{ number_format($baseInicial, 2, ',', '.') }} €</strong>
                        </div>
                        <div class="totalsLine">
                            <span>IVA ({{ (int)$porcentajeIva }}%)</span>
                            <strong>{{ number_format($ivaInicial, 2, ',', '.') }} €</strong>
                        </div>
                        <div class="totalsLine total">
                            <span>Total hoy</span>
                            <span>{{ number_format($totalInicial, 2, ',', '.') }} €</span>
                        </div>
                    </div>

                    @if($itemsRecurrentes->isNotEmpty())
                        @php
                            $baseMensual = (float) $itemsRecurrentes->sum(fn($i)=>(float)$i->subtotal_aplicado);
                            $mensual = round($baseMensual * $factorIva, 2);
                            $nombresR = $itemsRecurrentes->map(function($i){
                                $n = $i->nombre_personalizado ?? $i->servicio->nombre ?? 'Servicio mensual';
                                $q = (int) ($i->cantidad ?? 1);
                                return $q > 1 ? "{$n} (x{$q})" : $n;
                            })->values()->all();
                        @endphp
                        <div class="note">
                            <strong>Cuota mensual (no se cobra ahora):</strong><br>
                            {{ implode(' + ', $nombresR) }}<br>
                            <span style="color:#e0f2fe">Importe estimado:</span>
                            <strong>{{ number_format($mensual, 2, ',', '.') }} €/mes</strong> (IVA incl.)<br>
                            La configurarás en el siguiente paso para que el cobro sea automático.
                        </div>
                    @endif
                </div>

                {{-- DER: selección método --}}
                <div class="panel">
                    <div class="titleRow">
                        <div>
                            <h2 class="h1" style="font-size:20px">Elige cómo pagar</h2>
                            <p class="sub">
                                Selecciona el método para completar el pago inicial.
                                <strong>Recomendado:</strong> tarjeta para activación inmediata.
                            </p>
                        </div>
                        <div class="badgeIcon">⚡</div>
                    </div>

                    @if(session('error'))
                        <div class="err">{{ session('error') }}</div>
                    @endif

                    <form method="POST" action="{{ route('conversion.pago-inicial.store', ['token' => $link->token]) }}" style="margin-top:10px;">
                        @csrf

                        <div class="hidden-inputs">
                            <input type="radio" name="pago_inicial_metodo" id="m_stripe" value="stripe" @checked($preselect === 'stripe')>
                            <input type="radio" name="pago_inicial_metodo" id="m_transferencia" value="transferencia" @checked($preselect === 'transferencia')>
                        </div>

                        <div class="optGrid">

                            <div class="opt js-opt" data-method="stripe">
                                <div class="optBox">
                                    <div class="optIco">💳</div>
                                    <div>
                                        <div class="optTitle">Tarjeta (Stripe)</div>
                                        <div class="optDesc">
                                            Confirmación inmediata. Si luego hay cuota mensual, podrás <strong>usar esta misma tarjeta</strong> o elegir otra.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="opt js-opt" data-method="transferencia">
                                <div class="optBox">
                                    <div class="optIco">🏦</div>
                                    <div>
                                        <div class="optTitle">Transferencia bancaria</div>
                                        <div class="optDesc">
                                            Te mostraremos los datos (IBAN y concepto). La activación se realizará tras verificar el pago.
                                            Después podrás configurar la cuota mensual (si aplica).
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        @error('pago_inicial_metodo')
                            <div class="err">{{ $message }}</div>
                        @enderror

                        <div class="actions">
                            <button class="btn btnPrimary" type="submit">Continuar</button>
                            <a class="btn btnSecondary" href="{{ $backRoute }}">Volver</a>
                        </div>

                        <div class="security">
                            🔒 Pago seguro: con tarjeta se procesa directamente con Stripe.
                        </div>
                    </form>

                    <a class="footerLink" href="https://asesorfy.net" target="_blank" rel="noopener noreferrer">Volver a AsesorFy</a>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const opts = document.querySelectorAll('.js-opt');
    const mStripe = document.getElementById('m_stripe');
    const mTransfer = document.getElementById('m_transferencia');

    function clearSelected(){ opts.forEach(o => o.classList.remove('is-selected')); }

    function syncSelected(){
        clearSelected();
        const current = (mTransfer && mTransfer.checked) ? 'transferencia' : 'stripe';
        opts.forEach(o => {
            if (o.getAttribute('data-method') === current) o.classList.add('is-selected');
        });
    }

    opts.forEach(o => {
        o.addEventListener('click', () => {
            const method = o.getAttribute('data-method');
            if (method === 'transferencia') mTransfer.checked = true;
            else mStripe.checked = true;
            syncSelected();
        });
    });

    syncSelected();
})();
</script>

</body>
</html>
