<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documento firmado — {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-lg max-w-md w-full p-8 text-center">
        <img src="{{ url(asset('images/logo.png')) }}" alt="{{ config('app.name') }}" class="h-12 mx-auto mb-6">
        <div class="text-5xl mb-4">✅</div>
        <h1 class="text-2xl font-bold text-gray-900 mb-3">Documento firmado correctamente</h1>
        <p class="text-gray-500 mb-6">
            Recibirás una copia en tu correo electrónico. Consérvala como justificante.
        </p>
        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-sm text-left">
            <div class="mb-1"><span class="text-gray-500">Fecha:</span> <strong>{{ $contrato->signed_at->format('d/m/Y H:i') }}</strong></div>
            <div class="mb-1"><span class="text-gray-500">IP de firma:</span> <strong>{{ $contrato->ip_firma }}</strong></div>
            <div class="mb-1"><span class="text-gray-500">Email:</span> <strong>{{ $contrato->email_firma }}</strong></div>
            <div style="word-break:break-all;"><span class="text-gray-500">Hash SHA256:</span> <strong style="font-size:11px;font-family:monospace;">{{ $contrato->hash_firma }}</strong></div>
        </div>
    </div>
</body>
</html>
