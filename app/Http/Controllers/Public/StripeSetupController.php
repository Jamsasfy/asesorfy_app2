<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\LeadConversionLink;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\SetupIntent;
use Stripe\PaymentMethod;
use Stripe\Customer;

class StripeSetupController extends Controller
{
    /**
     * Helper privado para iniciar Stripe con configuración segura/local
     */
    private function initStripe()
    {
        if (app()->isLocal()) {
            Stripe::setVerifySslCerts(false);
        }
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * Mostrar pantalla de setup de tarjeta
     */
    public function setupCard($token)
    {
        $link = LeadConversionLink::where('token', $token)->firstOrFail();
        
        $clienteId = $link->meta['cliente_id'] ?? null;
        if (!$clienteId) {
            abort(500, "Falta cliente_id en meta del link");
        }

        $cliente = Cliente::findOrFail($clienteId);

        $this->initStripe();

        try {
            // Crear SetupIntent
            $intent = SetupIntent::create([
                'customer' => $cliente->stripe_customer_id,
                'payment_method_types' => ['card'],
            ]);

            return view('public.conversion.setup-card', [
                'clientSecret' => $intent->client_secret,
                'token'        => $token,
                'cliente'      => $cliente,
            ]);

        } catch (\Throwable $e) {
            Log::error("Error Stripe SetupCard: " . $e->getMessage());
            abort(500, "Error al conectar con la pasarela de pago.");
        }
    }

  /**
     * Procesar tarjeta guardada
     */
    public function processCard(Request $request, $token)
    {
        $link = LeadConversionLink::where('token', $token)->firstOrFail();

        $clienteId = $link->meta['cliente_id'] ?? null;
        if (!$clienteId) {
            abort(500, "Falta cliente_id en meta del link");
        }

        $cliente = Cliente::findOrFail($clienteId);

        $paymentMethodId = $request->payment_method;
        if (!$paymentMethodId) {
            return back()->with('error', 'No se recibió un método de pago válido.');
        }

        $this->initStripe();

        try {
            // 1. Recuperar y adjuntar método de pago al Customer
            $pm = \Stripe\PaymentMethod::retrieve($paymentMethodId);
            $pm->attach([
                'customer' => $cliente->stripe_customer_id,
            ]);

            // 2. 🔥 FORZAR método predeterminado en Invoice Settings
            // Esto asegura que los futuros cobros automáticos usen esta tarjeta
            \Stripe\Customer::update($cliente->stripe_customer_id, [
                'invoice_settings' => [
                    'default_payment_method' => $pm->id,
                ]
            ]);

            // 3. Limpiar payment methods anteriores (Mantenimiento)
            $methods = \Stripe\PaymentMethod::all([
                'customer' => $cliente->stripe_customer_id,
                'type' => 'card',
            ]);

            foreach ($methods->data as $method) {
                if ($method->id !== $pm->id) {
                    try {
                        $method->detach();
                    } catch (\Throwable $e) {
                        // Ignorar error al desvincular antiguos
                    }
                }
            }

            // =========================================================
            // 4. 🚀 NUEVO: REACTIVAR COBROS PENDIENTES (FIX INCOMPLETOS)
            // =========================================================
            // Buscamos las facturas que se quedaron 'open' al crear la suscripción
            // y las forzamos a pagarse con la tarjeta que acabamos de guardar.
            try {
                $openInvoices = \Stripe\Invoice::all([
                    'customer' => $cliente->stripe_customer_id,
                    'status'   => 'open',
                ]);

                foreach ($openInvoices->data as $invoice) {
                    $invoice->pay([
                        'payment_method' => $pm->id,
                    ]);
                    \Illuminate\Support\Facades\Log::info("✅ Factura pendiente {$invoice->id} cobrada tras añadir tarjeta.");
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Error reintentando cobro facturas pendientes: " . $e->getMessage());
                // No detenemos el proceso, lo importante es que la tarjeta ya está guardada.
            }
            // =========================================================

            // 5. Guardar preferencia en BBDD local
            $cliente->preferencia_pago_recurrente = 'tarjeta';
            $cliente->save();

            // ✅ REDIRECCIÓN CON FLAG DE ÉXITO
            return redirect()
                ->route('conversion.finished', ['token' => $token])
                ->with('payment_setup_success', true);

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Error Stripe ProcessCard: " . $e->getMessage());
            return back()->with('error', 'Error al guardar la tarjeta: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar pantalla SetupIntent SEPA
     */
    public function setupSepa($token)
    {
        $link = LeadConversionLink::where('token', $token)->firstOrFail();

        $clienteId = $link->meta['cliente_id'] ?? null;
        if (!$clienteId) {
            abort(500, "Falta cliente_id en meta del link");
        }

        $cliente = Cliente::findOrFail($clienteId);

        $this->initStripe();

        try {
            $intent = SetupIntent::create([
                'customer' => $cliente->stripe_customer_id,
                'payment_method_types' => ['sepa_debit'],
            ]);

            return view('public.conversion.setup-sepa', [
                'clientSecret' => $intent->client_secret,
                'token'        => $token,
                'cliente'      => $cliente,
            ]);
        } catch (\Throwable $e) {
            Log::error("Error Stripe SetupSepa: " . $e->getMessage());
            abort(500, "Error al iniciar configuración SEPA.");
        }
    }

    /**
     * Procesar SEPA Débito
     */
    public function processSepa(Request $request, $token)
    {
        $link = LeadConversionLink::where('token', $token)->firstOrFail();

        $clienteId = $link->meta['cliente_id'] ?? null;
        if (!$clienteId) {
            abort(500, "Falta cliente_id en meta del link");
        }

        $cliente = Cliente::findOrFail($clienteId);

        $paymentMethodId = $request->payment_method;
        if (!$paymentMethodId) {
            return back()->with('error', 'No se recibió el IBAN / método SEPA.');
        }

        $this->initStripe();

        try {
            // 1. Adjuntar
            $pm = PaymentMethod::retrieve($paymentMethodId);
            $pm->attach([
                'customer' => $cliente->stripe_customer_id,
            ]);

            // 2. Establecer Default
            Customer::update($cliente->stripe_customer_id, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ]
            ]);

            // 3. Guardar preferencia local
            $cliente->preferencia_pago_recurrente = 'domiciliacion';
            $cliente->save();

            // ✅ REDIRECCIÓN CON FLAG DE ÉXITO
            return redirect()
                ->route('conversion.finished', ['token' => $token])
                ->with('payment_setup_success', true);

        } catch (\Throwable $e) {
            Log::error("Error Stripe ProcessSepa: " . $e->getMessage());
            return back()->with('error', 'Error al guardar SEPA: ' . $e->getMessage());
        }
    }
}