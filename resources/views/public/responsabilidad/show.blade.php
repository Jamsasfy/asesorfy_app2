<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documento para firmar — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
    <style>
        canvas { touch-action: none; }
        .contract-content h2 { font-size: 1rem; font-weight: bold; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px; margin-top: 1.5rem; margin-bottom: 0.5rem; color: #0f172a; }
        .contract-content p { font-size: 0.93rem; line-height: 1.7; color: #374151; text-align: justify; margin-bottom: 0.75rem; }
        .contract-content ul { padding-left: 1.2rem; margin-bottom: 0.75rem; }
        .contract-content ul li { font-size: 0.93rem; line-height: 1.7; color: #374151; margin-bottom: 2px; }
        .contract-content .destacado { background:#fff7ed; border-left:4px solid #f59e0b; padding:12px 16px; margin:12px 0; border-radius:4px; }
        .contract-content .destacado-titulo { font-weight:bold; font-size:1rem; margin-bottom:4px; color:#0f172a; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-4">
    <div class="max-w-3xl mx-auto">

        {{-- Header --}}
        <div class="bg-white rounded-xl shadow p-6 text-center mb-4">
            <img src="{{ url(asset('images/logo.png')) }}" alt="{{ config('app.name') }}" class="h-12 mx-auto mb-3">
            <h1 class="text-xl font-bold text-gray-900">Documento de Exoneración de Responsabilidad</h1>
            <p class="text-gray-500 text-sm mt-1">Lee el documento completo y firma al final para confirmarlo.</p>
        </div>

        {{-- Contenido del documento --}}
        <div class="bg-white rounded-xl shadow p-8 mb-4 contract-content">

            <h2>1. Partes</h2>
            <p>De una parte, <strong>{{ config('app.name') }}</strong>, en adelante, <strong>LA ASESORÍA</strong>.</p>
            <p>
                Y de otra parte,
                <strong>{{ trim(($contrato->cliente->nombre ?? '') . ' ' . ($contrato->cliente->apellidos ?? '')) ?: $contrato->cliente->razon_social }}</strong>,
                con DNI/NIF/CIF <strong>{{ $contrato->cliente->dni_cif ?? '—' }}</strong>,
                en adelante, <strong>EL CLIENTE</strong>.
            </p>
            <p>Ambas partes, reconociéndose capacidad legal suficiente para obligarse, suscriben el presente Documento de Instrucción Expresa, Asunción de Riesgos y Exoneración de Responsabilidad.</p>

            <h2>2. Objeto del Documento</h2>
            <p>
                El presente documento tiene por objeto dejar constancia de que <strong>LA ASESORÍA</strong> ha informado
                expresa, clara y suficientemente a <strong>EL CLIENTE</strong> de que la actuación, instrucción, solicitud
                o criterio que éste pretende seguir no resulta jurídicamente recomendable, puede no ajustarse al criterio
                técnico de <strong>LA ASESORÍA</strong> o puede implicar riesgos fiscales, contables, laborales,
                administrativos, sancionadores o de cualquier otra naturaleza jurídica.
            </p>
            <p>
                Pese a ello, <strong>EL CLIENTE</strong> manifiesta de forma libre, consciente, expresa e inequívoca
                su voluntad de mantener dicha instrucción, solicitando a <strong>LA ASESORÍA</strong> que actúe conforme
                a lo indicado por <strong>EL CLIENTE</strong>, bajo su exclusiva responsabilidad.
            </p>

            <h2>3. Actuación o Instrucción Expresa del Cliente</h2>
            <div class="destacado">
                <div class="destacado-titulo">{{ $contrato->titulo }}</div>
                <div>{{ $contrato->descripcion }}</div>
            </div>
            <p>A efectos de este documento, la actuación anteriormente descrita será denominada en adelante, <strong>la ACTUACIÓN</strong>.</p>

            <h2>4. Advertencia Previa e Información al Cliente</h2>
            <p>
                <strong>LA ASESORÍA</strong> declara, y <strong>EL CLIENTE</strong> reconoce expresamente, que antes
                de la firma del presente documento se le ha informado de forma suficiente, comprensible y directa de
                que la ACTUACIÓN puede conllevar, entre otras, alguna o varias de las siguientes consecuencias:
            </p>
            <ul>
                <li>Requerimientos de la Administración.</li>
                <li>Regularizaciones tributarias o administrativas.</li>
                <li>Denegaciones.</li>
                <li>Pérdidas de derechos, beneficios o incentivos.</li>
                <li>Recargos, intereses o sanciones.</li>
                <li>Responsabilidades frente a terceros.</li>
                <li>Nulidad, ineficacia o impugnación de actuaciones.</li>
                <li>Cualesquiera otras consecuencias jurídicas, económicas o administrativas desfavorables.</li>
            </ul>
            <p><strong>EL CLIENTE</strong> declara que ha comprendido íntegramente dichas advertencias y ha recibido una explicación suficiente sobre los riesgos asociados.</p>

            <h2>5. Instrucción Expresa del Cliente</h2>
            <p>
                <strong>EL CLIENTE</strong> manifiesta expresamente que, aun habiendo sido advertido por <strong>LA ASESORÍA</strong>
                de los riesgos, inconvenientes o posible improcedencia de la ACTUACIÓN, mantiene su decisión y ordena
                expresamente que se proceda conforme a su criterio.
            </p>

            <h2>6. Asunción Íntegra de Riesgos por el Cliente</h2>
            <p>
                <strong>EL CLIENTE</strong> acepta y asume de forma expresa, plena e irrevocable que todos los riesgos
                derivados de la ACTUACIÓN son asumidos exclusivamente por él, incluyendo los riesgos fiscales, laborales,
                contables, mercantiles, administrativos, civiles, sancionadores o de cualquier otra índole.
            </p>

            <h2>7. Exoneración Expresa de Responsabilidad</h2>
            <p>
                <strong>EL CLIENTE</strong> exonera expresa, total y completamente a <strong>LA ASESORÍA</strong>,
                así como a sus administradores, socios, empleados, asesores, colaboradores y representantes, de toda
                responsabilidad derivada directa o indirectamente de la ACTUACIÓN descrita en el presente documento.
            </p>

            <h2>8. Ausencia de Garantía y Reserva de Criterio Profesional</h2>
            <p>
                La firma del presente documento no supone en ningún caso que <strong>LA ASESORÍA</strong> comparta,
                valide o recomiende la ACTUACIÓN, ni que garantice un resultado favorable, ni que asuma responsabilidad
                alguna por la viabilidad, legalidad material o consecuencias futuras de la ACTUACIÓN.
            </p>

            <h2>9. Indemnidad</h2>
            <p>
                <strong>EL CLIENTE</strong> se obliga a mantener plenamente indemne a <strong>LA ASESORÍA</strong>
                frente a cualquier daño, perjuicio, coste, reclamación, gasto, sanción, recargo, defensa jurídica o
                responsabilidad que pudiera derivarse de la ACTUACIÓN.
            </p>

            <h2>10. Prevalencia Documental</h2>
            <p>
                El presente documento complementa el contrato principal o relación profesional existente entre las partes
                y, respecto de la ACTUACIÓN aquí descrita, prevalecerá sobre cualquier manifestación verbal previa o simultánea.
            </p>

            <h2>11. Validez y Firma</h2>
            <p>
                <strong>EL CLIENTE</strong> declara que firma el presente documento de forma libre, consciente e informada,
                sin error, violencia, intimidación o dolo.
            </p>

        </div>

        {{-- Formulario de firma --}}
        <div class="bg-white rounded-xl shadow p-8 mb-8" id="firmaForm-container">
            <h2 class="text-lg font-bold text-gray-900 mb-2">Firma del documento</h2>
            <p class="text-sm text-gray-500 mb-6">
                Al firmar, confirmas que has leído y aceptas el contenido del documento anterior.
            </p>

            <form method="POST" action="{{ route('responsabilidad.firmar', $contrato->token) }}" id="firmaForm">
                @csrf

                <div class="mb-6">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Firma aquí:</label>
                    <div class="border-2 border-gray-300 rounded-lg overflow-hidden bg-white">
                        <canvas id="signatureCanvas" width="600" height="200" class="w-full"></canvas>
                    </div>
                    <button type="button" id="clearBtn" class="mt-2 text-xs text-gray-400 hover:text-red-500 underline">
                        Borrar firma
                    </button>
                    <input type="hidden" name="signature" id="signatureInput">
                    @error('signature')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" id="submitBtn"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition">
                    ✅ Firmar y confirmar documento
                </button>
            </form>
        </div>

    </div>

    <script>
        const canvas = document.getElementById('signatureCanvas');
        const sigPad = new SignaturePad(canvas, { backgroundColor: 'rgba(255,255,255,1)' });

        document.getElementById('clearBtn').addEventListener('click', () => sigPad.clear());

        document.getElementById('firmaForm').addEventListener('submit', function(e) {
            if (sigPad.isEmpty()) {
                e.preventDefault();
                alert('Por favor, firma el documento antes de continuar.');
                return;
            }
            document.getElementById('signatureInput').value = sigPad.toDataURL();
        });

        function resizeCanvas() {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = canvas.offsetHeight * ratio;
            canvas.getContext('2d').scale(ratio, ratio);
            sigPad.clear();
        }
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();
    </script>
</body>
</html>