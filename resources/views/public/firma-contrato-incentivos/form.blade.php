<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Firmar Contrato de Incentivos — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
    <style>
        canvas { touch-action: none; }
        .contract-content h2 {
            font-size: 1.1rem;
            font-weight: bold;
            color: #0ea5e9;
            border-bottom: 2px solid #0ea5e9;
            padding-bottom: 6px;
            margin-top: 1.5rem;
            margin-bottom: 0.75rem;
        }
        .contract-content p {
            font-size: 0.93rem;
            line-height: 1.7;
            color: #374151;
            text-align: justify;
            margin-bottom: 0.75rem;
        }
        .contract-content ul {
            font-size: 0.93rem;
            color: #374151;
            margin: 0.5rem 0 0.75rem 1.25rem;
        }
        .contract-content li { margin-bottom: 0.3rem; }
        .contract-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        .contract-content th {
            background-color: #0ea5e9;
            color: white;
            padding: 10px;
            text-align: left;
            font-size: 0.85rem;
        }
        .contract-content td {
            padding: 10px;
            border: 1px solid #e5e7eb;
            font-size: 0.9rem;
        }
        .contract-content tr:nth-child(even) { background-color: #f9fafb; }
        .badge-obligatoria {
            background-color: #ef4444;
            color: white;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: bold;
        }
        .info-box {
            background-color: #dbeafe;
            border-left: 4px solid #0ea5e9;
            padding: 12px 16px;
            margin: 12px 0;
            border-radius: 4px;
        }
        .warning-box {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px 16px;
            margin: 12px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-4">
    <div class="max-w-3xl mx-auto">

        {{-- Header --}}
        <div class="bg-white rounded-xl shadow p-6 text-center mb-4">
            <img src="{{ asset('images/logo.png') }}" alt="AsesorFy" class="h-12 mx-auto mb-3">
            <h1 class="text-2xl font-bold" style="color: #0ea5e9;">Contrato de Incentivos Comerciales</h1>
            <p class="text-gray-600 mt-1">{{ $contrato->tipo === 'base' ? 'Contrato Base' : 'Anexo' }}</p>
            <p class="text-gray-500 text-sm mt-1">{{ $contrato->comercial->name }}</p>
        </div>

        {{-- Contenido del contrato --}}
        <div class="bg-white rounded-xl shadow p-8 mb-4 contract-content">

            @if($contrato->tipo === 'anexo')
            @php
                $ultimoAnterior = $contrato->comercial->contratosIncentivos()
                    ->whereNotNull('fecha_firma')
                    ->where('id', '<', $contrato->id)
                    ->latest('fecha_firma')
                    ->first();

                $contratoRef = $ultimoAnterior ?? $contrato->contratoBase;
                $tipoRef     = $ultimoAnterior
                    ? ($ultimoAnterior->tipo === 'base' ? 'Contrato Base' : 'Anexo anterior')
                    : 'Contrato Base';
            @endphp
            <div class="warning-box">
                <p class="font-semibold text-gray-900 mb-1">📋 Anexo al {{ $tipoRef }}</p>
                <p class="mb-0">
                    Este documento constituye un ANEXO
                    @if($ultimoAnterior)
                        al {{ $tipoRef }} firmado el <strong>{{ $contratoRef->fecha_firma->format('d/m/Y') }}</strong>.
                    @else
                        al Contrato Base de Incentivos firmado el <strong>{{ $contrato->contratoBase->fecha_firma->format('d/m/Y') }}</strong>.
                    @endif
                    <br>
                    El presente anexo modifica únicamente las reglas de comisión descritas a continuación.
                    Todas las demás condiciones
                    @if($ultimoAnterior) del contrato base y anexos anteriores
                    @else del contrato base
                    @endif
                    siguen plenamente vigentes.
                </p>
            </div>
            @endif

            <div class="info-box">
                <p class="font-semibold text-gray-900 mb-1">Datos del Comercial</p>
                <p class="mb-0">
                    <strong>Nombre:</strong> {{ $contrato->comercial->name }}<br>
                    <strong>Email:</strong> {{ $contrato->comercial->email }}<br>
                    <strong>DNI:</strong> {{ $contrato->comercial->trabajador->dni_o_cif ?? 'N/A' }}<br>
                    <strong>Fecha de alta:</strong> {{ $contrato->comercial->fecha_inicio_comercial?->format('d/m/Y') ?? 'N/A' }}
                </p>
            </div>

            <p style="margin-bottom: 1.5rem;">
                Al objeto de garantizar los objetivos del departamento comercial y determinar los valores mínimos
                de productividad, se firma este acuerdo, como anexo al contrato de trabajo suscrito entre la
                empresa y el trabajador.
            </p>

            <p style="margin-bottom: 1rem;"><strong>Por lo siguiente, se acuerda:</strong></p>

            @php
                $empresa = \App\Models\VariableConfiguracion::whereIn('nombre_variable', [
                    'empresa_razon_social', 'empresa_cif', 'empresa_direccion_calle',
                    'empresa_direccion_cp', 'empresa_direccion_ciudad', 'empresa_direccion_provincia',
                ])->pluck('valor_variable', 'nombre_variable');

                $empresaDireccion = trim(
                    ($empresa['empresa_direccion_calle'] ?? '') . ' - ' .
                    ($empresa['empresa_direccion_cp'] ?? '') . ' ' .
                    ($empresa['empresa_direccion_ciudad'] ?? '') . ', ' .
                    ($empresa['empresa_direccion_provincia'] ?? '')
                );
            @endphp

            <p>
                <strong>DE UNA PARTE:</strong> {{ $empresa['empresa_razon_social'] ?? 'AsesorFy S.L.' }},
                con CIF {{ $empresa['empresa_cif'] ?? 'B12345678' }},
                domicilio en {{ $empresaDireccion }},
                en adelante <strong>LA EMPRESA</strong>.
            </p>

            <p>
                <strong>DE OTRA PARTE:</strong>
                {{ $contrato->comercial->trabajador->nombre ?? $contrato->comercial->name }}
                {{ $contrato->comercial->trabajador->apellidos ?? '' }},
                con DNI {{ $contrato->comercial->trabajador->dni_o_cif ?? 'N/A' }},
                en adelante <strong>EL TRABAJADOR</strong>.
            </p>

            <h2 style="margin-top: 1.5rem;">EXPONEN</h2>

            @if($contrato->tipo === 'base')
            <p>
                <strong>I.</strong> Que el trabajador recibe este acuerdo que vincula con su contrato de trabajo,
                donde se establecen los valores mínimos de resultados deseados en el puesto que ocupa.
            </p>

            <p>
                <strong>II.</strong> Que el trabajador está asignado al Departamento Comercial.
            </p>

            <p>
                <strong>III.</strong> Que el presente acuerdo invalida cualquier acuerdo de incentivos anterior.
            </p>
            @else
            <p>
                <strong>I.</strong> Que el trabajador firmó el Contrato Base de Incentivos el
                {{ $contrato->contratoBase->fecha_firma->format('d/m/Y') }}.
            </p>

            <p>
                <strong>II.</strong> Que han surgido modificaciones en las reglas de comisión que requieren
                actualización mediante el presente Anexo.
            </p>

            <p>
                <strong>III.</strong> Que el presente Anexo modifica únicamente las reglas especificadas,
                manteniendo vigentes todas las demás condiciones del Contrato Base.
            </p>
            @endif

            <h2 style="margin-top: 2rem;">CLÁUSULAS</h2>

            <h2>1. OBJETO DEL CONTRATO</h2>
            <p>
                El presente contrato tiene por objeto establecer las condiciones y reglas del sistema de incentivos
                por cumplimiento de objetivos comerciales aplicables al trabajador en el marco de su relación
                laboral con AsesorFy.
            </p>

            @if($contrato->tipo === 'anexo')
            <h2>2. REGLAS DE COMISIÓN ACTUALIZADAS</h2>
            <p>Con este anexo, las reglas de comisión quedan actualizadas de la siguiente manera:</p>

            @php
                $ultimoContratoAnterior = $contrato->comercial->contratosIncentivos()
                    ->whereNotNull('fecha_firma')
                    ->where('id', '<', $contrato->id)
                    ->latest('fecha_firma')
                    ->first();

                $contratoReferencia = $ultimoContratoAnterior ?? $contrato->contratoBase;
                $reglasAnterior     = collect($contratoReferencia->reglas_snapshot ?? []);
                $reglasActuales     = collect($contrato->reglas_snapshot);

                $cambios = [];
                foreach ($reglasActuales as $reglaActual) {
                    $reglaAnterior = $reglasAnterior->firstWhere('id', $reglaActual['id']);
                    if (!$reglaAnterior) {
                        $cambios[] = ['tipo' => 'nueva', 'regla' => $reglaActual];
                    } elseif (
                        $reglaAnterior['minimo'] != $reglaActual['minimo'] ||
                        $reglaAnterior['porcentaje'] != $reglaActual['porcentaje'] ||
                        $reglaAnterior['es_obligatoria'] != $reglaActual['es_obligatoria']
                    ) {
                        $cambios[] = ['tipo' => 'modificada', 'regla' => $reglaActual, 'anterior' => $reglaAnterior];
                    }
                }
                foreach ($reglasAnterior as $reglaAnterior) {
                    if (!$reglasActuales->contains('id', $reglaAnterior['id'])) {
                        $cambios[] = ['tipo' => 'eliminada', 'regla' => $reglaAnterior];
                    }
                }

                $tipoRefCambios = $ultimoContratoAnterior
                    ? ($ultimoContratoAnterior->tipo === 'base' ? 'contrato base' : 'anexo anterior')
                    : 'contrato base';
            @endphp

            @if(!empty($cambios))
            <div class="info-box">
                <p class="font-semibold mb-2">🔄 Cambios respecto al {{ $tipoRefCambios }}:</p>
                <ul class="mb-0">
                    @foreach($cambios as $cambio)
                        @if($cambio['tipo'] === 'nueva')
                            <li><strong>NUEVA:</strong> {{ $cambio['regla']['nombre'] }} — Mínimo: €{{ number_format($cambio['regla']['minimo'], 2, ',', '.') }} | Comisión: {{ $cambio['regla']['porcentaje'] }}%</li>
                        @elseif($cambio['tipo'] === 'modificada')
                            <li><strong>MODIFICADA:</strong> {{ $cambio['regla']['nombre'] }}
                                @if($cambio['anterior']['minimo'] != $cambio['regla']['minimo'])
                                    <br>&nbsp;&nbsp;&nbsp;• Mínimo: €{{ number_format($cambio['anterior']['minimo'], 2, ',', '.') }} → €{{ number_format($cambio['regla']['minimo'], 2, ',', '.') }}
                                @endif
                                @if($cambio['anterior']['porcentaje'] != $cambio['regla']['porcentaje'])
                                    <br>&nbsp;&nbsp;&nbsp;• Comisión: {{ $cambio['anterior']['porcentaje'] }}% → {{ $cambio['regla']['porcentaje'] }}%
                                @endif
                                @if($cambio['anterior']['es_obligatoria'] != $cambio['regla']['es_obligatoria'])
                                    <br>&nbsp;&nbsp;&nbsp;• {{ $cambio['regla']['es_obligatoria'] ? 'Ahora es OBLIGATORIA' : 'Ya no es obligatoria' }}
                                @endif
                            </li>
                        @elseif($cambio['tipo'] === 'eliminada')
                            <li><strong>ELIMINADA:</strong> {{ $cambio['regla']['nombre'] }}</li>
                        @endif
                    @endforeach
                </ul>
            </div>
            @endif

            <p class="mt-4">Tabla completa de reglas vigentes tras este anexo:</p>
            @else
            <h2>2. REGLAS DE COMISIÓN ASIGNADAS</h2>
            <p>El Comercial tiene asignadas las siguientes reglas de comisión:</p>
            @endif

            <table>
                <thead>
                    <tr>
                        <th>Regla</th>
                        <th>Mínimo Mensual</th>
                        <th>Porcentaje</th>
                        <th>Penalización</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($contrato->reglas_snapshot as $regla)
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

            @php
                $tieneObligatorias = collect($contrato->reglas_snapshot)->contains('es_obligatoria', true);
                $config = (object) $contrato->config_snapshot;
            @endphp

            @if($tieneObligatorias)
            <div class="warning-box">
                <p class="font-semibold mb-1">⚠️ Importante: Reglas Obligatorias</p>
                <p class="mb-0">
                    Las reglas marcadas como OBLIGATORIAS deben alcanzar el mínimo mensual establecido.
                    En caso de no alcanzar el mínimo en una o más reglas obligatorias, se anularán las comisiones
                    de TODAS las reglas para ese mes.
                </p>
            </div>
            @endif

            @if($contrato->tipo === 'base')
            <h2>3. CÁLCULO DE COMISIONES</h2>

            <p><strong>3.1. Base de Cálculo</strong></p>
            <p>
                Las comisiones se calcularán mensualmente sobre la facturación neta del Comercial,
                aplicando el porcentaje establecido en cada regla sobre la cantidad que exceda del mínimo mensual.
            </p>
            <p><strong>Fórmula:</strong> Comisión = (Facturación Neta - Mínimo) × Porcentaje</p>

            <p><strong>3.2. Penalización por Bajas Anticipadas</strong></p>
            <p>
                Las bajas de servicios recurrentes antes del periodo establecido en cada regla se descontarán
                de la facturación bruta del mes en que se produzca la baja.
            </p>

            <p><strong>3.3. Bonos Excepcionales</strong></p>
            <p>
                La empresa se reserva el derecho de otorgar bonos excepcionales por logros específicos,
                los cuales se sumarán al total de comisiones independientemente del cumplimiento de mínimos.
            </p>

            <h2>4. PERIODO DE PRUEBA</h2>
            @php
                $fechaAlta    = $contrato->comercial->fecha_inicio_comercial;
                $mesesPrueba  = $contrato->comercial->meses_prueba;
            @endphp

            <p>
                @if($fechaAlta && $mesesPrueba)
                El Comercial tiene establecido un período de prueba de
                <strong>{{ $mesesPrueba }} meses naturales completos</strong>
                posteriores al mes de alta ({{ $fechaAlta->format('d/m/Y') }}).
                @else
                El Comercial tiene establecido un período de prueba de
                <strong>3 meses naturales completos</strong>
                posteriores al mes de alta.
                @endif
            </p>

            @if($fechaAlta && $mesesPrueba)
            @php
                $mesInicioPrueba = $fechaAlta->copy()->addMonth()->startOfMonth();
                $mesFinPrueba    = $mesInicioPrueba->copy()->addMonths($mesesPrueba - 1)->endOfMonth();
                $primerMesComision = $mesFinPrueba->copy()->addMonth()->startOfMonth();
            @endphp
            <p>
                <strong>Periodo de prueba:</strong>
                Del {{ $mesInicioPrueba->format('d/m/Y') }} al {{ $mesFinPrueba->format('d/m/Y') }}
                ({{ $mesInicioPrueba->translatedFormat('F Y') }} — {{ $mesFinPrueba->translatedFormat('F Y') }}).
            </p>
            @endif

            <p>Durante este período:</p>
            <ul>
                <li>Los informes mensuales de comisiones son <strong>formativos</strong></li>
                <li>No se aplican penalizaciones por no alcanzar mínimos</li>
                <li>No se generan comisiones (solo informes de seguimiento)</li>
                <li>Los meses en período de prueba no cuentan para el cálculo de despido por bajo rendimiento</li>
            </ul>

            @if($fechaAlta && $mesesPrueba)
            <p>
                <strong>Primera comisión:</strong> Las comisiones comenzarán a devengarse a partir de
                {{ $primerMesComision->translatedFormat('F Y') }}.
            </p>
            @endif

            <h2>5. CONSECUCIÓN DE MÍNIMOS</h2>

            <p>
                El trabajador debe estar siempre por encima de los valores mínimos exigidos para la
                rentabilidad del puesto de trabajo y los objetivos de la empresa. Si el trabajador está por
                debajo de los mínimos, recibirá informe de Dirección y RRHH con los valores recibidos y las
                medidas de control personalizadas para trabajar la consecución de mínimos.
            </p>

            <p>
                El incumplimiento de los objetivos mensuales establecidos será considerado una falta grave
                en el desempeño de sus funciones cuando dicho incumplimiento se deba a falta de diligencia,
                desinterés o actitud negligente por parte del trabajador.
            </p>

            <div class="info-box">
                <p class="font-semibold mb-1">📋 Política de despido por bajo rendimiento:</p>
                <p class="mb-0">
                    En caso de que no se alcancen los objetivos durante
                    <strong>{{ $config->meses_consecutivos_despido }} meses consecutivos</strong> o
                    <strong>{{ $config->meses_alternos_despido }} meses alternos</strong> en un período de
                    <strong>{{ $config->periodo_meses_alternos }} meses</strong>, se procederá a la extinción
                    de la relación laboral por despido disciplinario, conforme a lo dispuesto en el artículo 54
                    del Estatuto de los Trabajadores, previo análisis de la situación y comunicación al trabajador.
                </p>
            </div>

            <p>
                Los resultados serán definidos y comunicados mensualmente a través de los medios internos
                de la empresa, asegurándose su accesibilidad al trabajador.
            </p>

            <h2>8. INGRESO DE LOS INCENTIVOS</h2>
            <p>
                Los incentivos aprobados se abonarán en bruto en la nómina del mes siguiente al de su generación,
                estando sujetos a las retenciones fiscales y de Seguridad Social que correspondan según la
                situación del trabajador.
            </p>
            <p>
                En caso de baja voluntaria o fin de contrato, los incentivos no abonados no serán
                incluidos en el finiquito.
            </p>

            <h2>6.1. ESTORNO DE COMISIONES</h2>

            <p>
                Se realizarán revisiones por parte de Dirección para analizar las ventas realizadas.
                Se descontarán de las comisiones los siguientes casos:
            </p>

            @php
                $reglasConPenalizacion = collect($contrato->reglas_snapshot)->where('penalizacion', '>', 0);
            @endphp

            @if($reglasConPenalizacion->isNotEmpty())
            <div class="warning-box">
                <p class="font-semibold mb-1">⚠️ Penalizaciones por bajas anticipadas:</p>
                <ul class="mb-0">
                    @foreach($reglasConPenalizacion as $regla)
                    <li>
                        <strong>{{ $regla['nombre'] }}:</strong> Bajas de servicios recurrentes antes de
                        {{ $regla['penalizacion'] }} meses desde la venta se descontarán de la facturación
                        bruta del mes en que se produzca la baja.
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

            <p>
                Los pagos de los incentivos siempre serán ingresados en la nómina una vez conseguidos,
                independientemente de la potestad de revisión que tiene Dirección para revisar el estorno
                de los mismos.
            </p>

            @if($contrato->tipo === 'base')
            <h2>6.2. AJUSTE PROPORCIONAL DE MÍNIMOS</h2>

            <p>
                Para garantizar la equidad y no discriminación, los mínimos mensuales se ajustarán
                de forma proporcional a los días efectivamente disponibles para trabajar en el mes.
            </p>

            <div class="info-box">
                <p class="font-semibold mb-1">📊 Fórmula de ajuste:</p>
                <p class="mb-0" style="font-family: monospace; background: #f3f4f6; padding: 8px; border-radius: 4px;">
                    Mínimo ajustado = Mínimo mensual × (Días disponibles / Días laborables del mes)
                </p>
            </div>

            <p><strong>Días disponibles:</strong> Días laborables del mes menos los días de vacaciones, bajas médicas y permisos retribuidos.</p>

            <p>
                Esta medida tiene carácter <strong>favorable</strong> para el trabajador, facilitando
                el cumplimiento de objetivos en meses con ausencias justificadas por vacaciones,
                incapacidad temporal o permisos.
            </p>

            <p>
                Las comisiones se calcularán sobre la facturación real obtenida en los días trabajados,
                aplicando el porcentaje establecido sobre el excedente del mínimo ajustado.
            </p>

            <div class="info-box">
                <p class="font-semibold mb-1">📝 Ejemplo práctico:</p>
                <ul class="mb-0">
                    <li>Mes: 22 días laborables</li>
                    <li>Ausencias: 10 días (vacaciones + baja médica)</li>
                    <li>Días disponibles: 12 días</li>
                    <li>Mínimo base de regla: €1.000</li>
                    <li><strong>Mínimo ajustado: €1.000 × (12/22) = €545,45</strong></li>
                    <li>Si factura €600 → Excede mínimo ajustado → Genera comisión sobre €54,55</li>
                </ul>
            </div>
            @endif

            <h2>7. MODIFICACIONES Y ACTUALIZACIÓN DEL ACUERDO</h2>

            <p>
                Teniendo en cuenta los cambios en la tipología de producto, precios y fechas de ventas,
                la empresa podrá modificar este acuerdo en cualquier momento, preavisando al trabajador
                el mismo día del cambio.
            </p>

            <p>
                Cualquier modificación en las reglas de comisión asignadas (adición, eliminación o cambio
                de parámetros) requerirá la firma de un <strong>ANEXO</strong> a este contrato base.
                El nuevo acuerdo entrará en vigor el siguiente mes de su firma.
            </p>

            <p>
                El Comercial no podrá percibir comisiones sobre reglas no firmadas.
            </p>

            @else
            <h2>3. VIGENCIA DE CONDICIONES ANTERIORES</h2>
            <p>
                Todas las condiciones establecidas en el Contrato Base firmado el
                <strong>{{ $contrato->contratoBase->fecha_firma->format('d/m/Y') }}</strong>
                continúan plenamente vigentes, excepto las reglas de comisión que quedan
                actualizadas según lo descrito en la sección 2.
            </p>
            <p>En particular, siguen aplicándose:</p>
            <ul>
                <li>Las fórmulas de cálculo de comisiones</li>
                <li>Las penalizaciones por bajas anticipadas</li>
                <li>El sistema de bonos excepcionales</li>
                <li>Las condiciones de despido por bajo rendimiento</li>
                <li>La forma de abono de incentivos</li>
            </ul>
            @endif

            <h2>9. ACTOS DESLEALES O FRAUDULENTOS</h2>

            <p>
                El trabajador se compromete a actualizar toda la información de forma honesta y leal,
                sin intentar cambiar datos que faciliten la obtención de incentivos. De la misma forma,
                no simulará acciones para incrementar los indicadores de ventas.
            </p>

            <p>
                RRHH y Dirección de AsesorFy analizará y revisará en cualquier momento los datos, ventas
                y valores en los medios internos de la empresa. Si se detecta cualquier actitud que vaya
                en contra de lo pactado y de la buena fe contractual, podrá ser motivo de sanción.
            </p>

            <h2>10. ACUERDO DE CONFIDENCIALIDAD</h2>

            <p>
                Ambas partes acuerdan mantener el presente Acuerdo de forma confidencial, aún después de
                terminar sus relaciones laborales.
            </p>

            <h2>11. ACEPTACIÓN Y FIRMA</h2>
            <p>
                El Comercial declara haber leído y comprendido todas las cláusulas de este contrato,
                y las acepta en su totalidad mediante su firma digital.
            </p>

        </div>

        {{-- Formulario de firma --}}
        <div class="bg-white rounded-xl shadow p-8 mb-8">
            <h2 class="text-lg font-bold text-gray-900 mb-2">Firma del Contrato</h2>
            <p class="text-sm text-gray-500 mb-6">
                Al firmar, confirmas que has leído y aceptas el contenido del contrato anterior.
            </p>

            <div class="mb-6">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Firma aquí:</label>
                <div class="border-2 border-gray-300 rounded-lg overflow-hidden bg-white">
                    <canvas id="signatureCanvas" width="600" height="200" class="w-full"></canvas>
                </div>
                <button type="button" id="clearBtn" class="mt-2 text-xs text-gray-400 hover:text-red-500 underline">
                    Borrar firma
                </button>
            </div>

            <button type="button" id="submitBtn"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition">
                ✍️ Firmar Contrato
            </button>

            <div id="message" class="mt-4"></div>
        </div>

    </div>

    <script>
        const canvas = document.getElementById('signatureCanvas');
        const sigPad = new SignaturePad(canvas, { backgroundColor: 'rgba(255,255,255,1)' });

        document.getElementById('clearBtn').addEventListener('click', () => sigPad.clear());

        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);
            sigPad.clear();
        }
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        document.getElementById('submitBtn').addEventListener('click', async function () {
            if (sigPad.isEmpty()) {
                showMessage('Por favor, dibuja tu firma antes de continuar.', 'error');
                return;
            }

            const firmaBase64 = sigPad.toDataURL();

            try {
                const response = await fetch('{{ route("firmar-contrato-incentivos.store", $contrato->token_firma) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ firma: firmaBase64 })
                });

                const data = await response.json();

                if (data.success) {
                    showMessage('¡Contrato firmado correctamente!', 'success');
                    setTimeout(() => { window.location.href = data.redirect; }, 1500);
                } else {
                    showMessage(data.message, 'error');
                }
            } catch (error) {
                showMessage('Error al firmar el contrato. Por favor, inténtalo de nuevo.', 'error');
            }
        });

        function showMessage(text, type) {
            const div = document.getElementById('message');
            const cls = type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
            div.innerHTML = `<div class="p-4 rounded-lg ${cls}">${text}</div>`;
        }
    </script>
</body>
</html>
