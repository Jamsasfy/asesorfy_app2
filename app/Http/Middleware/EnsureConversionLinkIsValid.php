<?php

namespace App\Http\Middleware;

use App\Models\LeadConversionLink;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureConversionLinkIsValid
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->route('token');

        /** @var LeadConversionLink|null $link */
        $link = LeadConversionLink::where('token', $token)
            ->with('lead')
            ->first();

        if (! $link || ! $link->lead) {
            return $this->linkError('invalid');
        }

        // Siempre inyectamos el link (para que todos los controllers lo puedan usar)
        $request->attributes->set('conversion_link', $link);

        $routeName = $request->route()?->getName();

        // ✅ Si es la ruta de gracias, permitir siempre (como ya tenías)
        if ($routeName === 'conversion.finished') {
            return $next($request);
        }

        // --- Caducidad / revocación por tiempo o meta ---
        $meta = $link->meta ?? [];

        // Si lo revocaste explícitamente en meta, consideramos expirado
        if (! empty($meta['revoked_at'])) {
            return $this->linkError('expired', $link);
        }

        // Si expiró por tiempo
        if ($link->expires_at && $link->expires_at->isPast()) {
            return $this->linkError('expired', $link);
        }

        // --- Link usado: permitir SOLO rutas de pago / post-firma ---
        if ($link->isUsed()) {

            $allowedWhenUsed = [
                // pasos post-firma dentro del flujo
                'conversion.pago-inicial',
                'conversion.pago-inicial.store',
                'conversion.pago-recurrente',
                'conversion.pago-recurrente.store',

                // (finished ya está contemplada arriba, pero no molesta dejarlo)
                'conversion.finished',
            ];

            if (in_array($routeName, $allowedWhenUsed, true)) {
                return $next($request);
            }

            // Cualquier otra ruta (form/contrato/firma) -> bloquear
            return $this->linkError('used', $link);
        }

        // Link OK
        return $next($request);
    }

    private function linkError(string $reason, ?LeadConversionLink $link = null): Response
    {
        $lead = $link?->lead;

        return response()
            ->view('public.conversion.error', compact('reason', 'lead'), 410);
    }
}
