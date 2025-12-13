<?php

namespace App\Http\Middleware;

use App\Models\LeadConversionLink;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class EnsureConversionLinkIsValid
{
    /**
     * Maneja la petición entrante para validar el token y el estado del link.
     */
public function handle(Request $request, Closure $next): Response
{
   

    $token = $request->route('token');

    // Buscar link
    $link = LeadConversionLink::where('token', $token)
        ->with('lead')
        ->first();

    if (!$link || !$link->lead) {
          
        return $this->linkError('invalid');
    }

    // ⚠️ SI ES LA RUTA DE GRACIAS → PERMITIR Y ADEMÁS INYECTAR EL LINK
    if ($request->route()->getName() === 'conversion.finished') {
        

        // Inyectamos para que finished() reciba el link
        $request->attributes->set('conversion_link', $link);

        return $next($request);
    }

    // Link usado → BLOQUEAR en formularios, NO en pantalla gracias
    if ($link->isUsed()) {
       
        return $this->linkError('used', $link);
    }

    // Link caducado
    if ($link->isExpired()) {
       
        return $this->linkError('expired', $link);
    }

    // Link OK → continuar e inyectar
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