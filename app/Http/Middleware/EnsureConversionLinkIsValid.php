<?php

namespace App\Http\Middleware;

use App\Models\LeadConversionLink;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureConversionLinkIsValid
{
    /**
     * Maneja la petición entrante para validar el token y el estado del link.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->route('token');

        // 1. Buscar el link y su lead asociado
        $link = LeadConversionLink::where('token', $token)
            ->with('lead')
            ->first();

        if (!$link || !$link->lead) {
            return $this->linkError('invalid');
        }

        // 2. Comprobar el estado del link
        if ($link->isUsed()) {
            return $this->linkError('used', $link);
        }

        if ($link->isExpired()) {
            return $this->linkError('expired', $link);
        }

        // 3. Inyectar el link válido en la petición para que el controlador lo use
        $request->attributes->set('conversion_link', $link);

        return $next($request);
    }
    
    /**
     * Genera la respuesta de error 410 (Gone) reutilizando la vista.
     */
    private function linkError(string $reason, ?LeadConversionLink $link = null): Response
    {
        $lead = $link?->lead;
        return response()
            ->view('public.conversion.error', compact('reason', 'lead'), 410);
    }
}