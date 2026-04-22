<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contrato Firmado - AsesorFy</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-12 px-4">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg p-8 text-center">
                <div class="text-6xl mb-4">✅</div>
                <h1 class="text-3xl font-bold text-green-600 mb-4">Contrato Firmado</h1>
                <p class="text-gray-600 mb-6">
                    Este contrato fue firmado el <strong>{{ $contrato->fecha_firma->format('d/m/Y') }}</strong>
                    a las <strong>{{ $contrato->fecha_firma->format('H:i') }}</strong>.
                </p>

                @if($contrato->pdf_path && \Storage::disk('local')->exists($contrato->pdf_path))
                <a href="{{ route('descargar-contrato-firmado', ['id' => $contrato->id]) }}"
                   target="_blank"
                   class="inline-block px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    📄 Descargar Contrato Firmado
                </a>
                @endif

                <p class="mt-8 text-sm text-gray-500">
                    Si tienes dudas, contacta con tu coordinador.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
