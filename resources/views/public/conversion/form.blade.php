<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
  <title>Alta de Cliente - AsesorFy</title>
  <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root {
      --bg-body: #f8fafc;
      --bg-card: #ffffff;
      --text-main: #0f172a;
      --text-muted: #64748b;
      --border-color: #e2e8f0;
      --primary: #0ea5e9;
      --primary-dark: #0284c7;
      --primary-light: #e0f7ff;
      --success: #22c55e;
      --warning: #f59e0b;
      --danger: #ef4444;
      --radius: 12px;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
      color: var(--text-main);
      line-height: 1.6;
      min-height: 100vh;
      padding: 20px;
    }

    .container {
      max-width: 900px;
      margin: 0 auto;
    }

    /* Header */
    .header {
      text-align: center;
      margin-bottom: 40px;
    }

    .logo {
      height: 50px;
      margin-bottom: 20px;
    }

    .header h1 {
      font-size: 28px;
      font-weight: 800;
      color: var(--text-main);
      margin-bottom: 8px;
    }

    .header p {
      font-size: 16px;
      color: var(--text-muted);
    }

    /* Stepper */
    .stepper {
      background: var(--bg-card);
      border-radius: var(--radius);
      padding: 30px 40px;
      margin-bottom: 30px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
      border: 1px solid var(--border-color);
    }

    .stepper-track {
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: relative;
      margin-bottom: 15px;
    }

    .stepper-track::before {
      content: '';
      position: absolute;
      top: 18px;
      left: 0;
      right: 0;
      height: 3px;
      background: var(--border-color);
      z-index: 0;
    }

    .stepper-track::after {
      content: '';
      position: absolute;
      top: 18px;
      left: 0;
      height: 3px;
      background: var(--primary);
      z-index: 1;
      transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .stepper-track[data-progress="1"]::after { width: 0%; }
    .stepper-track[data-progress="2"]::after { width: 25%; }
    .stepper-track[data-progress="3"]::after { width: 50%; }
    .stepper-track[data-progress="4"]::after { width: 75%; }
    .stepper-track[data-progress="5"]::after { width: 100%; }

    .step {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 10px;
      position: relative;
      z-index: 2;
      flex: 1;
    }

    .step-circle {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: var(--bg-card);
      border: 3px solid var(--border-color);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 14px;
      color: var(--text-muted);
      transition: all 0.3s ease;
    }

    .step.active .step-circle {
      background: var(--primary);
      border-color: var(--primary);
      color: white;
      transform: scale(1.1);
      box-shadow: 0 0 0 4px var(--primary-light);
    }

    .step.completed .step-circle {
      background: var(--primary);
      border-color: var(--primary);
      color: white;
    }

    .step-label {
      font-size: 13px;
      font-weight: 600;
      color: var(--text-muted);
      text-align: center;
      transition: color 0.3s ease;
    }

    .step.active .step-label {
      color: var(--primary);
    }

    .step.completed .step-label {
      color: var(--primary-dark);
    }

    /* Card */
    .card {
      background: var(--bg-card);
      border-radius: var(--radius);
      padding: 40px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
      border: 1px solid var(--border-color);
      margin-bottom: 20px;
      min-height: 500px;
    }

    /* Form Steps */
    .form-step {
      display: none;
      animation: fadeIn 0.4s ease;
    }

    .form-step.active {
      display: block;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .step-title {
      font-size: 22px;
      font-weight: 800;
      color: var(--text-main);
      margin-bottom: 8px;
    }

    .step-subtitle {
      font-size: 15px;
      color: var(--text-muted);
      margin-bottom: 30px;
    }

    /* Grid */
    .grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-bottom: 20px;
    }

    .full-width { grid-column: 1 / -1; }

    /* Field */
    .field {
      display: flex;
      flex-direction: column;
      gap: 8px;
      position: relative;
    }

    .field label {
      font-size: 14px;
      font-weight: 600;
      color: var(--text-main);
    }

    .req {
      color: var(--danger);
      margin-left: 2px;
    }

    .field input,
    .field select,
    .field textarea {
      width: 100%;
      padding: 12px 14px;
      font-size: 15px;
      font-family: inherit;
      border: 2px solid var(--border-color);
      border-radius: 10px;
      background: #fff;
      color: var(--text-main);
      transition: all 0.2s ease;
    }

    .field input:focus,
    .field select:focus,
    .field textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 4px var(--primary-light);
    }

    .field input.valid {
      border-color: var(--success);
      padding-right: 40px;
    }

    .field input.invalid {
      border-color: var(--danger);
    }

    .field-icon {
      position: absolute;
      right: 14px;
      top: 42px;
      font-size: 18px;
      pointer-events: none;
    }

    .field-icon.valid { color: var(--success); }
    .field-icon.invalid { color: var(--danger); }

    textarea {
      resize: vertical;
      min-height: 100px;
    }

    .note {
      font-size: 13px;
      color: var(--text-muted);
      margin-top: 4px;
      line-height: 1.4;
    }

    .error-msg {
      font-size: 13px;
      color: var(--danger);
      margin-top: 4px;
    }

    /* Checkbox especial */
    .checkbox-card {
      border: 2px solid var(--border-color);
      border-radius: 10px;
      padding: 16px;
      cursor: pointer;
      transition: all 0.2s ease;
      background: #f8fafc;
    }

    .checkbox-card:hover {
      border-color: var(--primary);
      background: var(--primary-light);
    }

    /* Radio Cards */
    .radio-card {
      border: 2px solid var(--border-color);
      border-radius: 12px;
      padding: 18px 20px;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      background: white;
      display: flex;
      align-items: center;
      gap: 14px;
      position: relative;
    }

    .radio-card:hover {
      border-color: var(--primary);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(14, 165, 233, 0.15);
    }

    .radio-card input[type="radio"] {
      width: 22px;
      height: 22px;
      accent-color: var(--primary);
      cursor: pointer;
      flex-shrink: 0;
    }

    .radio-card label {
      cursor: pointer;
      margin: 0;
      font-weight: 600;
      font-size: 15px;
      flex: 1;
      user-select: none;
    }

    .radio-card input[type="radio"]:checked + label {
      color: var(--primary-dark);
    }

    .radio-card:has(input[type="radio"]:checked) {
      border-color: var(--primary);
      background: linear-gradient(135deg, #e0f7ff 0%, #f0f9ff 100%);
      box-shadow: 0 4px 12px rgba(14, 165, 233, 0.25), 0 0 0 3px rgba(14, 165, 233, 0.1);
      transform: translateY(-2px);
    }

    .radio-card:has(input[type="radio"]:checked)::after {
      content: '✓';
      position: absolute;
      right: 18px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--primary);
      font-weight: 900;
      font-size: 20px;
    }

    .checkbox-card input[type="checkbox"] {
      width: 20px;
      height: 20px;
      accent-color: var(--primary);
      cursor: pointer;
    }

    .checkbox-card label {
      cursor: pointer;
      margin-left: 10px;
      font-weight: 600;
    }

    /* Hidden field */
    .hidden-field { display: none !important; }

    /* Buttons */
    .actions {
      display: flex;
      justify-content: space-between;
      gap: 15px;
      margin-top: 40px;
      padding-top: 30px;
      border-top: 1px solid var(--border-color);
    }

    .btn {
      padding: 14px 28px;
      font-size: 15px;
      font-weight: 700;
      border-radius: 10px;
      border: none;
      cursor: pointer;
      transition: all 0.2s ease;
      font-family: inherit;
    }

    .btn-secondary {
      background: #f1f5f9;
      color: var(--text-main);
    }

    .btn-secondary:hover {
      background: #e2e8f0;
    }

    .btn-primary {
      background: var(--primary);
      color: white;
      box-shadow: 0 4px 6px -1px rgba(48, 213, 200, 0.3);
    }

    .btn-primary:hover {
      background: var(--primary-dark);
      transform: translateY(-1px);
      box-shadow: 0 6px 12px -2px rgba(48, 213, 200, 0.4);
    }

    .btn:disabled {
      opacity: 0.5;
      cursor: not-allowed;
      transform: none !important;
    }

    /* Errors */
    .errors-box {
      background: #fef2f2;
      border: 2px solid #fecaca;
      color: #991b1b;
      padding: 16px 20px;
      border-radius: 10px;
      margin-bottom: 25px;
      font-size: 14px;
    }

    .errors-box strong {
      display: block;
      margin-bottom: 8px;
    }

    .errors-box ul {
      list-style: none;
      padding-left: 0;
    }

    .errors-box li::before {
      content: "• ";
      color: #dc2626;
      font-weight: bold;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .grid { grid-template-columns: 1fr; }
      .stepper { padding: 20px; }
      .card { padding: 25px; }
      .step-label { font-size: 11px; }
      .actions { flex-direction: column-reverse; }
      .btn { width: 100%; }
    }
  </style>
