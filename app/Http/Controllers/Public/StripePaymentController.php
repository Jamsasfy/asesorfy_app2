<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Factura;
use App\Enums\FacturaEstadoEnum;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\PagoFacturaConfirmado;

class StripePaymentController extends Controller
{
    /**
     * 1. INICIAR PAGO: Prepara la sesión de Stripe y redirige al cliente.
     */
    public function pay(Factura $factura)
    {
        // Seguridad: Solo permitir pagar si está pendiente
        if ($factura->estado !== FacturaEstadoEnum::PENDIENTE_PAGO) {
            // Si ya está pagada, lo mandamos a la pantalla de éxito directamente
            if ($factura->estado === FacturaEstadoEnum::PAGADA) {
                return redirect()->route('payment.success', ['factura' => $factura->id]);
            }

            return abort(403, 'Esta factura no se puede pagar (está anulada o en otro estado).');
        }

        // Iniciamos Stripe con la clave secreta del .env
        Stripe::setApiKey(env('STRIPE_SECRET'));

        // 🚑 PARCHE SOLO EN LOCAL (WAMP / artisan serve):
        // Desactivar verificación SSL para evitar "Network error [errno 60]"
        if (app()->isLocal()) {
            Stripe::setVerifySslCerts(false);
        }

        // Construimos los items para el carrito de Stripe
        $lineItems = [];

        foreach ($factura->items as $item) {
            // Stripe necesita el precio en CÉNTIMOS (integer)
            // Usamos 'precio_unitario_aplicado' (ya con descuentos)
            // y le sumamos el IVA porque vamos a cobrar el bruto final.

            $precioBase = $item->precio_unitario_aplicado;
            $iva        = $item->porcentaje_iva; // ej: 21.00

            // Precio unitario FINAL con IVA
            $precioConIva = $precioBase * (1 + ($iva / 100));

            // Convertir a céntimos y asegurar entero
            $unitAmount = (int) round($precioConIva * 100);

            // Descripción para el recibo del cliente
            $nombreProducto = $item->descripcion ?? 'Servicio AsesorFy';

            $lineItems[] = [
                'price_data' => [
                    'currency'     => 'eur',
                    'product_data' => [
                        'name' => $nombreProducto,
                    ],
                    'unit_amount'  => $unitAmount,
                ],
                'quantity'   => (int) $item->cantidad,
            ];
        }

        try {
            // Creamos la sesión de Checkout
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items'           => $lineItems,
                'mode'                 => 'payment', // Pago único

                // URLs de retorno (Laravel las genera)
                'success_url'          => route('payment.success', ['factura' => $factura->id]) . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url'           => route('payment.cancel', ['factura' => $factura->id]),

                // Pre-rellenamos el email para que no tenga que escribirlo
                'customer_email'       => $factura->cliente->email_contacto,

                // Metadatos para nosotros (útil si usamos webhooks luego)
                'metadata'             => [
                    'factura_id'      => $factura->id,
                    'venta_id'        => $factura->venta_id,
                    'numero_factura'  => $factura->numero_factura,
                ],
            ]);

            // Redirigimos al usuario a la página segura de Stripe
            return redirect($session->url);
        } catch (\Exception $e) {
            Log::error("Error creando sesión Stripe: " . $e->getMessage());

            // En local mostramos el mensaje real para depurar mejor
            $mensaje = app()->isLocal()
                ? 'Error al conectar con la pasarela de pago: ' . $e->getMessage()
                : 'Error al conectar con la pasarela de pago. Inténtalo de nuevo.';

            return back()->with('error', $mensaje);
        }
    }

    /**
     * 2. ÉXITO: El cliente ha pagado y Stripe lo devuelve aquí.
     */
    public function success(Request $request, Factura $factura)
    {
        $sessionId = $request->get('session_id');

        if (!$sessionId) {
            // Si entra aquí sin session_id pero la factura ya está pagada, mostramos éxito
            if ($factura->estado === FacturaEstadoEnum::PAGADA) {
                return view('public.payment.success', ['factura' => $factura]);
            }

            return redirect()->route('payment.cancel', $factura);
        }

        try {
            Stripe::setApiKey(env('STRIPE_SECRET'));

            // 🚑 PARCHE SOLO EN LOCAL (misma razón que en pay())
            if (app()->isLocal()) {
                Stripe::setVerifySslCerts(false);
            }

            $session = Session::retrieve($sessionId);

            // Verificamos que Stripe diga que está pagado
            if ($session->payment_status === 'paid') {
                // ✅ ACTUALIZAR ESTADO EN BASE DE DATOS
                if ($factura->estado !== FacturaEstadoEnum::PAGADA) {
                    $factura->update([
                        'estado'                    => FacturaEstadoEnum::PAGADA,
                        'stripe_payment_intent_id'  => $session->payment_intent, // Guardamos el ID de transacción
                        // 'metodo_pago'            => 'stripe', // Si tienes esa columna, descomenta
                    ]);

                    // --- NUEVO: DEJAR RASTRO EN EL LEAD ---
                    if ($venta = $factura->venta) {
                        if ($lead = $venta->lead) {
                            $lead->comentarios()->create([
                                'user_id'   => 9999, // Bot
                                'contenido' => "💰 PAGO RECIBIDO: La factura {$factura->numero_factura} (" . number_format($factura->total_factura, 2, ',', '.') . "€) ha sido abonada correctamente por tarjeta.",
                            ]);
                            
                            // Opcional: Notificar al comercial
                            // \Filament\Notifications\Notification::make()... sendToDatabase($lead->asignado);
                        }
                    }
                    // --------------------------------------



                    Log::info("Factura #{$factura->id} pagada correctamente vía Stripe.");
                    // Aquí podrías disparar notificación/email de "Factura pagada"
                    // 📧 Email de confirmación simple (sin factura adjunta)
        if ($factura->cliente && $factura->cliente->email_contacto) {
            try {
                Mail::to($factura->cliente->email_contacto)
                    ->send(new PagoFacturaConfirmado($factura));
            } catch (\Exception $e) {
                Log::error("Error enviando email de confirmación de pago: " . $e->getMessage());
            }
        }
                }

                return view('public.payment.success', ['factura' => $factura]);
            }
        } catch (\Exception $e) {
            Log::error("Error verificando pago Stripe: " . $e->getMessage());

            if (app()->isLocal()) {
                return redirect()
                    ->route('payment.cancel', $factura)
                    ->with('error', 'Error verificando el pago en Stripe: ' . $e->getMessage());
            }
        }

        // Si algo falló en la verificación
        return redirect()->route('payment.cancel', $factura);
    }

    /**
     * 3. CANCELADO: El cliente le dio a "Volver atrás" en Stripe.
     */
    public function cancel(Factura $factura)
    {
        return view('public.payment.cancel', ['factura' => $factura]);
    }
}
