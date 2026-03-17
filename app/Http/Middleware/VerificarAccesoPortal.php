<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerificarAccesoPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        \Log::info('🔍 VerificarAccesoPortal ejecutándose', [
            'url' => $request->url(),
            'user_id' => Auth::id(),
        ]);

        $user = Auth::user();

        if (!$user) {
            \Log::info('❌ No hay usuario autenticado');
            return redirect()->route('login');
        }

        \Log::info('👤 Usuario detectado', [
            'id' => $user->id,
            'email' => $user->email,
            'acceso_app' => $user->acceso_app,
            'portal_activo' => $user->portal_activo,
        ]);

        // Es trabajador, no cliente
        if ($user->acceso_app) {
            \Log::info('⚠️ Es trabajador, redirigiendo a /admin');
            return redirect('/admin');
        }

        // Acceso bloqueado
        if (!$user->portal_activo) {
            \Log::info('🔒 PORTAL BLOQUEADO - Redirigiendo a portal.bloqueado');
            return redirect()->route('portal.bloqueado');
        }

        \Log::info('✅ Acceso permitido');
        return $next($request);
    }
}
