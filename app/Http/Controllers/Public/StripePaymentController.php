<?php

namespace App\Http\Controllers\Public;

use App\Models\Cliente;
use Exception;
use Stripe\Customer;
use App\Http\Controllers\Controller;
use App\Models\Venta;
use App\Models\LeadConversionLink;
use App\Enums\FacturaEstadoEnum;
use App\Enums\VentaEstadoEnum;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Illuminate\Support\Facades\Log;

class StripePaymentController extends Controller
{
    public function pay(Venta $venta)
    {
        // 1. Validaciones previas
        if ($venta->estado === VentaEstadoEnum::COMPLETADA && $venta->tienePagoInicialCompletado()) {
            return $this->viewSuccess($venta);
        }

        if (!$venta->requierePagoInicial()) {
            abort(403, 'Esta venta no requiere pago inicial.');
        }

        $venta->loadMissing('items.servicio', 'cliente');

        // Configuración de Stripe
        Stripe::setApiKey(config('services.stripe.secret'));
        if (app()->isLocal()) {
            Stripe::setVerifySslCerts(false);
        }

        // 🔥 CEREBRO FISCAL: Detectar impuestos antes de cobrar
        $porcentajeIva = Cliente::getPorcentajeImpuesto(
            $venta->cliente->codigo_postal ?? null,
            $venta->cliente->provincia ?? null
        );
        $factorIva = 1 + ($porcentajeIva / 100); // 1.00 o 1.21

        // 2. Construir Items para el carrito
        $lineItems = [];
        foreach ($venta->items as $item) {
            // Solo cobramos servicios ÚNICOS ahora
            if (!$item->servicio || $item->servicio->tipo->value !== 'unico') {
                continue;
            }

            $precioBase = (float) $item->precio_unitario_aplicado;
            $precioConIva = $precioBase * $factorIva;

            $unitAmount = (int) round($precioConIva * 100);

            $productData = [];
            if ($item->servicio->stripe_product_id) {
                $productData['product'] = $item->servicio->stripe_product_id;
            } else {
                $productData['product_data'] = [
                    'name' => $item->nombre_personalizado ?: ($item->servicio->nombre ?? 'Servicio AsesorFy'),
                ];
            }

            $lineItems[] = [
                'price_data' => array_merge([
                    'currency'    => 'eur',
                    'unit_amount' => $unitAmount,
                ], $productData),
                'quantity' => (int) $item->cantidad,
            ];
        }

        if (empty($lineItems)) {
            abort(400, 'No hay conceptos únicos facturables.');
        }

        try {
            // 3. Gestión del Cliente en Stripe
            $customerOptions = [];

            if ($venta->cliente && $venta->cliente->stripe_customer_id) {
                $customerOptions['customer'] = $venta->cliente->stripe_customer_id;
                $customerOptions['customer_update'] = [
                    'name' => 'auto',
                    'address' => 'auto',
                ];
            } else {
                $customerOptions['customer_email'] = $venta->cliente->email_contacto ?? null;
                $customerOptions['customer_creation'] = 'always';
            }

            // 4. Crear la Sesión
            $sessionPayload = array_merge([
                'payment_method_types' => ['card'],
                'line_items'           => $lineItems,
                'mode'                 => 'payment',

                // Guardar tarjeta para el futuro (Checkout del pago inicial)
                'payment_intent_data' => [
                    'setup_future_usage' => 'off_session',
                    'metadata' => [
                        'iva_aplicado' => $porcentajeIva . '%',
                    ],
                ],

                'success_url' => route('payment.success', ['venta' => $venta->id]) . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'  => route('payment.cancel', ['venta' => $venta->id]),

                'metadata' => [
                    'venta_id'   => $venta->id,
                    'cliente_id' => $venta->cliente_id,
                ],
            ], $customerOptions);

            $session = Session::create($sessionPayload);

            return redirect($session->url);

        } catch (Exception $e) {
            Log::error("Error creando sesión Stripe venta {$venta->id}: " . $e->getMessage());
            return back()->with('error', 'Error al conectar con la pasarela de pago.');
        }
    }

