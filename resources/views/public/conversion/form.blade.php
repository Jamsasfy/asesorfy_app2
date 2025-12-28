<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1" />
  <title>Datos de Alta - AsesorFy</title>
  <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <style>
    :root {
      --bg-body: #f8fafc;
      --bg-card: #ffffff;
      --text-main: #0f172a;
      --text-muted: #64748b;
      --border-color: #e2e8f0;
      --primary: #0ea5e9;       /* Azul profesional */
      --primary-hover: #0284c7;
      --danger: #ef4444;
      --success: #22c55e;
      
      --radius-input: 8px;
      --radius-card: 12px;
    }

    * { box-sizing: border-box; }

    body {
      margin: 0;
      font-family: 'Inter', sans-serif;
      background-color: var(--bg-body);
      color: var(--text-main);
      line-height: 1.5;
      padding: 30px 15px;
      font-size: 14px; /* Tamaño base más contenido */
    }

    .container {
      max-width: 800px;
      margin: 0 auto;
    }

    /* Tarjeta */
    .card {
      background: var(--bg-card);
      border-radius: var(--radius-card);
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
      border: 1px solid var(--border-color);
      padding: 35px;
    }

    /* Cabecera */
    .header {
      text-align: center;
      margin-bottom: 35px;
      border-bottom: 1px solid var(--border-color);
      padding-bottom: 20px;
    }
    .logo { height: 32px; margin-bottom: 15px; width: auto; }
    .title { font-size: 22px; font-weight: 700; color: var(--text-main); margin: 0 0 5px 0; }
    .subtitle { font-size: 14px; color: var(--text-muted); margin: 0; }

    /* Secciones */
    .section { margin-bottom: 30px; }
    .section-title {
        font-size: 16px; font-weight: 600; color: var(--text-main);
        margin-bottom: 15px; padding-left: 10px;
        border-left: 4px solid var(--primary);
        text-transform: uppercase; letter-spacing: 0.5px;
    }

    /* Grid */
    .grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    .full-width { grid-column: 1 / -1; }

    /* Inputs */
    .field { display: flex; flex-direction: column; gap: 5px; }
    label { font-size: 13px; font-weight: 600; color: #475569; }
    .req { color: var(--danger); }

    input, select, textarea {
      width: 100%;
      padding: 10px 12px;
      font-size: 14px;
      border: 1px solid var(--border-color);
      border-radius: var(--radius-input);
      background-color: #fff;
      color: var(--text-main);
      transition: border-color 0.15s, box-shadow 0.15s;
      font-family: inherit;
    }
    
    input:focus, select:focus, textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
    }

    textarea { resize: vertical; min-height: 80px; }
    
    /* Validaciones */
    .has-error { border-color: var(--danger) !important; background-color: #fef2f2; }
    .is-valid { border-color: var(--success) !important; }
    .error-msg { color: var(--danger); font-size: 12px; margin-top: 2px; }

    /* Selector de Pago Compacto */
    .payment-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 5px; }
    .payment-option { position: relative; cursor: pointer; }
    .payment-option input { position: absolute; opacity: 0; width: 0; height: 0; }
    
    .payment-card {
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 12px;
        display: flex; align-items: center; gap: 10px;
        transition: all 0.2s;
    }
    .payment-card:hover { background-color: #f8fafc; border-color: #cbd5e1; }
    .payment-option input:checked + .payment-card {
        border-color: var(--primary);
        background-color: #f0f9ff;
        box-shadow: 0 0 0 1px var(--primary);
    }
    .p-icon { font-size: 20px; }
    .p-title { font-weight: 600; font-size: 14px; color: var(--text-main); }
    .p-desc { font-size: 11px; color: var(--text-muted); display: block; line-height: 1.2; }

    /* Botón */
    .actions { margin-top: 40px; text-align: center; }
    .btn-submit {
        background-color: var(--primary);
        color: white;
        border: none;
        padding: 12px 40px;
        font-size: 15px; font-weight: 600;
        border-radius: 6px;
        cursor: pointer;
        transition: background 0.2s;
        box-shadow: 0 2px 4px rgba(14, 165, 233, 0.2);
    }
    .btn-submit:hover { background-color: var(--primary-hover); transform: translateY(-1px); }

    /* Helpers */
    .hidden-field { display: none; }
    .note { font-size: 12px; color: #64748b; margin-top: 4px; }
    /* Quitar flechas de inputs type=number */
    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    input[type=number] {
        -moz-appearance: textfield;
    }

    @media (max-width: 640px) {
        .card { padding: 20px; border: none; box-shadow: none; background: transparent; }
        .container { padding: 0; }
        .grid { grid-template-columns: 1fr; }
        .payment-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

  @php
    $prefilled = $prefilled ?? [];
    $getValue = function($campo, $leadField = null) use ($prefilled, $lead) {
        $default = $prefilled[$campo] ?? ($leadField ? $lead->$leadField : '');
        return old($campo, $default);
    };
    $provinciasMap = config('provincias.provincias'); 
  @endphp

  <div class="container">
    <div class="card">
      
      <div class="header">
        <img src="{{ asset('images/logo.png') }}" alt="AsesorFy" class="logo">
        <h1 class="title">Alta de Cliente</h1>
        <p class="subtitle">Completa tus datos para formalizar el contrato.</p>
      </div>

      @if ($errors->any())
        <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px; border-radius: 8px; margin-bottom: 25px; font-size: 13px;">
          <strong>⚠️ Por favor corrige los siguientes errores:</strong>
          <ul style="margin-top:5px; padding-left:20px; margin-bottom:0;">
             @foreach($errors->all() as $error)
               <li>{{ $error }}</li>
             @endforeach
          </ul>
        </div>
      @endif

    <form 
    method="POST" 
    action="{{ route('conversion.submit', ['token' => $link->token]) }}"
>
        @csrf

        <div class="section">
          <div class="section-title">1. Datos de Contacto</div>
          <div class="grid">
            
            <div class="field">
                <label>Nombre (Persona de contacto) <span class="req">*</span></label>
                <input type="text" name="nombre" value="{{ $getValue('nombre', 'nombre') }}" required placeholder="Tu nombre de pila">
                @error('nombre') <div class="error-msg">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label>Apellidos <span class="req">*</span></label>
                <input type="text" name="apellidos" value="{{ $getValue('apellidos', 'apellidos') }}" required placeholder="Tus apellidos">
                @error('apellidos') <div class="error-msg">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label>Email <span class="req">*</span></label>
                <input type="email" name="email" value="{{ $getValue('email', 'email') }}" required placeholder="email@ejemplo.com">
                @error('email') <div class="error-msg">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label>Teléfono Móvil <span class="req">*</span></label>
                <input type="tel" name="telefono" value="{{ $getValue('telefono', 'tfn') }}" required placeholder="600 000 000">
                @error('telefono') <div class="error-msg">{{ $message }}</div> @enderror
            </div>
          </div>
        </div>

        <div class="section">
          <div class="section-title">2. Datos Fiscales (Titular)</div>
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

            <div class="field" id="bloque_dni">
                <label>DNI / NIE <span class="req">*</span></label>
                <input type="text" name="dni" id="input_dni" value="{{ $getValue('dni', 'dni') }}" placeholder="12345678Z">
                @error('dni') <div class="error-msg">{{ $message }}</div> @enderror
            </div>

            <div class="field hidden-field" id="bloque_cif">
                <label>CIF Empresa <span class="req">*</span></label>
                <input type="text" name="cif" id="input_cif" value="{{ $getValue('cif') }}" placeholder="B12345678">
                @error('cif') <div class="error-msg">{{ $message }}</div> @enderror
            </div>

            <div class="field">
                <label id="label_razon_social">Razón Social <span class="req">*</span></label>
                <input type="text" name="razon_social" id="input_razon_social" value="{{ $getValue('razon_social') }}" placeholder="Nombre Fiscal">
                @error('razon_social') <div class="error-msg">{{ $message }}</div> @enderror
                <div class="note" id="note_razon_social"></div>
            </div>

            {{-- CAMPO EXTRA: Solo visible para empresas que quieran especificar marca comercial --}}
            <div class="field hidden-field" id="bloque_nombre_comercial_empresa">
                <label>Nombre Comercial / Marca (Opcional)</label>
                <input type="text" name="nombre_comercial" value="{{ old('nombre_comercial') }}" placeholder="Nombre de tu marca si es distinto a la Razón Social">
            </div>

          </div>
        </div>

        <div class="section">
          <div class="section-title">3. Dirección Fiscal</div>
          <div class="grid">
            <div class="field full-width">
                <label>Dirección Completa <span class="req">*</span></label>
                <input type="text" name="direccion" value="{{ $getValue('direccion') }}" required placeholder="Calle, número, piso...">
                @error('direccion') <div class="error-msg">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label>Código Postal <span class="req">*</span></label>
                <input type="text" name="cp" value="{{ $getValue('cp') }}" required placeholder="28000">
            </div>
            <div class="field">
                <label>Localidad <span class="req">*</span></label>
                <input type="text" name="localidad" value="{{ $getValue('localidad') }}" required>
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
              <input type="text" id="comunidad_autonoma" name="comunidad_autonoma" value="{{ $getValue('comunidad_autonoma') }}" readonly style="background-color: #f1f5f9; color: #64748b;">
            </div>
          </div>
        </div>

      @if($tieneRecurrente)
    <div class="section">
        <div class="section-title">4. Facturación</div>

        <div class="grid">
            <div class="field full-width">
                <label id="labelIBAN">Cuenta IBAN (Impuestos / SS) <span class="req">*</span></label>
                <input
                    type="text"
                    id="cuenta_bancaria_ss"
                    name="cuenta_bancaria_ss"
                    value="{{ $getValue('cuenta_bancaria_ss') }}"
                    required
                    placeholder="ES..."
                    style="text-transform: uppercase; letter-spacing: 1px; font-family: monospace;"
                >
                <div id="ibanFeedback" style="font-size: 12px; margin-top: 4px; min-height: 18px;"></div>

                <div style="font-size: 12px; color: #64748b; margin-top: 2px; line-height: 1.25;">
                    Este IBAN se usará para gestiones con Hacienda y Seguridad Social, y para el pago de tu cuota de autónomo.
                </div>

                @error('cuenta_bancaria_ss') <div class="error-msg">{{ $message }}</div> @enderror
            </div>
        </div>
    </div>
@endif


        <div class="section">
            <div class="section-title">Información adicional</div>
            <div class="grid">
                <div class="field full-width">
                    <label>Observaciones (Opcional)</label>
                    <textarea name="observaciones" placeholder="¿Necesitas contarnos algo más?">{{ $getValue('observaciones') }}</textarea>
                </div>
            </div>
        </div>

        {{-- A) ALTA AUTÓNOMO --}}
        @if($tieneAltaAutonomo)
        <div class="section" style="border: 1px solid #bbf7d0; background-color: #f0fdf4; border-radius: 12px; padding: 20px;">
            <div class="section-title" style="border:none; margin-bottom: 15px; padding-left:0; color: #166534;">
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
                    <label>Nº Seguridad Social (Propio) <span class="req">*</span></label>
                    <input type="text" name="seguridad_social" value="{{ $getValue('seguridad_social') }}" required placeholder="Necesario para el alta en RETA">
                    @error('seguridad_social') <div class="error-msg">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label>¿Tienes certificado digital? <span class="req">*</span></label>
                    <select name="extra_auto_certificado_digital" required>
                        <option value="" disabled selected>Selecciona una opción...</option>
                        <option value="si"  @selected(old('extra_auto_certificado_digital') === 'si')>Sí, ya lo tengo</option>
                        <option value="no"  @selected(old('extra_auto_certificado_digital') === 'no')>No, no lo tengo</option>
                    </select>
                </div>
                <div class="field full-width">
                    <label>Descripción Actividad <span class="req">*</span></label>
                    <textarea name="extra_auto_actividad" required placeholder="Ej: Programador web freelance">{{ old('extra_auto_actividad') }}</textarea>
                </div>
                <div class="field">
                    <label>Lugar de trabajo</label>
                    <select name="extra_auto_lugar" required>
                        <option value="casa"    @selected(old('extra_auto_lugar') === 'casa')>En mi domicilio</option>
                        <option value="local"   @selected(old('extra_auto_lugar') === 'local')>Local propio/alquilado</option>
                        <option value="cliente" @selected(old('extra_auto_lugar') === 'cliente')>En cliente / Itinerante</option>
                    </select>
                </div>
                <div class="field">
                    <label>¿Has sido autónomo antes? (Tarifa Plana)</label>
                    <select name="extra_auto_tarifa_plana" required>
                        <option value="no" @selected(old('extra_auto_tarifa_plana') === 'no')>No (Solicitar Tarifa Plana)</option>
                        <option value="si" @selected(old('extra_auto_tarifa_plana') === 'si')>Sí, hace menos de 2 años</option>
                    </select>
                </div>
            </div>
        </div>
        @endif

        {{-- B) CREACIÓN S.L. --}}
        @if($tieneCreacionSociedad)
        <div class="section" style="border: 1px solid #ddd6fe; background-color: #f5f3ff; border-radius: 12px; padding: 20px;">
            <div class="section-title" style="border:none; margin-bottom: 15px; padding-left:0; color: #5b21b6;">
                🏗️ Datos Constitución S.L.
            </div>
            <div class="grid">
                <div class="field full-width">
                    <label>5 nombres de preferencia <span class="req">*</span></label>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <input type="text" name="extra_sl_nombre1" value="{{ old('extra_sl_nombre1') }}" placeholder="1. Opción principal" required>
                        <input type="text" name="extra_sl_nombre2" value="{{ old('extra_sl_nombre2') }}" placeholder="2. Opción">
                        <input type="text" name="extra_sl_nombre3" value="{{ old('extra_sl_nombre3') }}" placeholder="3. Opción">
                        <input type="text" name="extra_sl_nombre4" value="{{ old('extra_sl_nombre4') }}" placeholder="4. Opción">
                        <input type="text" name="extra_sl_nombre5" value="{{ old('extra_sl_nombre5') }}" placeholder="5. Opción">
                    </div>
                </div>

                <div class="field">
                    <label>Tipo de aportación de capital <span class="req">*</span></label>
                    <select name="extra_sl_aportacion_tipo" id="aportacion_tipo" required>
                        <option value="" disabled selected>Selecciona una opción...</option>
                        <option value="dineraria" @selected(old('extra_sl_aportacion_tipo') === 'dineraria')>Dineraria</option>
                        <option value="bienes"    @selected(old('extra_sl_aportacion_tipo') === 'bienes')>Bienes / aportación no dineraria</option>
                        <option value="mixta"     @selected(old('extra_sl_aportacion_tipo') === 'mixta')>Mixta (dineraria + bienes)</option>
                    </select>
                </div>

                <div class="field" id="bloque_capital_dinerario">
                    <label>Capital social dinerario (€) <span class="req">*</span></label>
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
                        {{-- Si hay old values (error de validación), renderizar loop, si no, uno vacío --}}
                        @php 
                           $oldSocios = old('extra_sl_socios_nombre', ['']); 
                        @endphp
                        
                        @foreach($oldSocios as $index => $socioVal)
                        <div class="socio-row" style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 10px;">
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
                                    <div style="display: grid; grid-template-columns: 110px auto 220px; align-items: center; column-gap: 12px; margin-top: 4px;">
                                        <input type="number" name="extra_sl_socios_porcentaje[]" class="socio-porcentaje" min="0" max="100" step="0.01" placeholder="0 – 100" 
                                               value="{{ old('extra_sl_socios_porcentaje.'.$index) }}" required style="-moz-appearance:textfield;">
                                    
                                        <label style="display: inline-flex; align-items: center; gap: 8px; font-weight: 500; font-size: 13px; margin: 0;">
                                            ¿Está casado/a?
                                            <input type="checkbox" class="socio-casado-toggle">
                                        </label>

                                        <select name="extra_sl_socios_regimen[]" class="socio-regimen-select hidden-field" style="width: 100%;">
                                            <option value="">Régimen matrimonial...</option>
                                            <option value="gananciales" @selected(old('extra_sl_socios_regimen.'.$index) == 'gananciales')>Sociedad de gananciales</option>
                                            <option value="separacion_bienes" @selected(old('extra_sl_socios_regimen.'.$index) == 'separacion_bienes')>Separación de bienes</option>
                                            <option value="participacion" @selected(old('extra_sl_socios_regimen.'.$index) == 'participacion')>Régimen de participación</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <button type="button" id="add_socio_btn" style="margin-top: 6px; padding: 6px 10px; font-size: 12px; border-radius: 6px; border: 1px solid #c4b5fd; background-color: #ede9fe; cursor: pointer;">
                        + Añadir otro socio
                    </button>
                </div>

                <div class="field">
                    <label>Tipo de administrador <span class="req">*</span></label>
                    <select name="extra_sl_tipo_admin" required>
                        <option value="unico"        @selected(old('extra_sl_tipo_admin') === 'unico')>Administrador único</option>
                        <option value="solidarios"   @selected(old('extra_sl_tipo_admin') === 'solidarios')>Administradores solidarios</option>
                        <option value="mancomunados" @selected(old('extra_sl_tipo_admin') === 'mancomunados')>Administradores mancomunados</option>
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

        {{-- C) CAPITALIZACIÓN PARO --}}
        @if($tieneCapitalizacion)
        <div class="section" style="border: 1px solid #fde68a; background-color: #fffbeb; border-radius: 12px; padding: 20px;">
            <div class="section-title" style="border:none; margin-bottom: 15px; padding-left:0; color: #b45309;">
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
                        <option value="cuotas"     @selected(old('extra_cap_modalidad') === 'cuotas')>Cuotas mensuales</option>
                        <option value="mixto"      @selected(old('extra_cap_modalidad') === 'mixto')>Mixto</option>
                        <option value="no_lo_se"   @selected(old('extra_cap_modalidad') === 'no_lo_se')>No lo sé</option>
                    </select>
                </div>
                <div class="field full-width">
                    <label>Memoria / idea del proyecto <span class="req">*</span></label>
                    <textarea name="extra_cap_memoria" required placeholder="Describe brevemente...">{{ old('extra_cap_memoria') }}</textarea>
                </div>
                <div class="field">
                    <label>Fecha de inicio del paro <span class="req">*</span></label>
                    <input type="date" name="extra_cap_fecha_paro" value="{{ old('extra_cap_fecha_paro') }}" required>
                </div>
                <div class="field">
                    <label>Prestación mensual actual (€) <span class="req">*</span></label>
                    <input type="text" name="extra_cap_prestacion_mensual" value="{{ old('extra_cap_prestacion_mensual') }}" required>
                </div>
                <div class="field">
                    <label>Tiempo de paro disponible (meses) <span class="req">*</span></label>
                    <input type="number" name="extra_cap_duracion_paro" min="1" step="1" value="{{ old('extra_cap_duracion_paro') }}" required>
                </div>
                <div class="field">
                    <label>Oficina / localidad del SEPE</label>
                    <input type="text" name="extra_cap_oficina_sepe" value="{{ old('extra_cap_oficina_sepe') }}">
                </div>
            </div>
        </div>
        @endif

        <div class="actions">
           <p style="font-size: 12px; color: #64748b; margin-bottom: 12px; line-height: 1.45;">
            Una vez nos envíes estos datos, tu asesor revisará la información...
           </p>
            <button type="submit" class="btn-submit">Guardar y Continuar</button>
        </div>

      </form>
    </div>
  </div>

  <script>
    // 1. Lógica Reactiva: Autónomo vs Empresa
    const selectTipo = document.getElementById('tipo_cliente_select');
    const bloqueDni = document.getElementById('bloque_dni');
    const bloqueCif = document.getElementById('bloque_cif');
    const inputDni = document.getElementById('input_dni');
    const inputCif = document.getElementById('input_cif');
    
    // Referencias para Razón Social vs Nombre Comercial
    const labelRazon = document.getElementById('label_razon_social');
    const inputRazon = document.getElementById('input_razon_social');
    const noteRazon = document.getElementById('note_razon_social');
    // Nuevo campo extra para empresa
    const bloqueNombreComercialEmpresa = document.getElementById('bloque_nombre_comercial_empresa');

    function togglePersona() {
        if(!selectTipo) return;
        const selectedOption = selectTipo.options[selectTipo.selectedIndex];
        // Si no hay selección o es "Autónomo" (contiene la palabra), es física.
        const isAutonomo = selectedOption.value === '' || selectedOption.text.toLowerCase().includes('autónomo') || selectedOption.dataset.isAutonomo === 'true';

        if (isAutonomo) {
            // --- MODO AUTÓNOMO ---
            
            // 1. Identificación
            bloqueCif.classList.add('hidden-field');
            bloqueDni.classList.remove('hidden-field');
            inputDni.required = true;
            inputCif.required = false;
            
            // 2. Nombres: El input "razon_social" se usa para el Nombre Comercial (Marca)
            labelRazon.innerHTML = 'Nombre Comercial / Marca (Opcional)';
            inputRazon.placeholder = 'Ej: Frutería Paco (Déjalo vacío si no tienes marca)';
            inputRazon.required = false; // No obligatorio para autónomos
            
            // 3. Nota explicativa
            noteRazon.style.display = 'block';
            noteRazon.innerHTML = 'Tu <strong>Razón Social legal</strong> será tu Nombre y Apellidos automáticamente.<br>Pon aquí el nombre de tu negocio solo si tienes una marca comercial.';

            // 4. Ocultar el campo extra de empresa
            bloqueNombreComercialEmpresa.classList.add('hidden-field');

        } else {
            // --- MODO EMPRESA ---

            // 1. Identificación
            bloqueDni.classList.add('hidden-field');
            bloqueCif.classList.remove('hidden-field');
            inputCif.required = true;
            inputDni.required = false;

            // 2. Nombres: El input "razon_social" es la Razón Social Legal
            labelRazon.innerHTML = 'Razón Social (Denominación S.L.) <span class="req">*</span>';
            inputRazon.placeholder = 'Ej: AsesorFy S.L.';
            inputRazon.required = true; // Obligatorio para empresas

            // 3. Ocultar nota de autónomo
            noteRazon.style.display = 'none';

            // 4. Mostrar el campo extra para Nombre Comercial
            bloqueNombreComercialEmpresa.classList.remove('hidden-field');
        }
    }

    if(selectTipo) {
        selectTipo.addEventListener('change', togglePersona);
        togglePersona(); 
    }

    // 2. Provincias
    const provinciasMap = @json($provinciasMap);
    const provSelect = document.getElementById('provincia');
    const comInput = document.getElementById('comunidad_autonoma');
    if (provSelect && comInput) {
        provSelect.addEventListener('change', () => {
            comInput.value = provinciasMap[provSelect.value] || '';
        });
        if(provSelect.value) provSelect.dispatchEvent(new Event('change'));
    }

    // 3. IBAN
    const ibanInput = document.getElementById('cuenta_bancaria_ss');
    const ibanFeedback = document.getElementById('ibanFeedback');
    if(ibanInput){
        ibanInput.addEventListener('input', function(){
             let v = this.value.replace(/\s+/g, '').toUpperCase();
             this.value = v;
             if(v.length >= 5) {
                 if(/^ES\d{22}$/.test(v)) {
                     ibanFeedback.textContent = '✓ Correcto';
                     ibanFeedback.style.color = 'var(--success)';
                     this.style.borderColor = 'var(--success)';
                 } else {
                     if(v.length >= 24){
                         ibanFeedback.textContent = '✕ Incorrecto';
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

    // 5. Creación S.L. - Aportación
    const aportacionSelect = document.getElementById('aportacion_tipo');
    const bloqueCapital = document.getElementById('bloque_capital_dinerario');
    const bloqueBienes = document.getElementById('bloque_bienes_aportar');

    function actualizarAportacionUI() {
        if (!aportacionSelect) return;
        const tipo = aportacionSelect.value;
        
        // Reset visibilidad
        if(bloqueCapital) bloqueCapital.classList.remove('hidden-field');
        if(bloqueBienes) bloqueBienes.classList.add('hidden-field');

        if (tipo === 'bienes') {
            if(bloqueCapital) bloqueCapital.classList.add('hidden-field');
            if(bloqueBienes) bloqueBienes.classList.remove('hidden-field');
        } else if (tipo === 'mixta') {
            if(bloqueCapital) bloqueCapital.classList.remove('hidden-field');
            if(bloqueBienes) bloqueBienes.classList.remove('hidden-field');
        }
    }
    if (aportacionSelect) {
        aportacionSelect.addEventListener('change', actualizarAportacionUI);
        actualizarAportacionUI();
    }

    // 6. Creación S.L. - Socios Repeater
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
        addSocioBtn.addEventListener('click', function () {
            const firstRow = sociosContainer.querySelector('.socio-row');
            if (!firstRow) return;
            const newRow = firstRow.cloneNode(true);

            newRow.querySelectorAll('input').forEach(function (input) {
                if (input.type === 'checkbox') input.checked = false;
                else input.value = '';
            });
            newRow.querySelectorAll('select').forEach(function (select) {
                select.value = '';
                if (select.classList.contains('socio-regimen-select')) select.classList.add('hidden-field');
            });
            sociosContainer.appendChild(newRow);
            bindSocioRow(newRow);
        });
    }
  </script>
</body>
</html>