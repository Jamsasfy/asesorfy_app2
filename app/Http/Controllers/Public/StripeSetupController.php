<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\LeadConversionLink;
use App\Models\Venta;
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
    private function initStripe(): void
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        if (app()->isLocal()) {
            Stripe::setVerifySslCerts(false);
        }
    }

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

    private function ensureStripeCustomer(Cliente $cliente): Cliente
    {
        $this->initStripe();

        if (! empty($cliente->stripe_customer_id)) {
            return $cliente;
        }

        $stripeCustomer = Customer::create([
            'email' => (string) $cliente->email_contacto,
            'name'  => (string) $cliente->razon_social,
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
     * ✅ Setup TARJETA (solo si ya eligió 'tarjeta' en pago-recurrente)
     */
    public function setupCard(string $token)
    {
        [$link, $cliente] = $this->resolveLinkAndCliente($token);

        // 🔒 Blindaje: si no hay elección REAL, fuera (evita bucles y “seteo preventivo”)
        if (data_get($link->meta, 'recurrente_metodo') !== 'tarjeta') {
            Log::info('setupCard bloqueado: recurrente_metodo no es tarjeta', [
                'token' => $token,
                'recurrente_metodo' => data_get($link->meta, 'recurrente_metodo'),
            ]);

            return redirect()
                ->route('conversion.pago-recurrente', ['token' => $token])
                ->with('error', 'Primero elige el método de pago de la cuota mensual.');
        }

        $cliente = $this->ensureStripeCustomer($cliente);
        $this->initStripe();

        try {
            $intent = SetupIntent::create([
                'customer' => $cliente->stripe_customer_id,
                'usage' => 'off_session',
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
public function processCard(Request $request, string $token)
{
    [$link, $cliente] = $this->resolveLinkAndCliente($token);

    if (data_get($link->meta, 'recurrente_metodo') !== 'tarjeta') {
        return redirect()
            ->route('conversion.pago-recurrente', ['token' => $token])
            ->with('error', 'Método recurrente no válido para tarjeta.');
    }

    $cliente = $this->ensureStripeCustomer($cliente);

    $paymentMethodId = $request->input('payment_method');
    if (! $paymentMethodId) {
        return back()->with('error', 'No se recibió un método de pago válido.');
    }

    $this->initStripe();

    try {
        $pm = PaymentMethod::retrieve($paymentMethodId);

        try { $pm->attach(['customer' => $cliente->stripe_customer_id]); } catch (Throwable $e) {}

        Customer::update($cliente->stripe_customer_id, [
            'invoice_settings' => ['default_payment_method' => $pm->id],
            'name'  => trim((string) $cliente->razon_social),
            'email' => (string) $cliente->email_contacto,
            'metadata' => [
                'cliente_id' => $cliente->id,
                'dni_cif'    => $cliente->dni_cif,
            ],
        ]);

        // Limpieza de métodos antiguos (solo tarjetas)
        $methods = PaymentMethod::all([
            'customer' => $cliente->stripe_customer_id,
            'type'     => 'card',
        ]);

        foreach ($methods->data as $method) {
            if ($method->id !== $pm->id) {
                try { $method->detach(); } catch (Throwable $e) {}
            }
        }

        // Reintentar invoices abiertas (solo tarjeta)
        try {
            $openInvoices = Invoice::all([
                'customer' => $cliente->stripe_customer_id,
                'status'   => 'open',
                'limit'    => 10,
            ]);

            foreach ($openInvoices->data as $inv) {
                try { $inv->pay(['payment_method' => $pm->id]); } catch (Throwable $e) {}
            }
        } catch (Throwable $e) {}

        // Preferencia local (UX)
        $cliente->preferencia_pago_recurrente = 'tarjeta';
        $cliente->saveQuietly();

        // Meta del link (fuente de verdad del flujo)
        $meta = $link->meta ?? [];
        $meta['recurrente_metodo'] = 'tarjeta';
        $meta['recurrente_metodo_confirmado_at'] = now()->toDateTimeString();
        $meta['recurrente_payment_method_id'] = $pm->id;
        $link->meta = $meta;
        $link->save();

        // =========================
        // ✅ Activación condicionada
        // - Solo recurrente: completar venta y activar
        // - Si hay pago único y venta no completada: NO activar (transferencia pendiente)
        // - Si hay bloqueo por proyecto: NO activar aunque la venta esté completada
        // =========================
        $ventaId = data_get($link->meta, 'existing_venta_id');

        if ($ventaId) {
            $venta = \App\Models\Venta::with('items.servicio', 'suscripciones')
                ->find($ventaId);

            if ($venta) {
                // Total únicos
                $totalUnico = 0.0;
                foreach ($venta->items as $item) {
                    if (! $item->servicio) continue;
                    $tipo = $item->servicio->tipo?->value ?? ($item->servicio->tipo ?? null);
                    if ($tipo === 'unico') {
                        $totalUnico += (float) ($item->subtotal_aplicado ?? 0);
                    }
                }

                // 🔒 BLOQUEO GLOBAL por proyecto (mismo criterio que en procesarCobroInicial)
                $esperaProyecto = $venta->items->contains(function ($i) {
                    if (! $i->servicio) return false;

                    $tipo = $i->servicio->tipo instanceof \BackedEnum
                        ? $i->servicio->tipo->value
                        : $i->servicio->tipo;

                    if ($tipo !== 'unico') return false;

                    $itemBloquea = (bool) ($i->bloquea_recurrente ?? false);
                    $svcBloquea  = (bool) ($i->servicio->bloquea_recurrente ?? false);

                    if ($itemBloquea || $svcBloquea) return true;

                    // Legacy fallback
                    if (!isset($i->bloquea_recurrente) && !isset($i->servicio->bloquea_recurrente)) {
                        $esEditable = (bool) ($i->servicio->es_editable ?? false);
                        return $esEditable
                            ? (bool) ($i->requiere_proyecto ?? false)
                            : (bool) ($i->servicio->requiere_proyecto_activacion ?? false);
                    }

                    return false;
                });

                // ✅ Solo recurrente => completar venta y activar
                if ($totalUnico <= 0 && $venta->estado !== \App\Enums\VentaEstadoEnum::COMPLETADA) {
                    $venta->procesarCobroInicial(
                        fechaPago: now(),
                        metodoPago: 'suscripcion_directa',
                        paymentIntentId: null,
                        extraData: array_merge((array) data_get($link->meta, 'form_data', []), [
                            'recurrente_metodo' => 'tarjeta',
                        ])
                    );
                    
                    // ✅ ClienteActivado — creación usuario portal + email
                    try {
                        $ventaFresh = $venta->fresh(['cliente']);
                        if ($ventaFresh->cliente) {
                            // Activar cliente y crear usuario portal
                            $activacionService = app(\App\Services\ClienteActivacionService::class);
                            $resultado = $activacionService->activarCliente(
                                $ventaFresh->cliente, 
                                'stripe_setup_completado'
                            );
                            
                            if (!$resultado['success']) {
                                \Illuminate\Support\Facades\Log::warning('Cliente no activado (ya tenía usuario)', [
                                    'cliente_id' => $ventaFresh->cliente->id,
                                ]);
                            }
                        }
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Error en activación de cliente: ' . $e->getMessage());
                    }

                    $venta->refresh();
                    $venta->loadMissing('items.servicio', 'suscripciones');
                }

                // ✅ Si hay únicos y NO está completada => NO activar (transferencia pendiente)
                if ($totalUnico > 0 && $venta->estado !== \App\Enums\VentaEstadoEnum::COMPLETADA) {
                    return redirect()
                        ->route('conversion.finished', ['token' => $token])
                        ->with('recurrente_setup_success', true)
                        ->with('success', 'Tarjeta guardada. La suscripción se activará cuando se confirme el pago inicial.');
                }

                // ✅ Si hay bloqueo por proyecto => NO activar aquí
                if ($esperaProyecto) {
                    return redirect()
                        ->route('conversion.finished', ['token' => $token])
                        ->with('recurrente_setup_success', true)
                        ->with('success', 'Tarjeta guardada. La suscripción se activará cuando finalice el proyecto.');
                }

                // ✅ Venta completada + sin bloqueo => activar pendientes
                foreach ($venta->suscripciones as $suscripcion) {
                    if (empty($suscripcion->stripe_subscription_id)
                        && $suscripcion->estado === \App\Enums\ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION
                    ) {
                        \App\Services\StripeSubscriptionService::activarSuscripcion($suscripcion);
                    }
                }
            }
        }

        return redirect()
            ->route('conversion.finished', ['token' => $token])
            ->with('recurrente_setup_success', true)
            ->with('success', 'Tarjeta guardada correctamente.');
    } catch (Throwable $e) {
        Log::error("Error Stripe processCard: " . $e->getMessage());
        return back()->with('error', 'Error al guardar la tarjeta: ' . $e->getMessage());
    }
}


    /**
     * ✅ Setup SEPA (solo si ya eligió 'domiciliacion' en pago-recurrente)
     */
    public function setupSepa(string $token)
    {
        [$link, $cliente] = $this->resolveLinkAndCliente($token);

        if (data_get($link->meta, 'recurrente_metodo') !== 'domiciliacion') {
            Log::info('setupSepa bloqueado: recurrente_metodo no es domiciliacion', [
                'token' => $token,
                'recurrente_metodo' => data_get($link->meta, 'recurrente_metodo'),
            ]);

            return redirect()
                ->route('conversion.pago-recurrente', ['token' => $token])
                ->with('error', 'Primero elige el método de pago de la cuota mensual.');
        }

        $cliente = $this->ensureStripeCustomer($cliente);
        $this->initStripe();

        try {
            $intent = SetupIntent::create([
                'customer' => $cliente->stripe_customer_id,
                'usage' => 'off_session',
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

public function processSepa(Request $request, string $token)
{
    [$link, $cliente] = $this->resolveLinkAndCliente($token);

    if (data_get($link->meta, 'recurrente_metodo') !== 'domiciliacion') {
        return redirect()
            ->route('conversion.pago-recurrente', ['token' => $token])
            ->with('error', 'Método recurrente no válido para domiciliación.');
    }

    $cliente = $this->ensureStripeCustomer($cliente);

    $paymentMethodId = $request->input('payment_method');
    if (! $paymentMethodId) {
        return back()->with('error', 'No se recibió el IBAN / método SEPA.');
    }

    $this->initStripe();

    try {
        $pm = PaymentMethod::retrieve($paymentMethodId);

        try { $pm->attach(['customer' => $cliente->stripe_customer_id]); } catch (Throwable $e) {}

        Customer::update($cliente->stripe_customer_id, [
            'invoice_settings' => ['default_payment_method' => $paymentMethodId],
            'name'  => trim((string) $cliente->razon_social),
            'email' => (string) $cliente->email_contacto,
            'metadata' => [
                'cliente_id' => $cliente->id,
                'dni_cif'    => $cliente->dni_cif,
            ],
        ]);

        $cliente->preferencia_pago_recurrente = 'domiciliacion';
        $cliente->saveQuietly();

        $meta = $link->meta ?? [];
        $meta['recurrente_metodo'] = 'domiciliacion';
        $meta['recurrente_metodo_confirmado_at'] = now()->toDateTimeString();
        $meta['recurrente_payment_method_id'] = $paymentMethodId;
        $link->meta = $meta;
        $link->save();

        // =========================
        // ✅ Activación condicionada (con bloqueo por proyecto)
        // =========================
        $ventaId = data_get($link->meta, 'existing_venta_id');

        if ($ventaId) {
            $venta = \App\Models\Venta::with('items.servicio', 'suscripciones')
                ->find($ventaId);

            if ($venta) {
                // Total únicos
                $totalUnico = 0.0;
                foreach ($venta->items as $item) {
                    if (! $item->servicio) continue;
                    $tipo = $item->servicio->tipo?->value ?? ($item->servicio->tipo ?? null);
                    if ($tipo === 'unico') {
                        $totalUnico += (float) ($item->subtotal_aplicado ?? 0);
                    }
                }

                // 🔒 BLOQUEO GLOBAL por proyecto
                $esperaProyecto = $venta->items->contains(function ($i) {
                    if (! $i->servicio) return false;

                    $tipo = $i->servicio->tipo instanceof \BackedEnum
                        ? $i->servicio->tipo->value
                        : $i->servicio->tipo;

                    if ($tipo !== 'unico') return false;

                    $itemBloquea = (bool) ($i->bloquea_recurrente ?? false);
                    $svcBloquea  = (bool) ($i->servicio->bloquea_recurrente ?? false);

                    if ($itemBloquea || $svcBloquea) return true;

                    // Legacy fallback
                    if (!isset($i->bloquea_recurrente) && !isset($i->servicio->bloquea_recurrente)) {
                        $esEditable = (bool) ($i->servicio->es_editable ?? false);
                        return $esEditable
                            ? (bool) ($i->requiere_proyecto ?? false)
                            : (bool) ($i->servicio->requiere_proyecto_activacion ?? false);
                    }

                    return false;
                });

                // ✅ Solo recurrente => completar venta y activar
                if ($totalUnico <= 0 && $venta->estado !== \App\Enums\VentaEstadoEnum::COMPLETADA) {
                    $venta->procesarCobroInicial(
                        fechaPago: now(),
                        metodoPago: 'suscripcion_directa',
                        paymentIntentId: null,
                        extraData: array_merge((array) data_get($link->meta, 'form_data', []), [
                            'recurrente_metodo' => 'domiciliacion',
                        ])
                    );

                    // ✅ ClienteActivado — creación usuario portal + email
                    try {
                        $ventaFresh = $venta->fresh(['cliente']);
                        if ($ventaFresh->cliente) {
                            // Activar cliente y crear usuario portal
                            $activacionService = app(\App\Services\ClienteActivacionService::class);
                            $resultado = $activacionService->activarCliente(
                                $ventaFresh->cliente, 
                                'stripe_setup_completado'
                            );
                            
                            if (!$resultado['success']) {
                                \Illuminate\Support\Facades\Log::warning('Cliente no activado (ya tenía usuario)', [
                                    'cliente_id' => $ventaFresh->cliente->id,
                                ]);
                            }
                        }
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Error en activación de cliente: ' . $e->getMessage());
                    }

                    $venta->refresh();
                    $venta->loadMissing('items.servicio', 'suscripciones');
                }

                // ✅ Si hay únicos y NO está completada => NO activar (transferencia pendiente)
                if ($totalUnico > 0 && $venta->estado !== \App\Enums\VentaEstadoEnum::COMPLETADA) {
                    return redirect()
                        ->route('conversion.finished', ['token' => $token])
                        ->with('recurrente_setup_success', true)
                        ->with('success', 'Domiciliación guardada. La suscripción se activará cuando se confirme el pago inicial.');
                }

                // ✅ Si hay bloqueo por proyecto => NO activar aquí
                if ($esperaProyecto) {
                    return redirect()
                        ->route('conversion.finished', ['token' => $token])
                        ->with('recurrente_setup_success', true)
                        ->with('success', 'Domiciliación guardada. La suscripción se activará cuando finalice el proyecto.');
                }

                // ✅ Venta completada + sin bloqueo => activar pendientes
                foreach ($venta->suscripciones as $suscripcion) {
                    if (empty($suscripcion->stripe_subscription_id)
                        && $suscripcion->estado === \App\Enums\ClienteSuscripcionEstadoEnum::PENDIENTE_ACTIVACION
                    ) {
                        \App\Services\StripeSubscriptionService::activarSuscripcion($suscripcion);
                    }
                }
            }
        }

        return redirect()
            ->route('conversion.finished', ['token' => $token])
            ->with('recurrente_setup_success', true)
            ->with('success', 'Domiciliación configurada correctamente.');
    } catch (Throwable $e) {
        Log::error("Error Stripe processSepa: " . $e->getMessage());
        return back()->with('error', 'Error al guardar SEPA: ' . $e->getMessage());
    }
}

}
