<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRecurrentPaymentSetup
{
    public function handle(Request $request, Closure $next)
    {
        // ⚠️ Middleware legacy: antes redirigía a setup en finished().
        // Ahora el flujo lo decide LeadConversionController@finished (sin redirects automáticos).
        return $next($request);
    }
}