</head>

@php
  $prefilled = $prefilled ?? [];
  $getValue = function($campo, $leadField = null) use ($prefilled, $lead) {
      $default = $prefilled[$campo] ?? ($leadField ? $lead->$leadField : '');
      return old($campo, $default);
  };
  $provinciasMap = config('provincias.provincias');

  // Detectar servicios especiales
  $saleBlueprint = $link->meta['sale_blueprint'] ?? [];
  $servicios = $saleBlueprint['servicios'] ?? [];
  
  $tieneRecurrente = collect($servicios)->contains(fn($s) => ($s['tipo'] ?? '') === 'recurrente');
  $tieneAltaAutonomo = collect($servicios)->contains(fn($s) => ($s['es_alta_autonomo'] ?? false) === true);
  $tieneCreacionSociedad = collect($servicios)->contains(fn($s) => ($s['es_creacion_sociedad'] ?? false) === true);
  $tieneCapitalizacion = collect($servicios)->contains(fn($s) => ($s['es_capitalizacion'] ?? false) === true);
@endphp

<body>



<div class="container">
  <!-- Header -->
  <div class="header">
    <img src="{{ asset('images/logo.png') }}" alt="AsesorFy" class="logo">
    <h1>Alta de Cliente</h1>
    <p>Completa tus datos en unos sencillos pasos</p>
  </div>

  <!-- Stepper -->
  <div class="stepper">
    <div class="stepper-track" data-progress="1">
      <div class="step active" data-step="1">
        <div class="step-circle">1</div>
        <div class="step-label">Contacto</div>
      </div>
      <div class="step" data-step="2">
        <div class="step-circle">2</div>
        <div class="step-label">Fiscal</div>
      </div>
      <div class="step" data-step="3">
        <div class="step-circle">3</div>
        <div class="step-label">Dirección</div>
      </div>
      <div class="step" data-step="4">
        <div class="step-circle">4</div>
        <div class="step-label">Impuestos</div>
      </div>
      <div class="step" data-step="5">
        <div class="step-circle">5</div>
        <div class="step-label">Resumen</div>
      </div>
    </div>
  </div>

  <!-- Resumen Servicios -->
  @php
    $serviciosResumen = [];
    
    // Obtener servicios desde $link->meta['sale_blueprint']['servicios']
    $saleBlueprint = $link->meta['sale_blueprint'] ?? [];
    
    if (isset($saleBlueprint['servicios']) && is_array($saleBlueprint['servicios'])) {
      foreach ($saleBlueprint['servicios'] as $servicio) {
        $serviciosResumen[] = [
          'nombre' => $servicio['nombre'] ?? 'Servicio',
          'tipo' => $servicio['tipo'] ?? 'unico',
          'unidades' => $servicio['unidades'] ?? 1
        ];
      }
    }
    
    $tieneRecurrenteResumen = collect($serviciosResumen)->contains(fn($s) => $s['tipo'] === 'recurrente');
    $tieneUnicoResumen = collect($serviciosResumen)->contains(fn($s) => $s['tipo'] !== 'recurrente');
  @endphp

  @if(!empty($serviciosResumen))
  <div style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-radius: 12px; padding: 20px 30px; margin-bottom: 25px; border: 2px solid #0ea5e9;">
    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 14px;">
      <div style="background: #0ea5e9; color: white; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 17px;">✓</div>
      <div style="font-size: 17px; font-weight: 800; color: #0f172a;">Servicios que vas a contratar</div>
    </div>
    
    <div style="display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 12px;">
      @foreach($serviciosResumen as $servicio)
        @php
          $colorBg = $servicio['tipo'] === 'recurrente' ? '#dbeafe' : '#dcfce7';
          $colorText = $servicio['tipo'] === 'recurrente' ? '#1e40af' : '#166534';
          $colorBorder = $servicio['tipo'] === 'recurrente' ? '#3b82f6' : '#22c55e';
          $icon = $servicio['tipo'] === 'recurrente' ? '🔄' : '⚡';
        @endphp
        
        <div style="background: {{ $colorBg }}; color: {{ $colorText }}; padding: 8px 16px; border-radius: 20px; font-size: 14px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; border: 2px solid {{ $colorBorder }};">
          <span>{{ $icon }}</span>
          <span>{{ $servicio['nombre'] }}</span>
          @if($servicio['unidades'] > 1)
            <span style="opacity: 0.7;">(x{{ $servicio['unidades'] }})</span>
          @endif
        </div>
      @endforeach
    </div>
    
    <div style="font-size: 13px; color: #64748b; display: flex; gap: 20px; flex-wrap: wrap;">
      @if($tieneRecurrenteResumen)
        <div style="display: flex; align-items: center; gap: 6px;">
          <span style="color: #3b82f6; font-weight: 800;">🔄</span>
          <span><strong>Recurrente:</strong> Cuota mensual</span>
        </div>
      @endif
      @if($tieneUnicoResumen)
        <div style="display: flex; align-items: center; gap: 6px;">
          <span style="color: #22c55e; font-weight: 800;">⚡</span>
          <span><strong>Único:</strong> Pago puntual</span>
        </div>
      @endif
    </div>
  </div>
  @endif

  <!-- Errors -->
  @if ($errors->any())
    <div class="errors-box">
      <strong>⚠️ Por favor corrige los siguientes errores:</strong>
      <ul>
        @foreach($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <!-- Form -->
  <form method="POST" action="{{ route('conversion.submit', ['token' => $link->token]) }}" id="mainForm">
    @csrf

    <div class="card">
      
      <!-- PASO 1: Contacto -->
      <div class="form-step active" data-step="1">
        <div class="step-title">Datos de Contacto</div>
        <div class="step-subtitle">Información básica de la persona de contacto</div>

        <div class="grid">
          <div class="field">
            <label>Nombre <span class="req">*</span></label>
            <input type="text" name="nombre" id="nombre" value="{{ $getValue('nombre', 'nombre') }}" required placeholder="Tu nombre">
            <span class="field-icon"></span>
            @error('nombre') <div class="error-msg">{{ $message }}</div> @enderror
          </div>

          <div class="field">
            <label>Apellidos <span class="req">*</span></label>
            <input type="text" name="apellidos" id="apellidos" value="{{ $getValue('apellidos', 'apellidos') }}" required placeholder="Tus apellidos">
            <span class="field-icon"></span>
            @error('apellidos') <div class="error-msg">{{ $message }}</div> @enderror
          </div>

          <div class="field">
            <label>Email <span class="req">*</span></label>
            <input type="email" name="email" id="email" value="{{ $getValue('email', 'email') }}" required placeholder="email@ejemplo.com">
            <span class="field-icon"></span>
            @error('email') <div class="error-msg">{{ $message }}</div> @enderror
          </div>

          <div class="field">
            <label>Teléfono Móvil <span class="req">*</span></label>
            <input type="tel" name="telefono" id="telefono" value="{{ $getValue('telefono', 'tfn') }}" required placeholder="600 000 000">
            <span class="field-icon"></span>
            @error('telefono') <div class="error-msg">{{ $message }}</div> @enderror
          </div>
        </div>
      </div>

      <!-- PASO 2: Fiscal -->
      <div class="form-step" data-step="2">
        <div class="step-title">Datos Fiscales</div>
        <div class="step-subtitle">Información fiscal del titular</div>

        <div class="grid">
          <div class="field full-width">
            <label>Tipo de Cliente <span class="req">*</span></label>
            <select name="tipo_cliente_id" id="tipo_cliente_select" required>
              <option value="" selected disabled>Selecciona tu situación...</option>
              @foreach($tipos as $tipo)
                @php
                  $selectedId = old('tipo_cliente_id', $prefilled['tipo_cliente_id'] ?? null);
                  $isAutonomo = stripos($tipo->nombre, 'autonomo') !== false ? 'true' : 'false';
                @endphp
                <option value="{{ $tipo->id }}" 
                        data-is-autonomo="{{ $isAutonomo }}"
                        @selected($selectedId == $tipo->id)>
                  {{ $tipo->nombre }}
                </option>
              @endforeach
            </select>
          </div>

          <!-- Radios "Constituida vs En constitución" (solo para sociedades) -->
          <div class="field full-width hidden-field" id="bloque_radios_constitucion">
            <label style="margin-bottom: 12px; display: block;">Estado de la sociedad <span class="req">*</span></label>
            
            <div class="radio-card" style="margin-bottom: 12px;">
              <input type="radio" name="estado_sociedad" id="sociedad_constituida" value="constituida" @checked(old('estado_sociedad') === 'constituida')>
              <label for="sociedad_constituida">La sociedad ya está constituida (tengo CIF)</label>
            </div>

            <div class="radio-card">
              <input type="radio" name="estado_sociedad" id="sociedad_en_constitucion" value="en_constitucion" @checked(old('estado_sociedad') === 'en_constitucion')>
              <label for="sociedad_en_constitucion">La sociedad está en constitución (no tengo CIF aún)</label>
            </div>
            
            <div class="note" id="note_constitucion" style="display: none; margin-top: 12px;">
              💡 El contrato se firmará a tu nombre personal hasta que la sociedad esté constituida.
            </div>
          </div>

          <!-- DNI (para autónomos y sociedades) -->
          <div class="field hidden-field" id="bloque_dni">
            <label id="label_dni">DNI / NIE <span class="req">*</span></label>
            <input type="text" name="dni" id="input_dni" value="{{ $getValue('dni', 'dni') }}" placeholder="12345678Z">
            <span class="field-icon"></span>
            @error('dni') <div class="error-msg">{{ $message }}</div> @enderror
          </div>

          <!-- Nombre Representante (solo sociedades constituidas) -->
          <div class="field hidden-field" id="bloque_nombre_representante">
            <label>Nombre (Representante) <span class="req">*</span></label>
            <input type="text" name="nombre_representante" id="input_nombre_representante" value="{{ $getValue('nombre_representante', 'nombre') }}" placeholder="Nombre">
            <span class="field-icon"></span>
            @error('nombre_representante') <div class="error-msg">{{ $message }}</div> @enderror
          </div>

          <!-- Apellidos Representante (solo sociedades constituidas) -->
          <div class="field hidden-field" id="bloque_apellidos_representante">
            <label>Apellidos (Representante) <span class="req">*</span></label>
            <input type="text" name="apellidos_representante" id="input_apellidos_representante" value="{{ $getValue('apellidos_representante', 'apellidos') }}" placeholder="Apellidos">
            <span class="field-icon"></span>
            @error('apellidos_representante') <div class="error-msg">{{ $message }}</div> @enderror
          </div>

          <!-- CIF (solo sociedades constituidas) -->
          <div class="field hidden-field" id="bloque_cif">
            <label>CIF Empresa <span class="req">*</span></label>
            <input type="text" name="cif" id="input_cif" value="{{ $getValue('cif') }}" placeholder="B12345678">
            <span class="field-icon"></span>
            @error('cif') <div class="error-msg">{{ $message }}</div> @enderror
          </div>

          <!-- Razón Social -->
          <div class="field hidden-field" id="bloque_razon_social">
            <label id="label_razon_social">Razón Social <span class="req">*</span></label>
            <input type="text" name="razon_social" id="input_razon_social" value="{{ $getValue('razon_social') }}" placeholder="Nombre Fiscal">
            <span class="field-icon"></span>
            @error('razon_social') <div class="error-msg">{{ $message }}</div> @enderror
            <div class="note" id="note_razon_social"></div>
          </div>

          <!-- Nombre Comercial (autónomos y empresas constituidas) -->
          <div class="field hidden-field" id="bloque_nombre_comercial">
            <label id="label_nombre_comercial">Nombre Comercial / Marca (Opcional)</label>
            <input type="text" name="nombre_comercial" id="input_nombre_comercial" value="{{ old('nombre_comercial') }}" placeholder="Nombre de tu marca">
          </div>

        </div>
      </div>

      <!-- PASO 3: Dirección -->
      <div class="form-step" data-step="3">
        <div class="step-title">Dirección Fiscal</div>
        <div class="step-subtitle">Domicilio fiscal del titular</div>

        <div class="grid">
          <div class="field full-width">
            <label>Dirección Completa <span class="req">*</span></label>
            <input type="text" name="direccion" id="direccion" value="{{ $getValue('direccion') }}" required placeholder="Calle, número, piso...">
            <span class="field-icon"></span>
            @error('direccion') <div class="error-msg">{{ $message }}</div> @enderror
          </div>

          <div class="field">
            <label>Código Postal <span class="req">*</span></label>
            <input type="text" name="cp" id="cp" value="{{ $getValue('cp') }}" required placeholder="28000">
            <span class="field-icon"></span>
          </div>

          <div class="field">
            <label>Localidad <span class="req">*</span></label>
            <input type="text" name="localidad" id="localidad" value="{{ $getValue('localidad') }}" required placeholder="Madrid">
            <span class="field-icon"></span>
          </div>

          <div class="field">
            <label>Provincia <span class="req">*</span></label>
            <select id="provincia" name="provincia" required>
              <option value="">Selecciona...</option>
              @foreach(array_keys($provinciasMap) as $prov)
                <option value="{{ $prov }}" @selected($getValue('provincia') == $prov)>{{ $prov }}</option>
              @endforeach
            </select>
          </div>

          <div class="field">
            <label>Comunidad Autónoma</label>
            <input type="text" id="comunidad_autonoma" name="comunidad_autonoma" value="{{ $getValue('comunidad_autonoma') }}" readonly style="background:#f1f5f9;">
          </div>
        </div>
      </div>

      <!-- PASO 4: Servicios -->
      <div class="form-step" data-step="4">
        <div class="step-title">Facturación y Servicios</div>
        <div class="step-subtitle">Datos bancarios y complementarios</div>

        @if($tieneRecurrente)
        <div class="grid">
          <div class="field full-width">
            <label>Cuenta IBAN (Impuestos / SS) <span class="req">*</span></label>
            <input type="text" id="cuenta_bancaria_ss" name="cuenta_bancaria_ss" value="{{ $getValue('cuenta_bancaria_ss') }}" required placeholder="ES..." style="text-transform: uppercase; letter-spacing: 1px; font-family: monospace;">
            <div id="ibanFeedback" style="font-size: 13px; margin-top: 4px; min-height: 18px;"></div>
            <div class="note" id="note_iban_general">Este IBAN se usará para gestiones con Hacienda y Seguridad Social.</div>
            <div class="note hidden-field" id="note_iban_constitucion" style="background: #fef3c7; padding: 12px; border-left: 3px solid #f59e0b; border-radius: 6px;">
              ⚠️ <strong>Importante:</strong> Esta cuenta será modificada por tu asesor una vez la sociedad esté constituida y tenga cuenta bancaria corporativa abierta.
            </div>
            <div class="note hidden-field" id="note_iban_empresa" style="background: #dbeafe; padding: 12px; border-left: 3px solid #3b82f6; border-radius: 6px;">
              💼 <strong>Importante:</strong> Debe ser la cuenta bancaria de titularidad de la empresa para el cargo de impuestos.
            </div>
            @error('cuenta_bancaria_ss') <div class="error-msg">{{ $message }}</div> @enderror
          </div>
        </div>
        @endif

        <div class="grid">
          <div class="field full-width">
            <label>Observaciones (Opcional)</label>
            <textarea name="observaciones" placeholder="¿Necesitas contarnos algo más?">{{ $getValue('observaciones') }}</textarea>
          </div>
        </div>

        {{-- Secciones extras: Alta autónomo, Creación SL, Capitalización --}}
        @if($tieneAltaAutonomo)
        <div style="border: 2px solid #bbf7d0; background: #f0fdf4; border-radius: 12px; padding: 25px; margin-top: 30px;">
          <div style="font-size: 18px; font-weight: 800; color: #166534; margin-bottom: 20px;">
            🚀 Datos para Alta de Autónomo
          </div>
          <div class="grid">
            <div class="field">
              <label>Fecha Inicio Actividad <span class="req">*</span></label>
              <input type="date" name="extra_auto_fecha_inicio" value="{{ old('extra_auto_fecha_inicio') }}" required>
            </div>
            <div class="field">
              <label>Fecha de Nacimiento <span class="req">*</span></label>
              <input type="date" name="fecha_nacimiento" value="{{ $getValue('fecha_nacimiento') }}" required>
              @error('fecha_nacimiento') <div class="error-msg">{{ $message }}</div> @enderror
            </div>
            <div class="field">
              <label>Nº Seguridad Social <span class="req">*</span></label>
              <input type="text" name="seguridad_social" value="{{ $getValue('seguridad_social') }}" required placeholder="Número SS">
              @error('seguridad_social') <div class="error-msg">{{ $message }}</div> @enderror
            </div>
            <div class="field">
              <label>¿Tienes certificado digital? <span class="req">*</span></label>
              <select name="extra_auto_certificado_digital" required>
                <option value="">Selecciona...</option>
                <option value="si" @selected(old('extra_auto_certificado_digital') === 'si')>Sí</option>
                <option value="no" @selected(old('extra_auto_certificado_digital') === 'no')>No</option>
              </select>
            </div>
            <div class="field full-width">
              <label>Descripción Actividad <span class="req">*</span></label>
              <textarea name="extra_auto_actividad" required placeholder="Ej: Programador web freelance">{{ old('extra_auto_actividad') }}</textarea>
            </div>
            <div class="field">
              <label>Lugar de trabajo <span class="req">*</span></label>
              <select name="extra_auto_lugar" required>
                <option value="casa" @selected(old('extra_auto_lugar') === 'casa')>En mi domicilio</option>
                <option value="local" @selected(old('extra_auto_lugar') === 'local')>Local propio/alquilado</option>
                <option value="cliente" @selected(old('extra_auto_lugar') === 'cliente')>En cliente / Itinerante</option>
              </select>
            </div>
            <div class="field">
              <label>¿Has sido autónomo antes? <span class="req">*</span></label>
              <select name="extra_auto_tarifa_plana" required>
                <option value="no" @selected(old('extra_auto_tarifa_plana') === 'no')>No (Solicitar Tarifa Plana)</option>
                <option value="si" @selected(old('extra_auto_tarifa_plana') === 'si')>Sí, hace menos de 2 años</option>
              </select>
            </div>
          </div>
        </div>
        @endif

        @if($tieneCreacionSociedad)
        <div style="border: 2px solid #ddd6fe; background: #f5f3ff; border-radius: 12px; padding: 25px; margin-top: 30px;">
          <div style="font-size: 18px; font-weight: 800; color: #5b21b6; margin-bottom: 20px;">
            🏗️ Datos Constitución S.L.
          </div>
          <div class="grid">
            <div class="field full-width">
              <label>5 nombres de preferencia <span class="req">*</span></label>
              <div style="display: flex; flex-direction: column; gap: 10px;">
                <input type="text" name="extra_sl_nombre1" value="{{ old('extra_sl_nombre1') }}" placeholder="1. Opción principal" required>
                <input type="text" name="extra_sl_nombre2" value="{{ old('extra_sl_nombre2') }}" placeholder="2. Opción">
                <input type="text" name="extra_sl_nombre3" value="{{ old('extra_sl_nombre3') }}" placeholder="3. Opción">
                <input type="text" name="extra_sl_nombre4" value="{{ old('extra_sl_nombre4') }}" placeholder="4. Opción">
                <input type="text" name="extra_sl_nombre5" value="{{ old('extra_sl_nombre5') }}" placeholder="5. Opción">
              </div>
            </div>

            <div class="field">
              <label>Tipo de aportación <span class="req">*</span></label>
              <select name="extra_sl_aportacion_tipo" id="aportacion_tipo" required>
                <option value="">Selecciona...</option>
                <option value="dineraria" @selected(old('extra_sl_aportacion_tipo') === 'dineraria')>Dineraria</option>
                <option value="bienes" @selected(old('extra_sl_aportacion_tipo') === 'bienes')>Bienes</option>
                <option value="mixta" @selected(old('extra_sl_aportacion_tipo') === 'mixta')>Mixta</option>
              </select>
            </div>

            <div class="field" id="bloque_capital_dinerario">
              <label>Capital social (€) <span class="req">*</span></label>
              <input type="number" name="extra_sl_capital" step="0.01" value="{{ old('extra_sl_capital', 3000) }}">
            </div>

            <div class="field full-width hidden-field" id="bloque_bienes_aportar">
              <label>Bienes a aportar</label>
              <textarea name="extra_sl_bienes_descripcion" rows="3" placeholder="Describe los bienes...">{{ old('extra_sl_bienes_descripcion') }}</textarea>
            </div>

            <div class="field full-width">
              <label>Actividad principal <span class="req">*</span></label>
              <input type="text" name="extra_sl_actividad" value="{{ old('extra_sl_actividad') }}" required>
            </div>

            <div class="field full-width">
              <label>Socios y porcentajes <span class="req">*</span></label>
              <div id="socios_repeater">
                @php $oldSocios = old('extra_sl_socios_nombre', ['']); @endphp
                @foreach($oldSocios as $index => $socioVal)
                <div class="socio-row" style="border: 1px solid #e2e8f0; border-radius: 10px; padding: 15px; margin-bottom: 12px; background: white;">
                  <div class="grid">
                    <div class="field">
                      <label>Nombre y apellidos <span class="req">*</span></label>
                      <input type="text" name="extra_sl_socios_nombre[]" value="{{ old('extra_sl_socios_nombre.'.$index) }}" required>
                    </div>
                    <div class="field">
                      <label>DNI / NIE</label>
                      <input type="text" name="extra_sl_socios_dni[]" value="{{ old('extra_sl_socios_dni.'.$index) }}">
                    </div>
                    <div class="field full-width">
                      <label>% Participación <span class="req">*</span></label>
                      <div style="display: grid; grid-template-columns: 110px auto 220px; align-items: center; gap: 12px;">
                        <input type="number" name="extra_sl_socios_porcentaje[]" class="socio-porcentaje" min="0" max="100" step="0.01" placeholder="0-100" value="{{ old('extra_sl_socios_porcentaje.'.$index) }}" required>
                        <label style="display: inline-flex; align-items: center; gap: 8px; margin: 0;">
                          <input type="checkbox" class="socio-casado-toggle"> ¿Casado/a?
                        </label>
                        <select name="extra_sl_socios_regimen[]" class="socio-regimen-select hidden-field">
                          <option value="">Régimen...</option>
                          <option value="gananciales" @selected(old('extra_sl_socios_regimen.'.$index) == 'gananciales')>Gananciales</option>
                          <option value="separacion_bienes" @selected(old('extra_sl_socios_regimen.'.$index) == 'separacion_bienes')>Separación de bienes</option>
                          <option value="participacion" @selected(old('extra_sl_socios_regimen.'.$index) == 'participacion')>Participación</option>
                        </select>
                      </div>
                    </div>
                  </div>
                </div>
                @endforeach
              </div>
              <button type="button" id="add_socio_btn" class="btn btn-secondary" style="padding: 10px 16px; font-size: 14px;">+ Añadir otro socio</button>
            </div>

            <div class="field">
              <label>Tipo de administrador <span class="req">*</span></label>
              <select name="extra_sl_tipo_admin" required>
                <option value="unico" @selected(old('extra_sl_tipo_admin') === 'unico')>Administrador único</option>
                <option value="solidarios" @selected(old('extra_sl_tipo_admin') === 'solidarios')>Solidarios</option>
                <option value="mancomunados" @selected(old('extra_sl_tipo_admin') === 'mancomunados')>Mancomunados</option>
              </select>
            </div>

            <div class="field">
              <label>Nombre del administrador <span class="req">*</span></label>
              <input type="text" name="extra_sl_admin_nombre" value="{{ old('extra_sl_admin_nombre') }}" required>
            </div>

            <div class="field">
              <label>Ciudad de firma <span class="req">*</span></label>
              <input type="text" name="extra_sl_ciudad_firma" value="{{ old('extra_sl_ciudad_firma') }}" required>
            </div>
          </div>
        </div>
        @endif

        @if($tieneCapitalizacion)
        <div style="border: 2px solid #fde68a; background: #fffbeb; border-radius: 12px; padding: 25px; margin-top: 30px;">
          <div style="font-size: 18px; font-weight: 800; color: #b45309; margin-bottom: 20px;">
            💰 Capitalización del Paro
          </div>
          <div class="grid">
            <div class="field full-width">
              <label>Destino de la inversión <span class="req">*</span></label>
              <select name="extra_cap_forma_juridica" required>
                <option value="autonomo" @selected(old('extra_cap_forma_juridica') === 'autonomo')>Hacerme Autónomo</option>
                <option value="sociedad" @selected(old('extra_cap_forma_juridica') === 'sociedad')>Aportación a Sociedad</option>
              </select>
            </div>
            <div class="field">
              <label>Inversión prevista (€) <span class="req">*</span></label>
              <input type="number" name="extra_cap_inversion" step="0.01" value="{{ old('extra_cap_inversion') }}" required>
            </div>
            <div class="field">
              <label>Paro que quieres solicitar (€)</label>
              <input type="number" name="extra_cap_solicitado" step="0.01" value="{{ old('extra_cap_solicitado') }}">
            </div>
            <div class="field full-width">
              <label>Modalidad de cobro <span class="req">*</span></label>
              <select name="extra_cap_modalidad" required>
                <option value="pago_unico" @selected(old('extra_cap_modalidad') === 'pago_unico')>Pago Único (100%)</option>
                <option value="cuotas" @selected(old('extra_cap_modalidad') === 'cuotas')>Cuotas mensuales</option>
                <option value="mixto" @selected(old('extra_cap_modalidad') === 'mixto')>Mixto</option>
                <option value="no_lo_se" @selected(old('extra_cap_modalidad') === 'no_lo_se')>No lo sé</option>
              </select>
            </div>
            <div class="field full-width">
              <label>Memoria del proyecto <span class="req">*</span></label>
              <textarea name="extra_cap_memoria" required placeholder="Describe brevemente...">{{ old('extra_cap_memoria') }}</textarea>
            </div>
            <div class="field">
              <label>Fecha inicio paro <span class="req">*</span></label>
              <input type="date" name="extra_cap_fecha_paro" value="{{ old('extra_cap_fecha_paro') }}" required>
            </div>
            <div class="field">
              <label>Prestación mensual (€) <span class="req">*</span></label>
              <input type="text" name="extra_cap_prestacion_mensual" value="{{ old('extra_cap_prestacion_mensual') }}" required>
            </div>
            <div class="field">
              <label>Duración paro (meses) <span class="req">*</span></label>
              <input type="number" name="extra_cap_duracion_paro" min="1" value="{{ old('extra_cap_duracion_paro') }}" required>
            </div>
            <div class="field">
              <label>Oficina SEPE</label>
              <input type="text" name="extra_cap_oficina_sepe" value="{{ old('extra_cap_oficina_sepe') }}">
            </div>
          </div>
        </div>
        @endif
      </div>

      <!-- PASO 5: Resumen -->
      <div class="form-step" data-step="5">
        <div class="step-title">Resumen</div>
        <div class="step-subtitle">Revisa que todos los datos sean correctos</div>

        <div id="resumen-contenido" style="background: #f8fafc; border-radius: 10px; padding: 25px; font-size: 15px; line-height: 1.8;">
          <!-- Se llenará con JavaScript -->
        </div>
      </div>

      <!-- Navigation -->
      <div class="actions">
        <button type="button" class="btn btn-secondary" id="prevBtn" style="display: none;">← Atrás</button>
        <div></div>
        <button type="button" class="btn btn-primary" id="nextBtn">Siguiente →</button>
        <button type="submit" class="btn btn-primary" id="submitBtn" style="display: none;">Guardar y Continuar</button>
      </div>

    </div>
  </form>
</div>

<script>
(function() {
  let currentStep = 1;
  const totalSteps = 5;

  const steps = document.querySelectorAll('.form-step');
  const stepCircles = document.querySelectorAll('.step');
  const stepperTrack = document.querySelector('.stepper-track');
  const prevBtn = document.getElementById('prevBtn');
  const nextBtn = document.getElementById('nextBtn');
  const submitBtn = document.getElementById('submitBtn');

  // Navegación
  function showStep(n) {
    steps.forEach(s => s.classList.remove('active'));
    stepCircles.forEach(s => {
      s.classList.remove('active');
      if (parseInt(s.dataset.step) < n) s.classList.add('completed');
      else s.classList.remove('completed');
    });

    steps[n - 1].classList.add('active');
    stepCircles[n - 1].classList.add('active');
    stepperTrack.setAttribute('data-progress', n);

    prevBtn.style.display = n === 1 ? 'none' : 'inline-block';
    nextBtn.style.display = n === totalSteps ? 'none' : 'inline-block';
    submitBtn.style.display = n === totalSteps ? 'inline-block' : 'none';

    if (n === totalSteps) {
      generarResumen();
    }

    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  nextBtn.addEventListener('click', () => {
    // Validar campos obligatorios del paso actual
    const currentStepEl = document.querySelector(`.form-step[data-step="${currentStep}"]`);
    const requiredInputs = currentStepEl.querySelectorAll('input[required]:not(.hidden-field input), select[required]:not(.hidden-field select), textarea[required]:not(.hidden-field textarea)');
    
    let isValid = true;
    let firstInvalidField = null;

    requiredInputs.forEach(input => {
      // Saltar si el campo está en un bloque oculto
      if (input.closest('.hidden-field')) return;
      
      if (!input.value.trim()) {
        isValid = false;
        input.classList.add('invalid');
        if (!firstInvalidField) firstInvalidField = input;
      } else {
        input.classList.remove('invalid');
      }
    });

    // Validación específica paso 2: radios sociedades
    if (currentStep === 2) {
      const tipoSelect = document.getElementById('tipo_cliente_select');
      if (!tipoSelect.value) {
        alert('⚠️ Por favor, selecciona el tipo de cliente');
        tipoSelect.focus();
        return;
      }

      const selectedOption = tipoSelect.options[tipoSelect.selectedIndex];
      const tipoTexto = (selectedOption.text || '').toLowerCase();
      const isAutonomo = (selectedOption.dataset.isAutonomo === 'true') || 
                         tipoTexto.includes('autónomo') || 
                         tipoTexto.includes('autonomo');
      
      if (!isAutonomo) {
        const radioConstituida = document.getElementById('sociedad_constituida');
        const radioEnConstitucion = document.getElementById('sociedad_en_constitucion');
        
        if (!radioConstituida.checked && !radioEnConstitucion.checked) {
          alert('⚠️ Por favor, selecciona el estado de la sociedad');
          return;
        }
      }
    }

    if (!isValid) {
      alert('⚠️ Por favor, completa todos los campos obligatorios antes de continuar');
      if (firstInvalidField) {
        firstInvalidField.focus();
        firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
      return;
    }

    if (currentStep < totalSteps) {
      currentStep++;
      showStep(currentStep);
    }
  });

  // Validación antes de submit
  const mainForm = document.getElementById('mainForm');
  mainForm.addEventListener('submit', function(e) {
    // Validar todos los campos obligatorios del formulario completo
    const requiredInputs = mainForm.querySelectorAll('input[required]:not(.hidden-field input), select[required]:not(.hidden-field select), textarea[required]:not(.hidden-field textarea)');
    
    let isValid = true;
    let invalidFields = [];

    requiredInputs.forEach(input => {
      // Saltar si el campo está en un bloque oculto
      if (input.closest('.hidden-field')) return;
      
      if (!input.value.trim()) {
        isValid = false;
        input.classList.add('invalid');
        invalidFields.push(input);
      }
    });

    // Validación especial: radios sociedades
    const tipoSelect = document.getElementById('tipo_cliente_select');
    if (tipoSelect && tipoSelect.value) {
      const selectedOption = tipoSelect.options[tipoSelect.selectedIndex];
      const tipoTexto = (selectedOption.text || '').toLowerCase();
      const isAutonomo = (selectedOption.dataset.isAutonomo === 'true') || 
                         tipoTexto.includes('autónomo') || 
                         tipoTexto.includes('autonomo');
      
      if (!isAutonomo) {
        const radioConstituida = document.getElementById('sociedad_constituida');
        const radioEnConstitucion = document.getElementById('sociedad_en_constitucion');
        
        if (!radioConstituida.checked && !radioEnConstitucion.checked) {
          isValid = false;
          alert('⚠️ Debes seleccionar el estado de la sociedad');
          // Volver al paso 2
          currentStep = 2;
          showStep(currentStep);
          e.preventDefault();
          return;
        }
      }
    }

    if (!isValid) {
      e.preventDefault();
      alert('⚠️ Hay campos obligatorios sin completar. Por favor, revisa el formulario.');
      
      // Ir al primer paso con errores
      if (invalidFields.length > 0) {
        const firstInvalid = invalidFields[0];
        const stepEl = firstInvalid.closest('.form-step');
        if (stepEl) {
          const stepNum = parseInt(stepEl.dataset.step);
          currentStep = stepNum;
          showStep(currentStep);
          setTimeout(() => {
            firstInvalid.focus();
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }, 300);
        }
      }
    }
  });

  prevBtn.addEventListener('click', () => {
    if (currentStep > 1) {
      currentStep--;
      showStep(currentStep);
    }
  });

  // Validación en tiempo real
  const inputs = document.querySelectorAll('input[required], input[type="email"]');
  inputs.forEach(input => {
    input.addEventListener('blur', () => validateField(input));
    input.addEventListener('input', () => validateField(input));
  });

  function validateField(input) {
    const icon = input.nextElementSibling;
    if (!icon || !icon.classList.contains('field-icon')) return;

    if (input.validity.valid && input.value.trim() !== '') {
      input.classList.add('valid');
      input.classList.remove('invalid');
      icon.textContent = '✓';
      icon.classList.add('valid');
      icon.classList.remove('invalid');
    } else if (input.value.trim() !== '') {
      input.classList.add('invalid');
      input.classList.remove('valid');
      icon.textContent = '✕';
      icon.classList.add('invalid');
      icon.classList.remove('valid');
    } else {
      input.classList.remove('valid', 'invalid');
      icon.textContent = '';
      icon.classList.remove('valid', 'invalid');
    }
  }

  // Lógica Tipo Cliente
  const selectTipo = document.getElementById('tipo_cliente_select');
  const bloqueRadiosConst = document.getElementById('bloque_radios_constitucion');
  const radioConstituida = document.getElementById('sociedad_constituida');
  const radioEnConstitucion = document.getElementById('sociedad_en_constitucion');
  const noteConstitucion = document.getElementById('note_constitucion');
  const bloqueDni = document.getElementById('bloque_dni');
  const bloqueCif = document.getElementById('bloque_cif');
  const inputDni = document.getElementById('input_dni');
  const inputCif = document.getElementById('input_cif');
  const labelDni = document.getElementById('label_dni');
  const bloqueRazonSocial = document.getElementById('bloque_razon_social');
  const labelRazon = document.getElementById('label_razon_social');
  const inputRazon = document.getElementById('input_razon_social');
  const noteRazon = document.getElementById('note_razon_social');
  const bloqueNombreComercial = document.getElementById('bloque_nombre_comercial');
  const labelNombreComercial = document.getElementById('label_nombre_comercial');
  const bloqueNombreRep = document.getElementById('bloque_nombre_representante');
  const bloqueApellidosRep = document.getElementById('bloque_apellidos_representante');
  const inputNombreRep = document.getElementById('input_nombre_representante');
  const inputApellidosRep = document.getElementById('input_apellidos_representante');

  function toggleTipoCliente() {
  if (!selectTipo) return;
  const selectedOption = selectTipo.options[selectTipo.selectedIndex];
  
  const tipoTexto = (selectedOption.text || '').toLowerCase();
  const isAutonomo = (selectedOption.dataset.isAutonomo === 'true') || 
                     tipoTexto.includes('autónomo') || 
                     tipoTexto.includes('autonomo');

  if (isAutonomo) {
    // ============================================
    // AUTÓNOMO
    // ============================================
    bloqueRadiosConst.classList.add('hidden-field');
    radioConstituida.checked = false;
    radioEnConstitucion.checked = false;
    
    // Mostrar DNI
    bloqueDni.classList.remove('hidden-field');
    inputDni.required = true;
    labelDni.innerHTML = 'DNI / NIE <span class="req">*</span>';
    
    // Ocultar CIF y representantes
    bloqueCif.classList.add('hidden-field');
    inputCif.required = false;
    bloqueNombreRep.classList.add('hidden-field');
    bloqueApellidosRep.classList.add('hidden-field');
    inputNombreRep.required = false;
    inputApellidosRep.required = false;
    
    // OCULTAR razón social y marcarla como NO obligatoria
    bloqueRazonSocial.classList.add('hidden-field');
    inputRazon.required = false;
    inputRazon.value = ''; // Limpiar valor
    
    // Mostrar nombre comercial como opcional
    bloqueNombreComercial.classList.remove('hidden-field');
    labelNombreComercial.innerHTML = 'Nombre Comercial / Marca (Opcional)';
    document.getElementById('input_nombre_comercial').placeholder = 'Ej: Frutería Paco';
    document.getElementById('input_nombre_comercial').required = false;
    
    // Nota autónomo
    noteRazon.style.display = 'block';
    noteRazon.innerHTML = '💡 Tu <strong>Razón Social legal</strong> será tu Nombre y Apellidos automáticamente.';
    noteConstitucion.style.display = 'none';
    
  } else {
    // ============================================
    // SOCIEDAD
    // ============================================
    bloqueRadiosConst.classList.remove('hidden-field');
    bloqueNombreComercial.classList.add('hidden-field');
    
    // Si hay algún radio marcado, aplicar lógica
    if (radioConstituida.checked || radioEnConstitucion.checked) {
      toggleSociedadConstitucion();
    } else {
      // Si no hay nada marcado, ocultar todos los campos
      bloqueDni.classList.add('hidden-field');
      bloqueCif.classList.add('hidden-field');
      bloqueRazonSocial.classList.add('hidden-field');
      bloqueNombreRep.classList.add('hidden-field');
      bloqueApellidosRep.classList.add('hidden-field');
      bloqueNombreComercial.classList.add('hidden-field');
    }
  }
}

  function toggleSociedadConstitucion() {
    // Mostrar campos base
    bloqueDni.classList.remove('hidden-field');
    bloqueRazonSocial.classList.remove('hidden-field');

    const enConstitucion = radioEnConstitucion.checked;

    if (enConstitucion) {
      // ============================================
      // EN CONSTITUCIÓN (NO TIENE CIF)
      // ============================================
      bloqueCif.classList.add('hidden-field');
      bloqueDni.classList.remove('hidden-field');
      inputDni.required = true;
      inputCif.required = false;
      inputCif.value = '';
      
      labelDni.innerHTML = 'DNI / NIE (Persona que firma) <span class="req">*</span>';
      
      // Ocultar nombre y apellidos del representante
      if (bloqueNombreRep) {
        bloqueNombreRep.classList.add('hidden-field');
        inputNombreRep.required = false;
      }
      if (bloqueApellidosRep) {
        bloqueApellidosRep.classList.add('hidden-field');
        inputApellidosRep.required = false;
      }
      
      // Razón social auto-rellenada
      labelRazon.innerHTML = 'Nombre completo';
      inputRazon.placeholder = 'Se usará tu nombre y apellidos automáticamente';
      inputRazon.required = false;
      inputRazon.readOnly = true;
      inputRazon.style.background = '#f1f5f9';
      inputRazon.value = '';
      
      noteRazon.style.display = 'block';
      noteRazon.innerHTML = '💡 El contrato se firmará a tu nombre personal. La denominación social se añadirá cuando se constituya.';
      
      bloqueNombreComercial.classList.add('hidden-field');
      noteConstitucion.style.display = 'block';
      
      autoRellenarRazonSocial();
    } else {
      // ============================================
      // YA CONSTITUIDA (TIENE CIF)
      // ============================================
      bloqueDni.classList.remove('hidden-field');
      bloqueCif.classList.remove('hidden-field');
      inputCif.required = true;
      inputDni.required = true;
      
      labelDni.innerHTML = 'DNI / NIE (Representante legal) <span class="req">*</span>';
      
      // Mostrar nombre y apellidos del representante
      if (bloqueNombreRep) {
        bloqueNombreRep.classList.remove('hidden-field');
        inputNombreRep.required = true;
      }
      if (bloqueApellidosRep) {
        bloqueApellidosRep.classList.remove('hidden-field');
        inputApellidosRep.required = true;
      }
      
      // Razón social obligatoria
      labelRazon.innerHTML = 'Razón Social / Denominación <span class="req">*</span>';
      inputRazon.placeholder = 'Ej: AsesorFy S.L.';
      inputRazon.required = true;
      inputRazon.readOnly = false;
      inputRazon.style.background = '#fff';
      
      noteRazon.style.display = 'none';
      
      bloqueNombreComercial.classList.remove('hidden-field');
      noteConstitucion.style.display = 'none';
    }
  }

  // Auto-rellenar razón social cuando está "en constitución"
  function autoRellenarRazonSocial() {
    const enConstitucion = radioEnConstitucion && radioEnConstitucion.checked;
    
    if (enConstitucion) {
      const nombre = document.getElementById('nombre').value.trim();
      const apellidos = document.getElementById('apellidos').value.trim();
      
      if (nombre && apellidos) {
        inputRazon.value = `${nombre} ${apellidos}`;
      }
    }
  }

  // Escuchar cambios en nombre y apellidos
  const nombreInput = document.getElementById('nombre');
  const apellidosInput = document.getElementById('apellidos');

  if (nombreInput) {
    nombreInput.addEventListener('input', autoRellenarRazonSocial);
  }
  if (apellidosInput) {
    apellidosInput.addEventListener('input', autoRellenarRazonSocial);
  }

  // Actualizar notas IBAN según tipo de sociedad
  function actualizarNotasIban() {
    const noteGeneral = document.getElementById('note_iban_general');
    const noteConstitucion = document.getElementById('note_iban_constitucion');
    const noteEmpresa = document.getElementById('note_iban_empresa');
    
    if (!noteGeneral || !noteConstitucion || !noteEmpresa) return;

    const tipoSelect = document.getElementById('tipo_cliente_select');
    if (!tipoSelect || !tipoSelect.value) return;

    const selectedOption = tipoSelect.options[tipoSelect.selectedIndex];
    const tipoTexto = (selectedOption.text || '').toLowerCase();
    const isAutonomo = (selectedOption.dataset.isAutonomo === 'true') || 
                       tipoTexto.includes('autónomo') || 
                       tipoTexto.includes('autonomo');

    if (isAutonomo) {
      // AUTÓNOMO: nota especial
      noteGeneral.classList.add('hidden-field');
      noteConstitucion.classList.remove('hidden-field');
      noteConstitucion.innerHTML = '💡 <strong>Recomendación:</strong> Es aconsejable usar una cuenta bancaria dedicada exclusivamente a la gestión de tu actividad profesional. Esto facilitará la contabilidad y el control de ingresos y gastos.';
      noteConstitucion.style.background = '#e0f7ff';
      noteConstitucion.style.borderColor = '#0ea5e9';
      noteEmpresa.classList.add('hidden-field');
    } else {
      const radioEnConstitucion = document.getElementById('sociedad_en_constitucion');
      const enConstitucion = radioEnConstitucion && radioEnConstitucion.checked;

      noteGeneral.classList.add('hidden-field');

      if (enConstitucion) {
        // EN CONSTITUCIÓN: nota amarilla
        noteConstitucion.classList.remove('hidden-field');
        noteConstitucion.innerHTML = '⚠️ <strong>Importante:</strong> Esta cuenta será modificada por tu asesor una vez la sociedad esté constituida y tenga cuenta bancaria corporativa abierta.';
        noteConstitucion.style.background = '#fef3c7';
        noteConstitucion.style.borderColor = '#f59e0b';
        noteEmpresa.classList.add('hidden-field');
      } else {
        // YA CONSTITUIDA: nota azul
        noteConstitucion.classList.add('hidden-field');
        noteEmpresa.classList.remove('hidden-field');
      }
    }
  }

  if (selectTipo) {
    selectTipo.addEventListener('change', () => {
      toggleTipoCliente();
      actualizarNotasIban();
    });
    toggleTipoCliente();
    setTimeout(actualizarNotasIban, 100);
  }

  if (radioConstituida) {
    radioConstituida.addEventListener('change', () => {
      toggleSociedadConstitucion();
      actualizarNotasIban();
    });
  }
  if (radioEnConstitucion) {
    radioEnConstitucion.addEventListener('change', () => {
      toggleSociedadConstitucion();
      actualizarNotasIban();
    });
  }

  // Provincias
  const provinciasMap = @json($provinciasMap);
  const provSelect = document.getElementById('provincia');
  const comInput = document.getElementById('comunidad_autonoma');
  if (provSelect && comInput) {
    provSelect.addEventListener('change', () => {
      comInput.value = provinciasMap[provSelect.value] || '';
    });
    if (provSelect.value) provSelect.dispatchEvent(new Event('change'));
  }

  // IBAN
  const ibanInput = document.getElementById('cuenta_bancaria_ss');
  const ibanFeedback = document.getElementById('ibanFeedback');
  if (ibanInput) {
    ibanInput.addEventListener('input', function() {
      let v = this.value.replace(/\s+/g, '').toUpperCase();
      this.value = v;
      if (v.length >= 5) {
        if (/^ES\d{22}$/.test(v)) {
          ibanFeedback.textContent = '✓ Correcto';
          ibanFeedback.style.color = 'var(--success)';
          this.style.borderColor = 'var(--success)';
        } else {
          if (v.length >= 24) {
            ibanFeedback.textContent = '✕ Formato incorrecto';
            ibanFeedback.style.color = 'var(--danger)';
            this.style.borderColor = 'var(--danger)';
          } else {
            ibanFeedback.textContent = '';
            this.style.borderColor = 'var(--border-color)';
          }
        }
      }
    });
  }

  // Aportación SL
  const aportacionSelect = document.getElementById('aportacion_tipo');
  const bloqueCapital = document.getElementById('bloque_capital_dinerario');
  const bloqueBienes = document.getElementById('bloque_bienes_aportar');

  function actualizarAportacionUI() {
    if (!aportacionSelect) return;
    const tipo = aportacionSelect.value;
    if (bloqueCapital) bloqueCapital.classList.remove('hidden-field');
    if (bloqueBienes) bloqueBienes.classList.add('hidden-field');

    if (tipo === 'bienes') {
      if (bloqueCapital) bloqueCapital.classList.add('hidden-field');
      if (bloqueBienes) bloqueBienes.classList.remove('hidden-field');
    } else if (tipo === 'mixta') {
      if (bloqueCapital) bloqueCapital.classList.remove('hidden-field');
      if (bloqueBienes) bloqueBienes.classList.remove('hidden-field');
    }
  }
  if (aportacionSelect) {
    aportacionSelect.addEventListener('change', actualizarAportacionUI);
    actualizarAportacionUI();
  }

  // Socios repeater
  const sociosContainer = document.getElementById('socios_repeater');
  const addSocioBtn = document.getElementById('add_socio_btn');

  function bindSocioRow(row) {
    if (!row) return;
    const checkbox = row.querySelector('.socio-casado-toggle');
    const regimenSelect = row.querySelector('.socio-regimen-select');
    if (!checkbox || !regimenSelect) return;

    function toggleRegimen() {
      if (checkbox.checked) {
        regimenSelect.classList.remove('hidden-field');
      } else {
        regimenSelect.classList.add('hidden-field');
        regimenSelect.value = '';
      }
    }
    checkbox.addEventListener('change', toggleRegimen);
    toggleRegimen();
  }

  if (sociosContainer) {
    const rows = sociosContainer.querySelectorAll('.socio-row');
    rows.forEach(bindSocioRow);
  }

  if (addSocioBtn && sociosContainer) {
    addSocioBtn.addEventListener('click', function() {
      const firstRow = sociosContainer.querySelector('.socio-row');
      if (!firstRow) return;
      const newRow = firstRow.cloneNode(true);

      newRow.querySelectorAll('input').forEach(function(input) {
        if (input.type === 'checkbox') input.checked = false;
        else input.value = '';
      });
      newRow.querySelectorAll('select').forEach(function(select) {
        select.value = '';
        if (select.classList.contains('socio-regimen-select')) select.classList.add('hidden-field');
      });
      sociosContainer.appendChild(newRow);
      bindSocioRow(newRow);
    });
  }

  // Generar resumen
  function generarResumen() {
    const resumen = document.getElementById('resumen-contenido');
    let html = '<div style="display: grid; gap: 25px;">';

    // ========================================
    // CONTACTO
    // ========================================
    html += '<div><strong style="color: var(--primary); font-size: 16px;">📋 Datos de Contacto</strong><br>';
    html += `Nombre: ${document.getElementById('nombre').value} ${document.getElementById('apellidos').value}<br>`;
    html += `Email: ${document.getElementById('email').value}<br>`;
    html += `Teléfono: ${document.getElementById('telefono').value}</div>`;

    // ========================================
    // FISCAL
    // ========================================
    html += '<div><strong style="color: var(--primary); font-size: 16px;">💼 Datos Fiscales</strong><br>';
    const tipoClienteText = selectTipo.options[selectTipo.selectedIndex].text;
    html += `Tipo: ${tipoClienteText}<br>`;

    const tipoTexto = (selectTipo.options[selectTipo.selectedIndex].text || '').toLowerCase();
    const esAutonomo = (selectTipo.options[selectTipo.selectedIndex].dataset.isAutonomo === 'true') || 
                       tipoTexto.includes('autónomo') || 
                       tipoTexto.includes('autonomo');
    const enConstitucion = radioEnConstitucion && radioEnConstitucion.checked;
    const nombreCompleto = document.getElementById('nombre').value + ' ' + document.getElementById('apellidos').value;

    if (esAutonomo) {
      html += `Titular: ${nombreCompleto}<br>`;
      html += `DNI: ${inputDni.value}<br>`;
      const nombreComercialInput = document.getElementById('input_nombre_comercial');
      if (nombreComercialInput && nombreComercialInput.value) {
        html += `Nombre comercial: ${nombreComercialInput.value}<br>`;
      } else {
        html += `Razón social: ${nombreCompleto}<br>`;
      }
    } else if (enConstitucion) {
      html += `<strong>Estado:</strong> 🏗️ En constitución (sin CIF)<br>`;
      html += `Persona que firma: ${nombreCompleto}<br>`;
      html += `DNI: ${inputDni.value}<br>`;
      html += `<em style="color: #64748b;">La denominación social se añadirá tras constituirse</em><br>`;
    } else {
      html += `<strong>Estado:</strong> ✓ Sociedad constituida<br>`;
      html += `CIF: ${inputCif.value}<br>`;
      html += `Razón Social / Denominación: ${inputRazon.value}<br>`;
      
      const nombreRep = inputNombreRep && inputNombreRep.value ? inputNombreRep.value : '';
      const apellidosRep = inputApellidosRep && inputApellidosRep.value ? inputApellidosRep.value : '';
      const nombreCompletoRep = (nombreRep && apellidosRep) ? `${nombreRep} ${apellidosRep}` : nombreCompleto;
      
      html += `Representante legal: ${nombreCompletoRep}<br>`;
      html += `DNI Representante: ${inputDni.value}<br>`;
      
      const nombreComercialEmpresa = document.querySelector('input[name="nombre_comercial"]');
      if (nombreComercialEmpresa && nombreComercialEmpresa.value) {
        html += `Nombre comercial: ${nombreComercialEmpresa.value}<br>`;
      }
    }
    html += '</div>';

    // ========================================
    // DIRECCIÓN
    // ========================================
    html += '<div><strong style="color: var(--primary); font-size: 16px;">📍 Dirección</strong><br>';
    html += `${document.getElementById('direccion').value}<br>`;
    html += `${document.getElementById('cp').value} ${document.getElementById('localidad').value}<br>`;
    html += `${document.getElementById('provincia').value}</div>`;

    // ========================================
    // FACTURACIÓN E IMPUESTOS
    // ========================================
    const ibanInput = document.getElementById('cuenta_bancaria_ss');
    if (ibanInput) {
      html += '<div><strong style="color: var(--primary); font-size: 16px;">💰 Facturación e Impuestos</strong><br>';
      if (ibanInput.value) {
        html += `IBAN (Impuestos/SS): ${ibanInput.value}<br>`;
      }
      const obs = document.querySelector('textarea[name="observaciones"]');
      if (obs && obs.value) {
        html += `Observaciones: ${obs.value}<br>`;
      }
      html += '</div>';
    }

    // ========================================
    // ALTA AUTÓNOMO
    // ========================================
    const altaAutoFecha = document.querySelector('input[name="extra_auto_fecha_inicio"]');
    if (altaAutoFecha && altaAutoFecha.value) {
      html += '<div><strong style="color: var(--primary); font-size: 16px;">🚀 Alta de Autónomo</strong><br>';
      html += `Fecha inicio actividad: ${altaAutoFecha.value}<br>`;
      
      const fechaNac = document.querySelector('input[name="fecha_nacimiento"]');
      if (fechaNac && fechaNac.value) html += `Fecha nacimiento: ${fechaNac.value}<br>`;
      
      const ss = document.querySelector('input[name="seguridad_social"]');
      if (ss && ss.value) html += `Nº Seguridad Social: ${ss.value}<br>`;
      
      const cert = document.querySelector('select[name="extra_auto_certificado_digital"]');
      if (cert && cert.value) html += `Certificado digital: ${cert.value === 'si' ? 'Sí' : 'No'}<br>`;
      
      const actividad = document.querySelector('textarea[name="extra_auto_actividad"]');
      if (actividad && actividad.value) html += `Actividad: ${actividad.value}<br>`;
      
      html += '</div>';
    }

    // ========================================
    // CREACIÓN SOCIEDAD
    // ========================================
    const slNombre1 = document.querySelector('input[name="extra_sl_nombre1"]');
    if (slNombre1 && slNombre1.value) {
      html += '<div><strong style="color: var(--primary); font-size: 16px;">🏗️ Constitución S.L.</strong><br>';
      html += `Denominaciones propuestas: ${slNombre1.value}`;
      
      const slNombre2 = document.querySelector('input[name="extra_sl_nombre2"]');
      if (slNombre2 && slNombre2.value) html += `, ${slNombre2.value}`;
      const slNombre3 = document.querySelector('input[name="extra_sl_nombre3"]');
      if (slNombre3 && slNombre3.value) html += `, ${slNombre3.value}`;
      html += '<br>';
      
      const capital = document.querySelector('input[name="extra_sl_capital"]');
      if (capital && capital.value) html += `Capital social: ${capital.value}€<br>`;
      
      const actividadSL = document.querySelector('input[name="extra_sl_actividad"]');
      if (actividadSL && actividadSL.value) html += `Actividad: ${actividadSL.value}<br>`;
      
      const tipoAdmin = document.querySelector('select[name="extra_sl_tipo_admin"]');
      if (tipoAdmin && tipoAdmin.value) {
        const adminTexto = tipoAdmin.options[tipoAdmin.selectedIndex].text;
        html += `Administración: ${adminTexto}<br>`;
      }
      
      html += '</div>';
    }

    // ========================================
    // CAPITALIZACIÓN
    // ========================================
    const capForma = document.querySelector('select[name="extra_cap_forma_juridica"]');
    if (capForma && capForma.value) {
      html += '<div><strong style="color: var(--primary); font-size: 16px;">💰 Capitalización del Paro</strong><br>';
      const formaTexto = capForma.options[capForma.selectedIndex].text;
      html += `Destino: ${formaTexto}<br>`;
      
      const inversion = document.querySelector('input[name="extra_cap_inversion"]');
      if (inversion && inversion.value) html += `Inversión prevista: ${inversion.value}€<br>`;
      
      const modalidad = document.querySelector('select[name="extra_cap_modalidad"]');
      if (modalidad && modalidad.value) {
        const modalidadTexto = modalidad.options[modalidad.selectedIndex].text;
        html += `Modalidad: ${modalidadTexto}<br>`;
      }
      
      html += '</div>';
    }

    html += '</div>';
    resumen.innerHTML = html;
  }

  showStep(1);
})();
</script>
</body>
</html>