<?php

namespace App\Http\Middleware;

use App\Models\LeadConversionLink;
use App\Models\Venta;
use Closure;
use Illuminate\Http\Request;

class CheckRecurrentPaymentSetup
{
    public function handle(Request $request, Closure $next)
    {
        /** @var LeadConversionLink $link */
        $link = $request->attributes->get('conversion_link');

        // Si no hay link → continuar normal
        if (!$link) {
            return $next($request);
        }

        // Solo actuar en la ruta "conversion.finished"
        if (!$request->routeIs('conversion.finished')) {
            return $next($request);
        }

        // Obtener venta
        $ventaId = $link->meta['existing_venta_id'] ?? null;
        if (!$ventaId) {
            return $next($request);
        }

        $venta = Venta::with('items.servicio', 'cliente')->find($ventaId);
        if (!$venta) {
            return $next($request);
        }

        $cliente = $venta->cliente;

        // ¿Tiene servicios recurrentes?
        $tieneRecurrente = $venta->items->contains(function ($item) {
            return $item->servicio && $item->servicio->tipo->value === 'recurrente';
        });

        if (!$tieneRecurrente || !$cliente) {
            return $next($request); // No afecta
        }

        // Preferencia elegida por el cliente en el formulario
        $meta = $link->meta['form_data'] ?? [];
        $preferencia = $meta['preferencia_pago_recurrente']
            ?? $cliente->preferencia_pago_recurrente
            ?? 'tarjeta';

        // ¿Tiene método configurado?
        $tieneMetodoStripe = !empty($cliente->stripe_customer_id);

        // TARJETA
        if ($preferencia === 'tarjeta') {
            if (!$tieneMetodoStripe) {
                return redirect()->route('stripe.setup-card', ['token' => $link->token]);
            }
        }

        // SEPA
        if ($preferencia === 'domiciliacion') {
            if (!$tieneMetodoStripe) {
                return redirect()->route('stripe.setup-sepa', ['token' => $link->token]);
            }
        }

        // Si todo OK → continuar hacia finished()
        return $next($request);
    }
}
