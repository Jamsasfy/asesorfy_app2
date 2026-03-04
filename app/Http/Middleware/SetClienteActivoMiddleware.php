<?php
// app/Http/Middleware/SetClienteActivoMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetClienteActivoMiddleware
{
    // Rutas del portal que NO deben ser interceptadas
    private const RUTAS_EXCLUIDAS = [
        'portal/seleccionar-empresa',
        'portal/login',
        'portal/logout',
        'portal/password-reset',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Si no está autenticado, dejamos pasar (el middleware Authenticate lo gestiona)
        if (!Auth::check()) {
            return $next($request);
        }

        // Si es una ruta excluida, dejamos pasar
        foreach (self::RUTAS_EXCLUIDAS as $ruta) {
            if ($request->is($ruta) || $request->is($ruta . '/*')) {
                return $next($request);
            }
        }

        $user = Auth::user();
        $clientes = $user->clientes()->get();

        // Sin clientes vinculados → no debería poder estar aquí, pero dejamos pasar
        // (esto se puede gestionar más adelante con una página de error)
        if ($clientes->isEmpty()) {
            return $next($request);
        }

        // Un solo cliente → lo ponemos en sesión automáticamente sin interrumpir
        if ($clientes->count() === 1) {
            session(['cliente_activo_id' => $clientes->first()->id]);
            return $next($request);
        }

        // Varios clientes → comprobamos si ya hay uno activo válido en sesión
        $clienteActivoId = session('cliente_activo_id');

        if ($clienteActivoId) {
            // Verificamos que el cliente en sesión realmente pertenece a este usuario
            $esValido = $clientes->contains('id', $clienteActivoId);

            if ($esValido) {
                return $next($request); // Todo correcto, dejamos pasar
            }

            // El ID en sesión no es válido para este usuario → lo limpiamos
            session()->forget('cliente_activo_id');
        }

        // No hay cliente activo válido → redirigir al selector
        return redirect('/portal/seleccionar-empresa');
    }
}