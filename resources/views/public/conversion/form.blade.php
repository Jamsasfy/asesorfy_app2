<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Datos para tu alta con AsesorFy</title>
  <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">

  <style>
    :root {
      --bg:#0b1220;
      --card:#0f172a;
      --muted:#94a3b8;
      --border:#1f2937;
      --accent:#38bdf8;
      --accent-strong:#0ea5e9;
      --danger:#ef4444;
      --success:#22c55e;
      --input-bg:#020617;
    }
    *{box-sizing:border-box}
    html,body{margin:0;padding:0;height:100%}
    body{
      font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial;
      background:radial-gradient(circle at top,#1f2937 0,#020617 55%,#000 100%);
      color:#e5e7eb;
    }
    .wrap{max-width:960px;margin:30px auto;padding:0 12px}
    .card{
      background:var(--card);
      border-radius:18px;
      border:1px solid var(--border);
      box-shadow:0 24px 55px rgba(15,23,42,.8);
      padding:22px 20px 24px;
    }
    
    /* Header */
    .header{ display:flex; justify-content:space-between; gap:14px; margin-bottom:18px; }
    .logo img{ height:38px; width:auto; display:block; }
    .step-pill{ font-size:.75rem; border-radius:999px; padding:4px 10px; border:1px solid rgba(148,163,184,.6); color:var(--muted); display:inline-flex; align-items:center; gap:6px; }
    .step-pill span.badge{ display:inline-flex; align-items:center; justify-content:center; width:18px; height:18px; border-radius:999px; background:#22c55e1a; color:#4ade80; font-size:.7rem; font-weight:700; }
    .title-main{font-size:1.45rem;font-weight:800;margin:4px 0 2px;letter-spacing:.02em}
    .subtitle{margin:0;color:var(--muted);font-size:.93rem;max-width:540px}
    
    /* Form Elements */
    form{margin-top:10px}
    .section{ margin-top:16px; padding:14px 14px 12px; border-radius:14px; background:linear-gradient(135deg,#020617,#020617 55%,#020617); border:1px solid rgba(15,23,42,1); }
    .section-header{ display:flex; justify-content:space-between; gap:8px; margin-bottom:10px; }
    .section-title{ font-size:.9rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:#cbd5f5; }
    .section-sub{ font-size:.8rem; color:var(--muted); }
    .grid{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px 16px; }
    .field{display:flex;flex-direction:column;font-size:.9rem}
    label{ font-size:.83rem; color:#e5e7eb; margin-bottom:4px; font-weight:600; }
    .req{color:var(--danger);margin-left:3px}
    
    input,select,textarea{ border-radius:10px; border:1px solid #1f2937; background:var(--input-bg); color:#e5e7eb; padding:8px 10px; font:inherit; outline:none; transition:border-color .15s ease, box-shadow .15s ease; }
    input:focus,select:focus,textarea:focus{ border-color:var(--accent-strong); box-shadow:0 0 0 1px rgba(56,189,248,.45); background:#020617; }
    textarea{min-height:80px;resize:vertical}
    .full{grid-column:1/-1}
    .hint{ font-size:.8rem; color:var(--muted); margin:4px 0 8px; }
    
    /* Radio buttons */
    .radio-group{ display:flex; flex-direction:column; gap:4px; margin-top:2px; }
    .radio-option{ display:flex; align-items:center; gap:7px; font-size:.86rem; color:var(--muted); }
    
    /* Buttons */
    .actions{ margin-top:18px; display:flex; justify-content:flex-end; gap:10px; }
    .btn-primary{ background:var(--accent-strong); color:#0b1120; border:0; border-radius:999px; padding:9px 18px; font-weight:700; cursor:pointer; }
    .btn-primary:hover{ background:#0ea5e9; }
    
    /* Errores y Validación */
    .error-box{ margin-bottom:12px; padding:10px 12px; border-radius:12px; border:1px solid #7f1d1d; background:rgba(248,113,113,.05); color:#fecaca; font-size:.83rem; }
    input.has-error, select.has-error, textarea.has-error { border-color: var(--danger); background-color: rgba(239, 68, 68, 0.05); }
    .error-msg { color: var(--danger); font-size: 0.8rem; margin-top: 4px; font-weight: 500; }
    
    /* JS Validation Classes */
    input.js-valid { border-color: var(--success); }
    input.js-invalid { border-color: var(--danger); }
    .iban-feedback { font-size: 0.8rem; margin-top: 4px; min-height: 1.2em; font-weight: 500; }
    .iban-feedback.error { color: var(--danger); }
    .iban-feedback.success { color: var(--success); }

    input[readonly] { background: #1e293b; color: #94a3b8; cursor: not-allowed; border-color: #334155; }
    @media (max-width:760px){ .grid{grid-template-columns:1fr} }
  </style>
</head>
<body>

  @php
    $prefilled = $prefilled ?? [];
    $getValue = function($campo, $leadField = null) use ($prefilled, $lead) {
        $default = $prefilled[$campo] ?? ($leadField ? $lead->$leadField : '');
        return old($campo, $default);
    };
    $formType = $link->meta['form_type'] ?? 'standard';
    $provinciasMap = config('provincias.provincias'); 
  @endphp

  <div class="wrap">
    <div class="card">
      <div class="header">
        <div>
          <div class="logo">
            <img src="{{ asset('images/logo.png') }}" alt="AsesorFy" onerror="this.replaceWith(document.createTextNode('AsesorFy'));">
          </div>
          <div style="margin-top:8px;"><div class="step-pill"><span class="badge">1</span> Datos de alta · Paso 1 de 2</div></div>
        </div>
        <div>
          <div class="title-main">Datos para proceder con tu alta</div>
          <p class="subtitle">Rellena o verifica estos datos básicos.</p>
        </div>
      </div>

      @if ($errors->any())
        <div class="error-box">
          <strong>Por favor revisa los siguientes campos marcados en rojo:</strong>
          <ul>@foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach</ul>
        </div>
      @endif

      <form method="POST" action="{{ route('conversion.submit', $link->token) }}">
        @csrf

        {{-- SECCIÓN 1: DATOS COMUNES --}}
        <div class="section">
          <div class="section-header"><div class="section-title">Datos del Titular / Cliente</div></div>
          <div class="grid">
            
            {{-- TIPO CLIENTE --}}
            <div class="field full">
              <label>Soy o quiero ser...<span class="req">*</span></label>
              <select name="tipo_cliente_id" required class="@error('tipo_cliente_id') has-error @enderror">
                <option value="" selected disabled>Selecciona una opción</option>
                @foreach($tipos as $tipo)
                    @php $selectedId = old('tipo_cliente_id', $prefilled['tipo_cliente_id'] ?? null); @endphp
                    <option value="{{ $tipo->id }}" @selected($selectedId == $tipo->id)>{{ $tipo->nombre }}</option>
                @endforeach
              </select>
              @error('tipo_cliente_id') <div class="error-msg">{{ $message }}</div> @enderror
            </div>

            {{-- NOMBRE --}}
            <div class="field">
                <label>Nombre<span class="req">*</span></label>
                <input type="text" name="nombre" value="{{ $getValue('nombre', 'nombre') }}" required class="@error('nombre') has-error @enderror">
                @error('nombre') <div class="error-msg">{{ $message }}</div> @enderror
            </div>

            {{-- APELLIDOS --}}
            <div class="field">
                <label>Apellidos<span class="req">*</span></label>
                <input type="text" name="apellidos" value="{{ $getValue('apellidos', 'apellidos') }}" required class="@error('apellidos') has-error @enderror">
                @error('apellidos') <div class="error-msg">{{ $message }}</div> @enderror
            </div>

            {{-- DNI --}}
            <div class="field">
                <label>DNI / NIE<span class="req">*</span></label>
                <input type="text" name="dni" value="{{ $getValue('dni', 'dni') }}" required class="@error('dni') has-error @enderror">
                @error('dni') <div class="error-msg">{{ $message }}</div> @enderror
            </div>

            {{-- FECHA NACIMIENTO --}}
            <div class="field">
                <label>Fecha Nacimiento<span class="req">*</span></label>
                <input type="date" name="fecha_nacimiento" value="{{ $getValue('fecha_nacimiento') }}" required class="@error('fecha_nacimiento') has-error @enderror">
                @error('fecha_nacimiento') <div class="error-msg">{{ $message }}</div> @enderror
            </div>

            {{-- EMAIL --}}
            <div class="field">
                <label>Email<span class="req">*</span></label>
                <input type="email" name="email" value="{{ $getValue('email', 'email') }}" required class="@error('email') has-error @enderror">
                @error('email') <div class="error-msg">{{ $message }}</div> @enderror
            </div>

            {{-- TELÉFONO --}}
            <div class="field">
                <label>Teléfono<span class="req">*</span></label>
                <input type="tel" name="telefono" value="{{ $getValue('telefono', 'tfn') }}" required class="@error('telefono') has-error @enderror">
                @error('telefono') <div class="error-msg">{{ $message }}</div> @enderror
            </div>

            {{-- SEGURIDAD SOCIAL --}}
            <div class="field full">
                <label>Nº Seguridad Social (Propio)<span class="req">*</span></label>
                <input type="text" name="seguridad_social" value="{{ $getValue('seguridad_social') }}" required class="@error('seguridad_social') has-error @enderror">
                @error('seguridad_social') <div class="error-msg">{{ $message }}</div> @enderror
            </div>
          </div>
        </div>

        {{-- SECCIÓN 2: DIRECCIÓN FISCAL --}}
        <div class="section">
          <div class="section-header"><div class="section-title">Dirección Fiscal</div></div>
          <div class="grid">
            <div class="field full">
                <label>Dirección completa<span class="req">*</span></label>
                <input type="text" name="direccion" value="{{ $getValue('direccion') }}" required class="@error('direccion') has-error @enderror">
                @error('direccion') <div class="error-msg">{{ $message }}</div> @enderror
            </div>
            <div class="field"><label>Código Postal<span class="req">*</span></label><input type="text" name="cp" value="{{ $getValue('cp') }}" required></div>
            <div class="field"><label>Localidad<span class="req">*</span></label><input type="text" name="localidad" value="{{ $getValue('localidad') }}" required></div>
            
            {{-- PROVINCIA (SELECT) --}}
            <div class="field">
              <label>Provincia<span class="req">*</span></label>
              <select id="provincia" name="provincia" required>
                  <option value="">Selecciona...</option>
                  @foreach(array_keys($provinciasMap) as $prov)
                    <option value="{{ $prov }}" @selected($getValue('provincia') == $prov)>{{ $prov }}</option>
                  @endforeach
              </select>
            </div>
            
            {{-- CCAA (AUTO) --}}
            <div class="field">
              <label>Comunidad Autónoma<span class="req">*</span></label>
              <input type="text" id="comunidad_autonoma" name="comunidad_autonoma" value="{{ $getValue('comunidad_autonoma') }}" readonly tabindex="-1">
            </div>
          </div>
        </div>

        {{-- SECCIÓN 3: DATOS BANCARIOS --}}
        <div class="section">
          <div class="section-header"><div class="section-title">Datos Bancarios</div></div>
          <div class="grid">
            <div class="field full">
              <label>Cuenta IBAN (Cobro de cuotas / impuestos)<span class="req">*</span></label>
              <input type="text" 
                     id="cuenta_bancaria_ss" 
                     name="cuenta_bancaria_ss" 
                     value="{{ $getValue('cuenta_bancaria_ss') }}" 
                     required 
                     placeholder="ES..." 
                     style="text-transform: uppercase;"
                     class="@error('cuenta_bancaria_ss') has-error @enderror"
              >
              <div id="ibanFeedback" class="iban-feedback"></div>
              @error('cuenta_bancaria_ss') <div class="error-msg">⚠️ {{ $message }}</div> @enderror
            </div>

            <div class="field full"><label>Observaciones generales (Opcional)</label><textarea name="observaciones" placeholder="Cualquier comentario adicional">{{ $getValue('observaciones') }}</textarea></div>
          </div>
        </div>

        {{-- SECCIÓN 4: BLOQUES DINÁMICOS (S.L., PARO, AUTÓNOMO) --}}

        {{-- A) CREACIÓN S.L. --}}
        @if($formType === 'creacion_sociedad')
            <div class="section" style="border-left: 4px solid #8b5cf6;">
                <div class="section-header"><div class="section-title">Datos Constitución S.L.</div></div>
                <div class="grid">
                     <div class="field full">
                        <label>5 Nombres de preferencia<span class="req">*</span></label>
                        <input type="text" name="extra_sl_nombre1" value="{{ old('extra_sl_nombre1') }}" placeholder="1. Principal" required style="margin-bottom:5px">
                        <input type="text" name="extra_sl_nombre2" value="{{ old('extra_sl_nombre2') }}" placeholder="2." required style="margin-bottom:5px">
                        <input type="text" name="extra_sl_nombre3" value="{{ old('extra_sl_nombre3') }}" placeholder="3." required style="margin-bottom:5px">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                            <input type="text" name="extra_sl_nombre4" value="{{ old('extra_sl_nombre4') }}" placeholder="4." required>
                            <input type="text" name="extra_sl_nombre5" value="{{ old('extra_sl_nombre5') }}" placeholder="5." required>
                        </div>
                     </div>
                     <div class="field"><label>Capital (€)</label><input type="number" name="extra_sl_capital" value="{{ old('extra_sl_capital', 3000) }}" required></div>
                     <div class="field"><label>Actividad</label><input type="text" name="extra_sl_actividad" value="{{ old('extra_sl_actividad') }}" required></div>
                     <div class="field full">
                        <div style="background:#f0f9ff; padding:8px; border-radius:6px; font-size:0.85rem; color:#0369a1; margin-bottom:6px;"><strong>Socios:</strong> Nombre, DNI, % y Estado Civil (Gananciales/Separación).</div>
                        <textarea name="extra_sl_socios" rows="4" required>{{ old('extra_sl_socios') }}</textarea>
                     </div>
                     <div class="field"><label>Admin</label><select name="extra_sl_tipo_admin" required><option value="unico">Único</option><option value="solidarios">Solidarios</option><option value="mancomunados">Mancomunados</option></select></div>
                     <div class="field"><label>Persona Admin</label><input type="text" name="extra_sl_admin_nombre" value="{{ old('extra_sl_admin_nombre') }}" required></div>
                     <div class="field full"><label>Domicilio Social</label><input type="text" name="extra_sl_domicilio_social" value="{{ old('extra_sl_domicilio_social') }}" placeholder="Si es distinto al tuyo"></div>
                     <div class="field"><label>Ciudad Firma (Notaría)</label><input type="text" name="extra_sl_ciudad_firma" value="{{ old('extra_sl_ciudad_firma') }}" required></div>
                </div>
            </div>
        @endif

        {{-- B) CAPITALIZACIÓN PARO --}}
        @if($formType === 'capitalizacion')
             <div class="section" style="border-left: 4px solid #f59e0b;">
                <div class="section-header"><div class="section-title">Capitalización del Paro</div></div>
                <div class="grid">
                    <div class="field full"><label>¿Para qué usarás el dinero?<span class="req">*</span></label>
                        <select name="extra_cap_forma_juridica" required>
                            <option value="autonomo" @selected(old('extra_cap_forma_juridica')=='autonomo')>Autónomo</option>
                            <option value="sociedad" @selected(old('extra_cap_forma_juridica')=='sociedad')>Sociedad</option>
                        </select>
                    </div>
                    <div class="field"><label>Inversión Prevista (Opcional)</label><input type="number" name="extra_cap_inversion" value="{{ old('extra_cap_inversion') }}"></div>
                    <div class="field"><label>Paro Solicitado (Opcional)</label><input type="number" name="extra_cap_solicitado" value="{{ old('extra_cap_solicitado') }}"></div>
                    <div class="field full"><label>Modalidad Cobro<span class="req">*</span></label>
                        <div class="radio-group">
                            <label class="radio-option"><input type="radio" name="extra_cap_modalidad" value="pago_unico" required @checked(old('extra_cap_modalidad')=='pago_unico')> Pago Único</label>
                            <label class="radio-option"><input type="radio" name="extra_cap_modalidad" value="cuotas" required @checked(old('extra_cap_modalidad')=='cuotas')> Cuotas</label>
                            <label class="radio-option"><input type="radio" name="extra_cap_modalidad" value="mixto" required @checked(old('extra_cap_modalidad')=='mixto')> Mixto</label>
                            <label class="radio-option"><input type="radio" name="extra_cap_modalidad" value="no_claro" required @checked(old('extra_cap_modalidad')=='no_claro')> No lo sé</label>
                        </div>
                    </div>
                    <div class="field full"><label>Memoria / Idea</label><textarea name="extra_cap_memoria" style="height:80px;">{{ old('extra_cap_memoria') }}</textarea></div>
                    <div class="field"><label>En paro desde<span class="req">*</span></label><input type="date" name="extra_cap_fecha_paro" value="{{ old('extra_cap_fecha_paro') }}" required></div>
                    <div class="field"><label>Percibe mensual<span class="req">*</span></label><input type="text" name="extra_cap_prestacion_mensual" value="{{ old('extra_cap_prestacion_mensual') }}" required></div>
                    <div class="field full"><label>Duración restante<span class="req">*</span></label><input type="text" name="extra_cap_duracion_paro" value="{{ old('extra_cap_duracion_paro') }}" required></div>
                </div>
            </div>
        @endif

        {{-- C) ALTA AUTÓNOMO --}}
        @if($formType === 'alta_autonomo')
            <div class="section" style="border-left: 4px solid #10b981;">
                <div class="section-header"><div class="section-title">Alta de Autónomo</div></div>
                <div class="grid">
                    <div class="field"><label>Inicio Actividad<span class="req">*</span></label><input type="date" name="extra_auto_fecha_inicio" value="{{ old('extra_auto_fecha_inicio') }}" required></div>
                    <div class="field full"><label>Actividad Detallada<span class="req">*</span></label><textarea name="extra_auto_actividad" required>{{ old('extra_auto_actividad') }}</textarea></div>
                    <div class="field full"><label>Lugar<span class="req">*</span></label>
                        <select name="extra_auto_lugar" required>
                            <option value="casa">Casa</option><option value="local">Local</option><option value="cliente">Cliente</option>
                        </select>
                    </div>
                    <div class="field full"><label>Tarifa Plana</label>
                        <div class="radio-group"><label class="radio-option"><input type="radio" name="extra_auto_tarifa_plana" value="si" checked> Sí</label><label class="radio-option"><input type="radio" name="extra_auto_tarifa_plana" value="no"> No</label></div>
                    </div>
                </div>
            </div>
        @endif

        <div class="actions">
          <button type="submit" class="btn-primary">Continuar a contrato</button>
        </div>
      </form>
    </div>
  </div>

  {{-- SCRIPTS JS --}}
  <script>
    // 1. CCAA
    const provinciasMap = @json($provinciasMap);
    const provSelect = document.getElementById('provincia');
    const comInput = document.getElementById('comunidad_autonoma');
    if (provSelect && comInput) {
        const updateComunidad = () => {
            const prov = provSelect.value;
            comInput.value = (prov && provinciasMap[prov]) ? provinciasMap[prov] : '';
        };
        provSelect.addEventListener('change', updateComunidad);
        updateComunidad(); 
    }

    // 2. VALIDACIÓN IBAN (JS)
    const ibanInput = document.getElementById('cuenta_bancaria_ss');
    const ibanFeedback = document.getElementById('ibanFeedback');
    if (ibanInput) {
        ibanInput.addEventListener('input', function() {
            let val = this.value.replace(/\s+/g, '').toUpperCase();
            this.value = val; 
            const esIbanRegex = /^ES\d{22}$/;
            
            if (val.length > 0) {
                if (esIbanRegex.test(val)) {
                    this.classList.remove('js-invalid');
                    this.classList.add('js-valid');
                    ibanFeedback.textContent = 'Formato IBAN correcto';
                    ibanFeedback.className = 'iban-feedback success';
                } else {
                    this.classList.remove('js-valid');
                    this.classList.add('js-invalid');
                    ibanFeedback.textContent = 'Formato incorrecto (Debe ser ES + 22 dígitos)';
                    ibanFeedback.className = 'iban-feedback error';
                }
            } else {
                this.classList.remove('js-valid', 'js-invalid');
                ibanFeedback.textContent = '';
            }
        });
    }
  </script>
</body>
</html>