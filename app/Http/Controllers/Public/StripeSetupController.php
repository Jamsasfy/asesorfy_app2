<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\LeadConversionLink;
use App\Models\Venta;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;
use Stripe\Stripe;
use Throwable;

class StripeSetupController extends Controller
{
    /**
     * Helper privado para iniciar Stripe con configuración segura/local
     */
    private function initStripe(): void
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        if (app()->isLocal()) {
            Stripe::setVerifySslCerts(false);
        }
    }

    /**
     * Resolver link + cliente de forma robusta (cliente_id | existing_cliente_id | existing_venta_id)
     */
    private function resolveLinkAndCliente(string $token): array
    {
        $link = LeadConversionLink::where('token', $token)->firstOrFail();

        $clienteId =
            data_get($link->meta, 'cliente_id')
            ?? data_get($link->meta, 'existing_cliente_id');

        $cliente = null;

        if ($clienteId) {
            $cliente = Cliente::find($clienteId);
        }

        // Fallback por venta
        if (! $cliente) {
            $ventaId = data_get($link->meta, 'existing_venta_id');
            if ($ventaId) {
                $venta = Venta::with('cliente')->find($ventaId);
                $cliente = $venta?->cliente;
            }
        }

        if (! $cliente) {
            abort(500, "No se pudo resolver el cliente para este enlace de conversión.");
        }

        return [$link, $cliente];
    }

    /**
     * Asegura que el cliente tiene stripe_customer_id (por seguridad)
     */
    private function ensureStripeCustomer(Cliente $cliente): Cliente
    {
        $this->initStripe();

        if ($cliente->stripe_customer_id) {
            return $cliente;
        }

        $stripeCustomer = Customer::create([
            'email' => $cliente->email_contacto,
            'name'  => $cliente->razon_social,
            'metadata' => [
                'cliente_id' => $cliente->id,
                'dni_cif'    => $cliente->dni_cif,
            ],
        ]);

        $cliente->stripe_customer_id = $stripeCustomer->id;
        $cliente->saveQuietly();

        return $cliente;
    }

    /**
     * Mostrar pantalla de setup de tarjeta
     */
    public function setupCard(string $token)
    {
        [$link, $cliente] = $this->resolveLinkAndCliente($token);
        $cliente = $this->ensureStripeCustomer($cliente);

        $this->initStripe();

        try {
            $intent = SetupIntent::create([
                'customer' => $cliente->stripe_customer_id,
                'payment_method_types' => ['card'],
            ]);

            return view('public.conversion.setup-card', [
                'clientSecret' => $intent->client_secret,
                'token'        => $token,
                'cliente'      => $cliente,
            ]);
        } catch (Throwable $e) {
            Log::error("Error Stripe setupCard: " . $e->getMessage());
            abort(500, "Error al conectar con la pasarela de pago.");
        }
    }

    /**
     * Procesar tarjeta guardada
     */
    public function processCard(Request $request, string $token)
    {
        [$link, $cliente] = $this->resolveLinkAndCliente($token);
        $cliente = $this->ensureStripeCustomer($cliente);

        $paymentMethodId = $request->input('payment_method');
        if (! $paymentMethodId) {
            return back()->with('error', 'No se recibió un método de pago válido.');
        }

        $this->initStripe();

        try {
            // 1) Recuperar y adjuntar método de pago al Customer (si no lo está ya)
            $pm = PaymentMethod::retrieve($paymentMethodId);

            try {
                $pm->attach(['customer' => $cliente->stripe_customer_id]);
            } catch (Throwable $e) {
                // Si ya estaba attached, Stripe puede devolver error; lo ignoramos.
            }

            // 2) Forzar método predeterminado
            Customer::update($cliente->stripe_customer_id, [
                'invoice_settings' => [
                    'default_payment_method' => $pm->id,
                ],
                'name'  => trim((string) $cliente->razon_social),
                'email' => (string) $cliente->email_contacto,
                'metadata' => [
                    'cliente_id' => $cliente->id,
                    'dni_cif'    => $cliente->dni_cif,
                ],
            ]);

            // 3) Limpiar métodos antiguos (solo tarjetas)
            $methods = PaymentMethod::all([
                'customer' => $cliente->stripe_customer_id,
                'type'     => 'card',
            ]);

            foreach ($methods->data as $method) {
                if ($method->id !== $pm->id) {
                    try {
                        $method->detach();
                    } catch (Throwable $e) {
                        // Ignorar
                    }
                }
            }

            // 4) Reintentar cobro de invoices abiertas (solo tarjeta)
            try {
                $openInvoices = Invoice::all([
                    'customer' => $cliente->stripe_customer_id,
                    'status'   => 'open',
                    'limit'    => 10,
                ]);

                foreach ($openInvoices->data as $inv) {
                    try {
                        $inv->pay(['payment_method' => $pm->id]);
                        Log::info("✅ Invoice open {$inv->id} pagada tras guardar tarjeta.");
                    } catch (Throwable $e) {
                        Log::warning("No se pudo pagar invoice {$inv->id}: " . $e->getMessage());
                    }
                }
            } catch (Throwable $e) {
                Log::warning("No se pudieron listar invoices open: " . $e->getMessage());
            }

            // 5) Preferencia local
            $cliente->preferencia_pago_recurrente = 'tarjeta';
            $cliente->saveQuietly();

            // ✅ Guardar en meta del link (flujo público)
            $meta = $link->meta ?? [];
            $meta['recurrente_metodo'] = 'tarjeta';
            $link->meta = $meta;
            $link->save();

            return redirect()
                ->route('conversion.finished', ['token' => $token])
                ->with('payment_setup_success', true)
                ->with('success', 'Tarjeta guardada correctamente.');
        } catch (Throwable $e) {
            Log::error("Error Stripe processCard: " . $e->getMessage());
            return back()->with('error', 'Error al guardar la tarjeta: ' . $e->getMessage());
        }
    }

    /**
     * Mostrar pantalla SetupIntent SEPA
     */
    public function setupSepa(string $token)
    {
        [$link, $cliente] = $this->resolveLinkAndCliente($token);
        $cliente = $this->ensureStripeCustomer($cliente);

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
        } catch (Throwable $e) {
            Log::error("Error Stripe setupSepa: " . $e->getMessage());
            abort(500, "Error al iniciar configuración SEPA.");
        }
    }

    /**
     * Procesar SEPA Débito
     */
    public function processSepa(Request $request, string $token)
    {
        [$link, $cliente] = $this->resolveLinkAndCliente($token);
        $cliente = $this->ensureStripeCustomer($cliente);

        $paymentMethodId = $request->input('payment_method');
        if (! $paymentMethodId) {
            return back()->with('error', 'No se recibió el IBAN / método SEPA.');
        }

        $this->initStripe();

        try {
            // 1) Adjuntar
            $pm = PaymentMethod::retrieve($paymentMethodId);

            try {
                $pm->attach(['customer' => $cliente->stripe_customer_id]);
            } catch (Throwable $e) {
                // Si ya estaba attached, ignoramos.
            }

            // 2) Default
            Customer::update($cliente->stripe_customer_id, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
                'name'  => trim((string) $cliente->razon_social),
                'email' => (string) $cliente->email_contacto,
                'metadata' => [
                    'cliente_id' => $cliente->id,
                    'dni_cif'    => $cliente->dni_cif,
                ],
            ]);

            // 3) Preferencia local
            $cliente->preferencia_pago_recurrente = 'domiciliacion';
            $cliente->saveQuietly();

            // ✅ Guardar en meta del link (flujo público)
            $meta = $link->meta ?? [];
            $meta['recurrente_metodo'] = 'domiciliacion';
            $link->meta = $meta;
            $link->save();

            return redirect()
                ->route('conversion.finished', ['token' => $token])
                ->with('payment_setup_success', true)
                ->with('success', 'Domiciliación configurada correctamente.');
        } catch (Throwable $e) {
            Log::error("Error Stripe processSepa: " . $e->getMessage());
            return back()->with('error', 'Error al guardar SEPA: ' . $e->getMessage());
        }
    }
}