    public function success(Request $request, Venta $venta)
    {
        $sessionId = $request->get('session_id');
        $yaEstabaPagada = $venta->tienePagoInicialCompletado();

        // Si ya estaba pagada → mostrar vista éxito
        if (!$sessionId && $yaEstabaPagada) {
            return $this->viewSuccess($venta);
        }

        if (!$sessionId) {
            return redirect()->route('payment.cancel', ['venta' => $venta->id]);
        }

        try {
            Stripe::setApiKey(config('services.stripe.secret'));
            if (app()->isLocal()) Stripe::setVerifySslCerts(false);

            // Expandimos payment_intent para sacar la tarjeta usada
            $session = Session::retrieve([
                'id' => $sessionId,
                'expand' => ['payment_intent'],
            ]);

            if ($session->payment_status === 'paid') {

                // A) Guardar/Vincular stripe_customer_id + poner default_payment_method
                if ($session->customer && $venta->cliente) {
                    $cliente = $venta->cliente;

                    if ($cliente->stripe_customer_id !== $session->customer) {
                        $cliente->stripe_customer_id = $session->customer;
                        $cliente->saveQuietly();
                    }

                    $paymentMethodId = $session->payment_intent->payment_method ?? null;

                    if ($paymentMethodId) {
                        try {
                            Customer::update($cliente->stripe_customer_id, [
                                'invoice_settings' => [
                                    'default_payment_method' => $paymentMethodId,
                                ],
                                'name'     => trim($cliente->razon_social),
                                'email'    => $cliente->email_contacto,
                                'metadata' => [
                                    'razon_social' => $cliente->razon_social,
                                    'nombre_comercial' => $cliente->nombre_comercial ?? '',
                                    'dni_cif'      => $cliente->dni_cif,
                                    'cliente_id'   => $cliente->id,
                                ],
                            ]);
                        } catch (Exception $e) {
                            Log::error("Error asignando tarjeta default en success: " . $e->getMessage());
                        }
                    }
                }

                // B) Procesar venta solo si no estaba pagada
                if (!$yaEstabaPagada) {
                    $extraData = [];
                    $link = LeadConversionLink::where('meta->existing_venta_id', $venta->id)->latest()->first();
                    if (!$link) {
                        $link = LeadConversionLink::where('meta->existing_cliente_id', $venta->cliente_id)->latest()->first();
                    }
                    if ($link && !empty($link->meta['form_data'])) {
                        $extraData = $link->meta['form_data'];
                    }

                    $venta->procesarCobroInicial(
                        now(),
                        'stripe',
                        $session->payment_intent->id,
                        $extraData
                    );
                }

                // ✅ NUEVO: que viewSuccess decida el siguiente paso (pago recurrente / setup / finished)
                return $this->viewSuccess($venta->fresh());
            }

        } catch (Exception $e) {
            Log::error("Error verificando pago Stripe venta {$venta->id}: " . $e->getMessage());
        }

        // Fallback por si acaso
        if ($venta->fresh()->tienePagoInicialCompletado()) {
            return $this->viewSuccess($venta);
        }

        return redirect()->route('payment.cancel', ['venta' => $venta->id])
            ->with('error', 'No se ha podido verificar el pago.');
    }

    /**
     * Helper privado: Redirige al flujo nuevo de conversión (finished / setup) si hay link.
     */
    private function viewSuccess(Venta $venta)
    {
        // 1) Buscamos el link asociado para poder volver
        $link = LeadConversionLink::where('meta->existing_venta_id', $venta->id)
            ->latest()
            ->first();

        // Fallback por si acaso
        if (!$link) {
            $link = LeadConversionLink::where('meta->existing_cliente_id', $venta->cliente_id)
                ->latest()
                ->first();
        }

        // Si encontramos el link, aplicamos el flujo nuevo
        if ($link) {

            $venta->loadMissing('items.servicio', 'cliente');

            $tieneRecurrente = $venta->items->contains(fn ($i) => $i->servicio && $i->servicio->tipo->value === 'recurrente');

            // ✅ Si NO hay recurrente, volvemos a finished como siempre
            if (! $tieneRecurrente) {
                return redirect()->route('conversion.finished', ['token' => $link->token])
                    ->with('success', 'Pago recibido correctamente.');
            }

            // Preferencia del recurrente en meta del link
            $recurrenteMetodo = data_get($link->meta, 'recurrente_metodo'); // 'tarjeta' | 'domiciliacion' | null

            // A) Si aún no ha elegido método recurrente -> pantalla de elección
            if (empty($recurrenteMetodo)) {
                return redirect()->route('conversion.pago-recurrente', ['token' => $link->token])
                    ->with('success', 'Pago inicial recibido. Ahora configura tu cuota mensual.');
            }

            // B) Si eligió domiciliación -> setup SEPA directo
            if ($recurrenteMetodo === 'domiciliacion') {
                return redirect()->route('stripe.setup-sepa', ['token' => $link->token])
                    ->with('success', 'Pago inicial recibido. Ahora configura la domiciliación (IBAN) para la cuota mensual.');
            }

            // C) Si eligió tarjeta:
            // ✅ si ya hay tarjeta default -> a finished disparando el auto-proceso
            // ✅ si no hay tarjeta default -> setup-card
            $cliente = $venta->cliente;
            $tieneTarjetaDefault = false;

            if ($cliente && $cliente->stripe_customer_id) {
                try {
                    Stripe::setApiKey(config('services.stripe.secret'));
                    if (app()->isLocal()) Stripe::setVerifySslCerts(false);

                    $customer = Customer::retrieve([
                        'id'     => $cliente->stripe_customer_id,
                        'expand' => ['invoice_settings.default_payment_method'],
                    ]);

                    $defaultPM = $customer->invoice_settings->default_payment_method ?? null;
                    $tieneTarjetaDefault = (bool) ($defaultPM && isset($defaultPM->card));
                } catch (\Throwable $e) {
                    Log::warning("viewSuccess: no se pudo comprobar default_payment_method: " . $e->getMessage());
                }
            }

            if (! $tieneTarjetaDefault) {
                return redirect()->route('stripe.setup-card', ['token' => $link->token])
                    ->with('success', 'Pago inicial recibido. Ahora configura la tarjeta para la cuota mensual.');
            }

            return redirect()->route('conversion.finished', ['token' => $link->token])
                ->with('payment_setup_success', true)
                ->with('success', 'Pago inicial recibido. Tu cuota mensual se cobrará con la tarjeta guardada.');
        }

        // 3) Solo si no encontramos el link (muy raro), mostramos la vista de recibo antigua como emergencia
        $facturaPagada = $venta->facturas()
            ->where('estado', FacturaEstadoEnum::PAGADA)
            ->latest()
            ->first();

        return view('public.payment.success', [
            'venta'   => $venta,
            'factura' => $facturaPagada,
        ]);
    }

    public function cancel(Venta $venta)
    {
        $facturaPendiente = $venta->facturas()
            ->where('estado', FacturaEstadoEnum::PENDIENTE_PAGO)
            ->latest()
            ->first();

        return view('public.payment.cancel', [
            'venta'   => $venta,
            'factura' => $facturaPendiente,
        ]);
    }
}
