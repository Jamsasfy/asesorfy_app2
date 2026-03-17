<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Pago mensual | AsesorFy</title>
  <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
  <link href="https://fonts.googleapis.com/css2?family=Varela+Round&display=swap" rel="stylesheet">

  <style>
    :root {
      --bg:#0f172a;
      --card:#1e293b;
      --text:#f1f5f9;
      --muted:#94a3b8;

      --ok:#22c55e;
      --warn:#f59e0b;
      --danger:#ef4444;

      --blue:#60a5fa;
      --border:#334155;

      --btn:#16a34a;
      --btn-h:#15803d;
    }

    *{box-sizing:border-box}
    body{ margin:0; font-family:"Varela Round", sans-serif; background:var(--bg); color:var(--text); }
    a{ color:inherit; }

    .wrap{ max-width:900px; margin:40px auto; padding:20px; }
    .card{
      background:var(--card);
      border-radius:24px;
      padding:40px;
      box-shadow:0 25px 50px -12px rgba(0,0,0,0.5);
      border:1px solid var(--border);
    }

    .logo img { height: 45px; display:block; margin: 0 auto 18px; }

    .header { text-align:center; margin-bottom:26px; }
    h1 { margin:0 0 10px; font-size:30px; }
    .subhead{ color:var(--muted); margin:0; line-height:1.7; }

    .data-grid {
      display:grid; grid-template-columns: repeat(3, 1fr); gap:20px;
      background:#0f172a; padding:20px; border-radius:16px;
      margin: 18px 0 26px;
      border:1px solid var(--border);
    }
    .lbl { font-size:12px; text-transform:uppercase; color:var(--muted); font-weight:700; margin-bottom:4px; }
    .val { font-size:15px; font-weight:600; color:#fff; word-break: break-word; }

    /* BOXES */
    .status-box{
      background:#0f172a;
      border-radius:16px;
      padding:24px;
      margin-bottom:18px;
      border-left:5px solid transparent;
      border:1px solid var(--border);
    }
    .status-box.info { border-left-color: #3b82f6; background: linear-gradient(90deg, rgba(59,130,246,0.06) 0%, rgba(15,23,42,1) 100%); }
    .status-box.warn { border-left-color: #f59e0b; background: linear-gradient(90deg, rgba(245,158,11,0.06) 0%, rgba(15,23,42,1) 100%); }
    .status-box.success { border-left-color: #22c55e; background: linear-gradient(90deg, rgba(34,197,94,0.06) 0%, rgba(15,23,42,1) 100%); }
    .status-box.danger { border-left-color: #ef4444; background: linear-gradient(90deg, rgba(239,68,68,0.06) 0%, rgba(15,23,42,1) 100%); }

    .box-header { display:flex; justify-content:space-between; align-items:flex-start; gap:14px; margin-bottom:10px; }
    .box-title { font-size:18px; font-weight:800; display:flex; align-items:center; gap:10px; }
    .box-amount { font-size:24px; font-weight:900; color:#fff; text-align:right; white-space:nowrap; }
    .box-body { font-size:14px; color:var(--muted); line-height:1.7; }

    .text-green { color:#4ade80; font-weight:800; }
    .text-orange { color:#fbbf24; font-weight:800; }
    .text-blue { color:#60a5fa; font-weight:800; }
    .text-red { color:#f87171; font-weight:800; }
    .text-white { color:#fff; font-weight:800; }

    /* TABLA SERVICIOS */
    .services-table { margin-top: 10px; border-top: 1px solid var(--border); padding-top: 18px; }
    .st-head { font-size: 12px; text-transform: uppercase; color: var(--muted); font-weight: 800; margin-bottom: 12px; letter-spacing: 0.05em; }
    .st-row { display: flex; justify-content: space-between; align-items: flex-start; gap:16px; padding: 12px 0; border-bottom: 1px dashed var(--border); }
    .st-row:last-child { border-bottom: 0; }
    .st-name { font-weight: 700; font-size: 15px; color: #fff; }
    .st-meta { font-size: 12px; color: var(--muted); display: block; margin-top: 4px; line-height:1.45; }
    .st-price { font-weight: 900; color: #fff; text-align: right; white-space:nowrap; }
    .st-price small{ font-size:11px; font-weight:700; color:var(--muted); margin-left:6px; }

    .old{ text-decoration: line-through; color: #94a3b8; font-weight:700; font-size:12px; margin-top:4px; }
    .disc{ color:#fbbf24; font-weight:900; font-size:12px; margin-top:4px; }
    .promo-note{ color:#cbd5e1; font-weight:700; }
    .future-note{ color:#94a3b8; font-weight:700; }

    /* BLOQUE A PAGAR HOY */
    .paytoday{
      margin-top:14px;
      border-radius:14px;
      padding:14px 16px;
      display:flex;
      justify-content:space-between;
      align-items:flex-start;
      gap:14px;
    }
    .paytoday.free{
      background: rgba(34,197,94,0.10);
      border: 1px solid rgba(34,197,94,0.30);
      color:#86efac;
      box-shadow: 0 6px 18px rgba(34,197,94,.08);
    }
    .paytoday.paid{
      background: rgba(2,6,23,.35);
      border: 1px solid rgba(245,158,11,.28);
      color:#fcd34d;
      box-shadow: 0 6px 18px rgba(245,158,11,.08);
    }
    .paytoday .lbl{
      font-size:12px;
      text-transform:uppercase;
      letter-spacing:.06em;
      font-weight:900;
      margin:0 0 6px 0;
      color:inherit;
    }
    .paytoday .amt{
      font-size:26px;
      font-weight:1000;
      line-height:1;
      color:inherit;
    }
    .paytoday .sub{
      text-align:right;
      font-size:12px;
      font-weight:800;
      line-height:1.45;
      color:inherit;
      opacity:.95;
    }
    .paytoday .sub .muted{
      color:rgba(241,245,249,.80);
      font-weight:800;
    }

    /* MÉTODOS PAGO (mismo estilo que pago inicial) */
    .pay-methods { margin-top: 18px; }
    .pm-head { font-size: 12px; text-transform: uppercase; color: var(--muted); font-weight: 800; margin: 18px 0 12px; letter-spacing: 0.05em; }
    .opt{ display:block; cursor:pointer; user-select:none; }
    .optbox{
      border:1px solid #334155; border-radius:16px; padding:16px 16px;
      background:rgba(2,6,23,.35); transition:.15s;
      display:flex; gap:12px; align-items:flex-start
    }
    .optbox:hover{ border-color:#475569; background:rgba(2,6,23,.55); transform: translateY(-1px); }
    .opt.is-selected .optbox{ border-color:rgba(56,189,248,.70); box-shadow:0 0 0 3px rgba(56,189,248,.14) }

    .opt-ico{ width:40px;height:40px;border-radius:14px;display:grid;place-items:center;flex:0 0 auto; background:rgba(148,163,184,.10); border:1px solid rgba(148,163,184,.20); }
    .opt-title{ font-weight:900; }
    .opt-desc{ font-size:13px; color:var(--muted); margin-top:2px; line-height:1.45; }

    .chip{
      display:inline-flex; align-items:center; gap:8px;
      margin-top:8px;
      padding:6px 10px;
      border-radius:999px;
      border:1px solid rgba(148,163,184,.20);
      background:rgba(2,6,23,.28);
      color:#cbd5e1;
      font-size:12px;
      font-weight:800;
    }

    .btnrow{ margin-top:16px; display:flex; gap:12px; flex-wrap:wrap }
    .btn{
      appearance:none; border:0; cursor:pointer; background:var(--btn); color:#fff; font-weight:900;
      padding:12px 16px; border-radius:12px; font-size:15px; text-decoration:none; display:inline-flex; align-items:center; gap:10px; justify-content:center
    }
    .btn:hover{ background:var(--btn-h) }
    .btn-secondary{ background:transparent; border:1px solid #475569; color:#cbd5e1 }
    .btn-secondary:hover{ background:#1e293b; border-color:#94a3b8 }

    .err{
      margin-top:16px;
      padding:12px 16px;
      border-radius:12px;
      background:rgba(239,68,68,.12);
      color:#fecaca;
      border:1px solid rgba(239,68,68,.28);
      font-size:.9rem;
      line-height:1.4
    }
    .hidden-inputs{ position:absolute; left:-9999px; top:auto; width:1px; height:1px; overflow:hidden }

    .footer { text-align:center; margin-top:26px; font-size:13px; }
    .footer a { color:var(--muted); text-decoration: underline; }

    @media(max-width:700px){
      .data-grid{ grid-template-columns:1fr; }
      .card{ padding:26px; }
      .box-header{ flex-direction:column; align-items:flex-start; }
      .box-amount{ text-align:left; }
      .st-row{ flex-direction:column; align-items:flex-start; }
      .st-price{ text-align:left; }
      .paytoday{ flex-direction:column; }
      .paytoday .sub{ text-align:left; }
    }
  </style>
</head>
<body>
@php
  // =========================
  // 0) Variables globales
  // =========================
  $cliente = $venta->cliente ?? null;
  $form = $link->meta['form_data'] ?? [];

  $toBool = function ($v): bool {
      if (is_bool($v)) return $v;
      if ($v === null || $v === '') return false;
      $parsed = filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
      return $parsed ?? (bool) $v;
  };

  $hasDefaultPM = $hasDefaultPM ?? false;
  $defaultPmLabel = $defaultPmLabel ?? null;
  $defaultPmType = $defaultPmType ?? null;
  $hasDefaultCard = $hasDefaultPM && $defaultPmLabel && $defaultPmType === 'card';

  $preselectMetodo = old('recurrente_metodo', $link->meta['recurrente_metodo'] ?? ($cliente->preferencia_pago_recurrente ?? 'tarjeta'));
  $preselectAccion = old('tarjeta_accion', $link->meta['tarjeta_accion'] ?? ($hasDefaultCard ? 'usar_existente' : 'usar_otra'));

  $pagoInicialMetodo = $pagoInicialMetodo ?? ($venta->pago_inicial_metodo ?? ($link->meta['pago_inicial_metodo'] ?? 'stripe'));
  $tieneUnico = $venta?->items?->contains(fn($i) => $i->servicio && $i->servicio->tipo->value === 'unico') ?? false;
  $pagoInicialCompletado = $tieneUnico ? ($venta?->tienePagoInicialCompletado() ?? false) : false;

  $mensajeIntro = match (true) {
      $tieneUnico === false
          => 'No hay importe inicial. Ahora elige el método para tus cuotas mensuales.',
      $pagoInicialMetodo === 'stripe' && $pagoInicialCompletado
          => 'Has pagado el importe inicial con tarjeta. Puedes usar esa misma u otra distinta para las cuotas mensuales.',
      $pagoInicialMetodo === 'stripe' && !$pagoInicialCompletado
          => 'Has elegido pagar el importe inicial con tarjeta. Ahora elige el método para las cuotas mensuales.',
      $pagoInicialMetodo === 'transferencia'
          => 'Has elegido transferencia para el importe inicial. Ahora elige el método para las cuotas mensuales.',
      default
          => 'Elige el método para las cuotas mensuales.',
  };

  $tituloTarjetaNueva = $hasDefaultCard ? 'Usar otra tarjeta' : 'Pagar con tarjeta';
  $descTarjetaNueva = $hasDefaultCard ? 'Configurar una tarjeta distinta.' : 'Configurar una tarjeta para la cuota mensual.';

  // =========================
  // 1) IVA y Helpers
  // =========================
  $cpCliente   = $form['cp'] ?? ($cliente->codigo_postal ?? '');
  $provCliente = $form['provincia'] ?? ($cliente->provincia ?? '');
  $porcentajeIva = \App\Models\Cliente::getPorcentajeImpuesto($cpCliente, $provCliente);
  $factorIva = 1 + ($porcentajeIva / 100);

  $fmtMoney = fn (float $n) => number_format($n, 2, ',', '.');
  $fmtMesAnio = fn (\Carbon\Carbon $d) => ucfirst($d->locale('es')->translatedFormat('F Y'));
  $fmtMes = fn (\Carbon\Carbon $d) => ucfirst($d->locale('es')->translatedFormat('F'));

  $fmtRangoMeses = function ($inicio, $meses) use ($fmtMes, $fmtMesAnio): ?string {
      if (!$inicio instanceof \Carbon\Carbon) $inicio = \Carbon\Carbon::parse($inicio)->locale('es');
      $meses = (int) $meses;
      if ($meses <= 0) return null;

      $ini = $inicio->copy()->startOfMonth();
      $fin = $ini->copy()->addMonthsNoOverflow($meses - 1)->startOfMonth();

      if ($meses === 1) return $fmtMesAnio($ini);
      $iniTxt = $fmtMes($ini);
      $finTxt = $fmtMes($fin);

      if ((int) $ini->year !== (int) $fin->year) {
          $iniTxt = $fmtMesAnio($ini);
          $finTxt = $fmtMesAnio($fin);
          return "de {$iniTxt} a {$finTxt}";
      }

      if ($meses === 2) return "{$iniTxt} y {$finTxt}";
      return "de {$iniTxt} a {$finTxt}";
  };

  // Helper: rango relativo a activación (para inicio diferido)
  $fmtRangoRelativo = function (int $inicioMes, int $meses): ?string {
      if ($meses <= 0) return null;
      $finMes = $inicioMes + $meses - 1;
      return $inicioMes === $finMes ? "Mes {$inicioMes}" : "Meses {$inicioMes}-{$finMes}";
  };

  // =========================
  // 2) Fechas y Prorratas
  // =========================
  $hoy = \Carbon\Carbon::now()->locale('es');
  $diasMes = $hoy->daysInMonth ?: 30;
  $diasRestantes = ($diasMes - $hoy->day) + 1;

  $primerCobroFecha = $hoy->copy()->addMonthNoOverflow()->startOfMonth();
  $textoPrimerCobro = '1 de ' . ucfirst($primerCobroFecha->translatedFormat('F Y'));
  $mesActualTxt = ucfirst($hoy->translatedFormat('F'));

  // =========================
  // 3) Recuperar Servicios (Blueprint) + detectar inicio diferido
  // =========================
  $services = [];
  $candidatos = [
      data_get($link, 'meta.blueprint.servicios'),
      data_get($link, 'meta.servicios'),
      data_get($link, 'meta.sale_blueprint.servicios'),
      data_get($venta, 'meta.blueprint.servicios'),
      data_get($venta, 'meta.servicios'),
      data_get($venta, 'blueprint.servicios'),
  ];
  foreach ($candidatos as $cand) {
      if (is_array($cand) && !empty($cand)) { $services = $cand; break; }
  }

  $inicioDiferido = collect($services)->contains(function ($s) use ($toBool) {
      $esEditable = $toBool($s['es_editable'] ?? false);
      $req = $esEditable ? ($s['requiere_proyecto'] ?? false) : ($s['servicio_requiere_proyecto'] ?? false);
      return $toBool($req);
  });

  // =========================
  // 3.1) Recurrentes (Blueprint vs Items fallback)
  // =========================
  $recurrentesBlueprint = [];
  if (!empty($services)) {
      $recurrentesBlueprint = array_values(array_filter($services, fn($s) => ($s['tipo'] ?? '') === 'recurrente'));
  }

  $itemsRecurrentes = $venta->items->filter(fn($i) => $i->servicio && $i->servicio->tipo->value === 'recurrente');
  $recurrentesItems = $itemsRecurrentes->map(function($i){
      $qty = (float) ($i->cantidad ?? 1);
      if ($qty <= 0) $qty = 1;
      $base = (float) ($i->precio_base ?? $i->precio_base_original ?? 0) * $qty;

      $meta = is_array($i->meta ?? null) ? $i->meta : [];
      $promo = $meta['descuento'] ?? null;
      $cobro = $meta['cobro_primer_mes'] ?? 'prorrata';
      if (!empty($meta['no_cobrar_primer_periodo'])) $cobro = 'gratis';

      return [
          'tipo'     => 'recurrente',
          'nombre'   => $i->nombre_personalizado ?? $i->servicio->nombre ?? 'Servicio mensual',
          'unidades' => (int) $qty,
          'precio_base_original' => $base,
          'descuento'      => $promo,
          'cobro_primer_mes' => $cobro,
      ];
  })->values()->all();

  $recurrentes = !empty($recurrentesBlueprint) ? $recurrentesBlueprint : $recurrentesItems;

  $recPrincipal = null;
  if (!empty($services)) {
      $recPrincipal = collect($services)->first(fn($s) => ($s['tipo'] ?? '') === 'recurrente' && !empty($s['es_tarifa_principal']))
          ?? collect($services)->first(fn($s) => ($s['tipo'] ?? '') === 'recurrente');
  }
  if (!$recPrincipal && !empty($recurrentes)) $recPrincipal = $recurrentes[0];

  // =========================
  // 4) Lógica de Cálculo de Línea
  // =========================
  $calcLine = function (array $s) use ($diasMes, $diasRestantes, $toBool): array {
      $units = (float) ($s['unidades'] ?? 1);
      if ($units <= 0) $units = 1;

      $baseTotal = (float) ($s['subtotal_base'] ?? 0)
          ?: ((float) ($s['precio_base_original'] ?? 0) * $units)
          ?: ((float) ($s['precio_base'] ?? 0) * $units);

      if ($baseTotal <= 0 && !empty($s['servicio_id'])) {
          $baseTotal = (float) (\App\Models\Servicio::find($s['servicio_id'])?->precio_base ?? 0) * $units;
      }

      $promo = $s['descuento'] ?? null;
      $aplicaPromo = (bool) data_get($promo, 'aplicar', false);
      $promoTipo  = (string) data_get($promo, 'tipo', '');
      $promoValor = (float) (data_get($promo, 'valor') ?: 0);

      $finalTotal = $baseTotal;
      if ($aplicaPromo) {
          if ($promoTipo === 'porcentaje' && $promoValor > 0) {
              $finalTotal = max(0, $baseTotal * (1 - ($promoValor / 100)));
          } elseif (in_array($promoTipo, ['fijo', 'importe', 'euros'], true) && $promoValor > 0) {
              $finalTotal = max(0, $baseTotal - $promoValor);
          } elseif ($promoTipo === 'precio_final' && $promoValor > 0) {
              $finalTotal = max(0, $promoValor);
          }
      }

      $discount = max(0, $baseTotal - $finalTotal);
      $promoMeses = (int) (data_get($promo, 'meses') ?: 0);

      $cobro = $s['cobro_primer_mes'] ?? 'prorrata';
      if (!empty($s['no_cobrar_primer_periodo']) && $toBool($s['no_cobrar_primer_periodo'])) $cobro = 'gratis';

      $payToday = 0.0;
      if ($cobro === 'gratis') $payToday = 0.0;
      elseif ($cobro === 'completo') $payToday = $finalTotal;
      else $payToday = ($finalTotal / $diasMes) * $diasRestantes;

      return [
          'base'        => $baseTotal,
          'final'       => $finalTotal,
          'pay_today'   => $payToday,
          'cobro'       => $cobro,
          'discount'    => $discount,
          'promo_meses' => $promoMeses,
          'promo_tipo'  => $promoTipo,
          'promo_valor' => $promoValor,
          'aplicaPromo' => $aplicaPromo,
      ];
  };

  // =========================
  // 5) Totales Globales (mensual) + flags
  // =========================
  $totalBaseMensual = 0.0;
  $totalFinalMensual = 0.0;
  $totalPagarHoy = 0.0;
  $hayPromo = false;

  $hayCobroProrrata = false;
  $hayCobroCompleto = false;
  $hayCobroGratis = false;

  foreach ($recurrentes as $s) {
      $l = $calcLine($s);

      $totalBaseMensual  += $l['base'];
      $totalFinalMensual += $l['final'];
      $totalPagarHoy     += $l['pay_today'];

      if ($l['discount'] > 0.001) $hayPromo = true;

      if ($l['cobro'] === 'prorrata') $hayCobroProrrata = true;
      if ($l['cobro'] === 'completo') $hayCobroCompleto = true;
      if ($l['cobro'] === 'gratis') $hayCobroGratis = true;
  }

  $totalBaseMensualTax  = $totalBaseMensual * $factorIva;
  $totalFinalMensualTax = $totalFinalMensual * $factorIva;
  $totalPagarHoyTaxReal = $totalPagarHoy * $factorIva;

  // Si inicio diferido: HOY 0
  $totalPagarHoyTax = $inicioDiferido ? 0.0 : $totalPagarHoyTaxReal;

  $ceroPorInicioDiferido = $inicioDiferido;
  $ceroPorMesGratis = (!$inicioDiferido) && ($totalPagarHoyTax < 0.01) && $hayCobroGratis;

  $txtCobroHoy = 'A PAGAR HOY:';
  if (!$inicioDiferido && $totalPagarHoyTax >= 0.01) {
      if ($hayCobroProrrata) $txtCobroHoy = 'A PAGAR HOY (PRORRATA):';
      elseif ($hayCobroCompleto) $txtCobroHoy = 'A PAGAR HOY (MES COMPLETO):';
  }

  // Datos titular
  $titular = trim(($form['nombre'] ?? '') . ' ' . ($form['apellidos'] ?? ''));
  if (!$titular) $titular = trim(($cliente->nombre ?? '') . ' ' . ($cliente->apellidos ?? ''));
  if (!$titular) $titular = $cliente->razon_social ?? '—';

  // Back route
  $tieneUnico2 = $venta->items->contains(fn($i)=>$i->servicio && $i->servicio->tipo->value === 'unico');
  $backRoute = $tieneUnico2 ? route('conversion.pago-inicial', ['token'=>$link->token]) : route('conversion.finished', ['token'=>$link->token]);
@endphp

<div class="wrap">
  <div class="card">

    <div class="header">
      <div class="logo">
        <img src="{{ asset('images/logo_dark.png') }}" alt="AsesorFy"
             onerror="this.replaceWith(document.createTextNode('AsesorFy'));"/>
      </div>

      <h1 style="color:#43beeb;">Pago mensual</h1>

      <p class="subhead">
        {{ $mensajeIntro }}<br>
        Configura tu método para la <span class="text-white">cuota mensual</span> (tarjeta o IBAN).
      </p>
    </div>

    @if(session('error'))
      <div class="err">{{ session('error') }}</div>
    @endif

    {{-- DATOS CLIENTE --}}
    <div class="data-grid">
      <div><div class="lbl">Titular</div><div class="val">{{ $titular  }}</div></div>
      <div><div class="lbl">DNI / CIF</div><div class="val">{{ $form['cif'] ?? $form['dni'] ?? '—' }}</div></div>
      <div><div class="lbl">Email</div><div class="val">{{ $form['email'] ?? '—' }}</div></div>
    </div>

    {{-- 1) RESUMEN CUOTA --}}
    <div class="status-box info">
      <div class="box-header">
        <div class="box-title" style="color: var(--blue);">↻ Cuota mensual</div>
        <div class="box-amount" style="color: var(--blue);">
          {{ $fmtMoney($totalFinalMensualTax) }} €/mes
          <small style="font-size:11px;font-weight:800;color:var(--muted);">IVA inc.</small>
        </div>
      </div>

      <div class="box-body">
        Base imponible mensual: <span class="text-white">{{ $fmtMoney($totalFinalMensual) }} €</span>
        · IVA ({{ (int)$porcentajeIva }}%): <span class="text-white">{{ $fmtMoney($totalFinalMensual * ($porcentajeIva/100)) }} €</span>

        @if($inicioDiferido)
          <br><span class="text-orange">Inicio diferido:</span> la suscripción se activará al finalizar los trámites iniciales.
        @elseif($hayPromo)
          <br><span class="text-orange">Precio especial aplicado</span> a tus servicios recurrentes.
        @endif
      </div>

      <div class="services-table">
        <div class="st-head">Servicios incluidos en la cuota (recurrente)</div>

        @forelse($recurrentes as $s)
          @php
            $line = $calcLine($s);
            $nombre = $s['nombre'] ?? 'Servicio mensual';
            if (($s['unidades'] ?? 1) > 1) $nombre .= " (x{$s['unidades']})";

            $periodos = (int) ($line['promo_meses'] ?? 0);

            // =========================
            // FIX: En inicio diferido NO usamos meses de calendario.
            // Mostramos meses relativos a la activación.
            // - Si mes 1 es gratis => promo empieza en mes 2.
            // =========================
            $promoRango = null;
            $desdeNormal = null;
            $desdeNormalTxt = null;

            if ($periodos > 0) {
              if ($inicioDiferido) {
                $inicioPromoRel = (($line['cobro'] ?? '') === 'gratis') ? 2 : 1;
                $promoRango = $fmtRangoRelativo($inicioPromoRel, $periodos);
                $mesNormalDesdeRel = $inicioPromoRel + $periodos; // ej: promo 1-2 => normal desde 3
                $desdeNormalTxt = "A partir del mes {$mesNormalDesdeRel} desde activación: {$fmtMoney($line['base'])} € + IVA";
              } else {
                $promoRango = $fmtRangoMeses($primerCobroFecha, $periodos);
                $desdeNormal = $primerCobroFecha->copy()->addMonthsNoOverflow($periodos)->startOfMonth();
              }
            }

            $txtPromo = 'Promoción';
            if (($line['promo_tipo'] ?? null) === 'porcentaje' && (float)($line['promo_valor'] ?? 0) > 0) {
              $txtPromo = 'Dto. ' . (float)$line['promo_valor'] . '%';
            } elseif (in_array(($line['promo_tipo'] ?? null), ['fijo','importe','euros'], true) && (float)($line['promo_valor'] ?? 0) > 0) {
              $txtPromo = 'Dto. ' . number_format((float)$line['promo_valor'], 2, ',', '.') . ' €';
            }

            $finalConIva = $line['final'] * $factorIva;
            $baseConIva = $line['base'] * $factorIva;
          @endphp

          <div class="st-row">
            <div>
              <div class="st-name">{{ $nombre }}</div>
              <span class="st-meta">
                Cobro mensual.
                @if($inicioDiferido)
                  <span class="promo-note">Inicio diferido: se facturará cuando se active el servicio.</span>
                @else
                  @if(($line['cobro'] ?? '') === 'gratis')
                    <span class="text-green">Primer mes gratis (0,00 €)</span>.
                  @elseif(($line['cobro'] ?? '') === 'prorrata')
                    Primer periodo en prorrata.
                  @elseif(($line['cobro'] ?? '') === 'completo')
                    Primer periodo mes completo.
                  @endif
                @endif
              </span>

              {{-- Promo info --}}
              @if(($line['discount'] ?? 0) > 0.001)
                <span class="st-meta">
                  <span class="text-orange">{{ $txtPromo }}</span>
                  @if($periodos > 0 && $promoRango)
                    <span class="promo-note">({{ $promoRango }})</span>
                  @endif

                  @if($inicioDiferido)
                    @if($desdeNormalTxt)
                      <span class="future-note"> · {{ $desdeNormalTxt }}</span>
                    @endif
                  @else
                    @if($desdeNormal)
                      <span class="future-note"> · A partir de {{ $fmtMesAnio($desdeNormal) }}: {{ $fmtMoney($line['base']) }} € + IVA</span>
                    @endif
                  @endif
                </span>
              @endif
            </div>

            <div class="st-price">
              {{ $fmtMoney($finalConIva) }} €
              <small>IVA inc.</small>

              @if(($line['discount'] ?? 0) > 0.001)
                <div class="old">{{ $fmtMoney($baseConIva) }} €</div>
              @endif
            </div>
          </div>

        @empty
          <div class="box-body">No hay servicios recurrentes en esta contratación.</div>
        @endforelse
      </div>

      {{-- A PAGAR HOY --}}
      @if($inicioDiferido)
        <div class="paytoday free">
          <div>
            <div class="lbl">A PAGAR HOY:</div>
            <div class="amt">0,00 €</div>
          </div>
          <div class="sub">
            <span class="muted">Inicio diferido:</span> se facturará cuando se active el servicio.
          </div>
        </div>
      @elseif($ceroPorMesGratis)
        <div class="paytoday free">
          <div>
            <div class="lbl">A PAGAR HOY:</div>
            <div class="amt">0,00 €</div>
          </div>
          <div class="sub">
            <span class="muted">Primer mes gratis.</span><br>
            Próximo cargo: {{ $textoPrimerCobro }}
          </div>
        </div>
      @elseif($totalFinalMensualTax <= 0.01)
        <div class="paytoday free">
          <div>
            <div class="lbl">A PAGAR HOY:</div>
            <div class="amt">0,00 €</div>
          </div>
          <div class="sub">
            <span class="muted">No hay cuota mensual configurada.</span>
          </div>
        </div>
      @else
        <div class="paytoday paid">
          <div>
            <div class="lbl">{{ $txtCobroHoy }}</div>
            <div class="amt">{{ $fmtMoney($totalPagarHoyTax) }} €</div>
          </div>

          <div class="sub">
              IVA incluido.<br>

              @if($hayCobroProrrata)
                <span class="muted">
                  Prorrata: parte proporcional de {{ $mesActualTxt }}
                  ({{ $diasRestantes }} de {{ $diasMes }} días).
                </span><br>
              @endif

              <span class="muted">Próximo cargo (cuota mensual):</span><br>
              <strong style="color:#fcd34d;">
                {{ $fmtMoney($totalFinalMensualTax) }} €/mes
              </strong>
              <span class="muted"> — {{ $textoPrimerCobro }}</span>
            </div>

        </div>
      @endif
    </div>

    {{-- 2) ELECCIÓN MÉTODO --}}
    <div class="status-box warn">
      <div class="box-header">
        <div class="box-title" style="color:#fbbf24;">⚡ Elige cómo pagar</div>
        <div class="box-amount" style="color:#fbbf24;">
          {{ $fmtMoney($totalFinalMensualTax) }} €/mes
          <small style="font-size:11px;font-weight:800;color:var(--muted);">IVA inc.</small>
        </div>
      </div>

      <div class="box-body">
        Selecciona el método para la cuota mensual.
        <br><span class="text-white">Recomendado:</span> tarjeta para confirmación inmediata.
      </div>

      <form method="POST" action="{{ route('conversion.guardar-pago-recurrente', ['token' => $link->token]) }}" class="pay-methods">
        @csrf

        <div class="hidden-inputs">
          <input type="radio" name="recurrente_metodo" id="rm_tarjeta" value="tarjeta" @checked($preselectMetodo === 'tarjeta')>
          <input type="radio" name="recurrente_metodo" id="rm_domiciliacion" value="domiciliacion" @checked($preselectMetodo === 'domiciliacion')>

          <input type="radio" name="tarjeta_accion" id="ta_existente" value="usar_existente" @checked($preselectAccion === 'usar_existente')>
          <input type="radio" name="tarjeta_accion" id="ta_otra" value="usar_otra" @checked($preselectAccion === 'usar_otra')>
        </div>

        <div class="pm-head">Métodos disponibles</div>

@php
  // Stripe devuelve brand tipo: visa, mastercard, amex, maestro...
  $brandIcon = 'icon-card'; // fallback

  if (!empty($defaultPmBrand)) {
    $b = strtolower($defaultPmBrand);

    $brandIcon = match ($b) {
      'visa' => 'icon-visa',
      'mastercard' => 'icon-mastercard',
      'amex', 'american_express' => 'icon-amex',
      'maestro' => 'icon-maestro',
      default => 'icon-card',
    };
  }
@endphp

@if($hasDefaultCard)
  <div class="opt js-opt" data-method="tarjeta" data-action="usar_existente">
    <div class="optbox">
      <div class="opt-ico">
        <x-dynamic-component :component="$brandIcon" style="width:22px;height:22px;" />
      </div>
      <div>
        <div class="opt-title">Usar la tarjeta guardada</div>
        <div class="opt-desc">Cobro automático mensual con Stripe.</div>
        <div class="chip">✅ {{ $defaultPmLabel }}</div>
      </div>
    </div>
  </div>
@endif

<div class="opt js-opt" data-method="tarjeta" data-action="usar_otra" style="margin-top:10px;">
  <div class="optbox">
    <div class="opt-ico">
      <x-icon-card style="width:22px;height:22px;" />
    </div>
    <div>
      <div class="opt-title">{{ $tituloTarjetaNueva }}</div>
      <div class="opt-desc">{{ $descTarjetaNueva }}</div>
    </div>
  </div>
</div>

<div class="opt js-opt" data-method="domiciliacion" data-action="" style="margin-top:10px;">
  <div class="optbox">
    <div class="opt-ico">
      <x-icon-sepa style="width:22px;height:22px;" />
    </div>
    <div>
      <div class="opt-title">Domiciliación (IBAN)</div>
      <div class="opt-desc">
        Adeudo SEPA contra tu cuenta bancaria.<br>
        El cargo puede reflejarse entre el día 1 y 15.
      </div>
    </div>
  </div>
</div>

        @error('recurrente_metodo')
          <div class="err">{{ $message }}</div>
        @enderror

        @error('tarjeta_accion')
          <div class="err">{{ $message }}</div>
        @enderror

        <div class="btnrow">
          <button class="btn" type="submit">Continuar</button>
          <a class="btn btn-secondary" href="{{ $backRoute }}">Volver</a>
        </div>

        <div class="box-body" style="margin-top:10px;">
          🔒 Seguridad: la configuración de tarjeta/IBAN se gestiona con Stripe.
        </div>
      </form>
    </div>

    <div class="footer">
      <a href="https://asesorfy.net" target="_blank" rel="noopener noreferrer">Volver a la web principal</a>
    </div>

  </div>
</div>

<script>
(function () {
  const opts = document.querySelectorAll('.js-opt');
  const rmTarjeta = document.getElementById('rm_tarjeta');
  const rmSepa = document.getElementById('rm_domiciliacion');
  const taExistente = document.getElementById('ta_existente');
  const taOtra = document.getElementById('ta_otra');

  function clearSelected(){ opts.forEach(o => o.classList.remove('is-selected')); }

  function setCardActionEnabled(enabled) {
    if (!taExistente || !taOtra) return;
    taExistente.disabled = !enabled;
    taOtra.disabled = !enabled;
    if (!enabled) {
      taExistente.checked = false;
      taOtra.checked = false;
    } else {
      if (!taExistente.checked && !taOtra.checked) taOtra.checked = true;
    }
  }

  function setSelectedFromInputs() {
    clearSelected();

    const metodo = (rmSepa && rmSepa.checked) ? 'domiciliacion' : 'tarjeta';
    setCardActionEnabled(metodo === 'tarjeta');

    const accion = (taExistente && taExistente.checked) ? 'usar_existente' : 'usar_otra';

    opts.forEach(o => {
      const m = o.getAttribute('data-method');
      const a = o.getAttribute('data-action') || '';
      if (m === metodo) {
        if (metodo === 'domiciliacion') o.classList.add('is-selected');
        else if (a === accion) o.classList.add('is-selected');
      }
    });
  }

  opts.forEach(o => {
    o.addEventListener('click', () => {
      const method = o.getAttribute('data-method');
      const action = o.getAttribute('data-action') || '';

      if (method === 'domiciliacion') {
        if (rmSepa) rmSepa.checked = true;
      } else {
        if (rmTarjeta) rmTarjeta.checked = true;
        if (action === 'usar_existente' && taExistente) taExistente.checked = true;
        if (action === 'usar_otra' && taOtra) taOtra.checked = true;
      }

      setSelectedFromInputs();
    });
  });

  setSelectedFromInputs();
})();
</script>

</body>
</html>
