<?php

namespace App\Http\Controllers\Portal;

use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\BillingPortal\Session as BillingPortalSession;
use Stripe\Stripe;

class StripeBillingPortalController
{
    public function __invoke(Request $request): RedirectResponse
    {
        // Stripe init (igual que tus controllers antiguos)
        Stripe::setApiKey(config('services.stripe.secret'));

        if (app()->isLocal()) {
            Stripe::setVerifySslCerts(false);
        }

        // ✅ Portal: por ahora cogemos el primer cliente del usuario
        // (Cuando exista selector de empresa, aquí usaremos el cliente "seleccionado")
        $user = $request->user();

        /** @var Cliente|null $cliente */
        $cliente = $user?->clientes()->first();

        if (! $cliente || blank($cliente->stripe_customer_id)) {
            return redirect()->back();
        }

        // A dónde vuelve Stripe cuando terminas
        $returnUrl = url('/portal/metodo-de-pago');

        try {
            // ✅ IMPORTANTE:
            // Forzamos el "flow" directo a actualizar método de pago,
            // para evitar que el cliente vea la home del portal con la suscripción/trial.
            $session = BillingPortalSession::create([
                'customer'   => $cliente->stripe_customer_id,
                'return_url' => $returnUrl,

                'flow_data' => [
                    'type' => 'payment_method_update',
                ],
            ]);

            return redirect()->away($session->url);
        } catch (\Throwable $e) {
            Log::error('StripeBillingPortalController error', [
                'cliente_id' => $cliente->id,
                'stripe_customer_id' => $cliente->stripe_customer_id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back();
        }
    }
}
