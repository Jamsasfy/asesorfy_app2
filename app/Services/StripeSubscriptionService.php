<?php

namespace App\Services;

use App\Models\ClienteSuscripcion;
use App\Models\Factura;
use App\Enums\FacturaEstadoEnum;
use App\Enums\ClienteSuscripcionEstadoEnum;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\Price;
use Stripe\InvoiceItem;
use Carbon\Carbon;

class StripeSubscriptionService
{
    /**
     * Activa la suscripción en Stripe.
     * Genera SIEMPRE la factura local de la prorrata (Devengo).
     * - Tarjeta: Cobra prorrata YA -> Factura PAGADA.
     * - SEPA: Agrupa cobro día 1 -> Factura PENDIENTE.
     */
    public static function activarSuscripcion(ClienteSuscripcion $suscripcion): void
    {
        $cliente = $suscripcion->cliente;
        $servicio = $suscripcion->servicio;

        if (!$cliente->stripe_customer_id) {
            throw new \Exception("El cliente no tiene ID de Stripe.");
        }

        // 1. Configurar Stripe
        Stripe::setApiKey(config('services.stripe.secret'));
        if (app()->isLocal()) Stripe::setVerifySslCerts(false);

        // 2. Detectar Método de Pago
        $stripeCustomer = \Stripe\Customer::retrieve([
            'id' => $cliente->stripe_customer_id,
            'expand' => ['invoice_settings.default_payment_method']
        ]);
        
        $defaultPm = $stripeCustomer->invoice_settings->default_payment_method;
        $pmId = $defaultPm->id ?? null;
        $tipoPago = $defaultPm->type ?? 'card'; 

        Log::info("🔄 Activando suscripción ({$tipoPago}) para #{$suscripcion->id}");

        // 3. Cálculos Fiscales
        $ivaPercent = \App\Models\Cliente::getPorcentajeImpuesto($cliente->codigo_postal, $cliente->provincia);
        $factorIva = 1 + ($ivaPercent / 100);

        // 4. Calcular Prorrata
        $fechaInicio = now(); 
        $inicioMesSiguiente = $fechaInicio->copy()->addMonth()->startOfMonth();
        
        $precioMensualBase = $suscripcion->precio_acordado; 
        $precioMensualBruto = round($precioMensualBase * $factorIva, 2);
        
        $diasMes = $fechaInicio->daysInMonth;
        $diasRestantes = ($diasMes - $fechaInicio->day) + 1;
        
        $prorrataBruta = 0;
        if ($fechaInicio->day > 1) {
            $prorrataBruta = round(($precioMensualBruto / $diasMes) * $diasRestantes, 2);
        }

        // 5. Preparar Descripción
        $nombreServicio = $suscripcion->nombre_personalizado ?? ($servicio->nombre ?? 'Suscripción');
        $descProrrata = "$nombreServicio - Prorrata " . ucfirst($fechaInicio->locale('es')->translatedFormat('F Y'));

        try {
            // A. Stripe: Precio Recurrente
            $priceId = self::ensureStripePrice($servicio, $precioMensualBruto, $ivaPercent);

            // =========================================================
            // 🔥 LÓGICA DE COBRO (TARJETA vs SEPA)
            // =========================================================
            
            if ($tipoPago === 'card' && $prorrataBruta > 0) {
                // --- CASO TARJETA: COBRO INMEDIATO ---
                
                // 1. Crear Item Suelto (Prorrata)
                \Stripe\InvoiceItem::create([
                    'customer' => $cliente->stripe_customer_id,
                    'amount'   => (int)round($prorrataBruta * 100),
                    'currency' => 'eur',
                    'description' => $descProrrata . " (Cobro inmediato)", 
                    'metadata' => ['suscripcion_local_id' => $suscripcion->id]
                ]);

                // 2. Forzar Factura PUNTUAL y Cobrar YA
                $invoice = \Stripe\Invoice::create([
                    'customer' => $cliente->stripe_customer_id,
                    'auto_advance' => true,
                ]);
                $invoice->finalizeInvoice();
                Log::info("💳 Prorrata cobrada inmediatamente (Invoice {$invoice->id})");

                // 3. Crear Suscripción LIMPIA para el futuro (Día 1)
                $stripeSub = \Stripe\Subscription::create([
                    'customer' => $cliente->stripe_customer_id,
                    'items' => [['price' => $priceId, 'quantity' => $suscripcion->cantidad]],
                    'trial_end' => $inicioMesSiguiente->timestamp,
                    'default_payment_method' => $pmId,
                    'metadata' => ['suscripcion_local_id' => $suscripcion->id]
                ]);

            } else {
                // --- CASO SEPA (DIFERIDO) ---
                // 🔥 CAMBIO CLAVE: Primero Suscripción, luego Ítem vinculado.
                
                // 1. Crear Suscripción (Trial hasta el día 1) -> Genera factura de 0€
                $stripeSub = \Stripe\Subscription::create([
                    'customer' => $cliente->stripe_customer_id,
                    'items' => [['price' => $priceId, 'quantity' => $suscripcion->cantidad]],
                    'trial_end' => $inicioMesSiguiente->timestamp, // Agrupa todo el día 1
                    'default_payment_method' => $pmId,
                    'metadata' => ['suscripcion_local_id' => $suscripcion->id]
                ]);
                
                Log::info("🏦 SEPA: Suscripción creada (Trial). ID: {$stripeSub->id}");

                // 2. Crear Ítem Prorrata y VINCULARLO a la suscripción
                // Al estar vinculado a una suscripción en trial, NO se cobra hoy. Se espera a la next invoice.
                if ($prorrataBruta > 0) {
                    \Stripe\InvoiceItem::create([
                        'customer' => $cliente->stripe_customer_id,
                        'subscription' => $stripeSub->id, // <--- ESTA ES LA CLAVE
                        'amount'   => (int)round($prorrataBruta * 100),
                        'currency' => 'eur',
                        'description' => $descProrrata,
                        'metadata' => ['suscripcion_local_id' => $suscripcion->id]
                    ]);
                    Log::info("🏦 Prorrata vinculada a la suscripción (cobro diferido).");
                }
            }

            // D. Actualizar Local
            $suscripcion->update([
                'stripe_subscription_id' => $stripeSub->id,
                'stripe_status' => $stripeSub->status,
                'estado' => ClienteSuscripcionEstadoEnum::ACTIVA,
                'fecha_inicio' => $fechaInicio,
            ]);

            // E. Generar Factura Local (PAGADA o PENDIENTE)
            if ($prorrataBruta > 0) {
                 $baseProrrata = round($prorrataBruta / $factorIva, 2);
                 
                 // Tarjeta -> Pagada (stripe)
                 // SEPA -> Pendiente (domiciliacion)
                 $estadoFactura = ($tipoPago === 'sepa_debit') 
                    ? FacturaEstadoEnum::PENDIENTE_PAGO 
                    : FacturaEstadoEnum::PAGADA;

                 FacturacionRecurrenteService::crearFacturaRecurrenteManual(
                    $suscripcion,
                    now(), // Fecha factura = HOY
                    $baseProrrata,
                    $estadoFactura, 
                    $descProrrata
                 );
                 
                 Log::info("🧾 Factura Prorrata creada localmente. Estado: {$estadoFactura->value}");
            }

        } catch (\Exception $e) {
            Log::error("❌ Error activando suscripción Stripe: " . $e->getMessage());
            throw $e;
        }
    }

    private static function ensureStripePrice($servicio, $precioBruto, $ivaPercent)
    {
        $prices = Price::all(['product' => $servicio->stripe_product_id, 'limit' => 10]);
        foreach ($prices->data as $p) {
            if (isset($p->metadata['iva_percent']) && 
                (float)$p->metadata['iva_percent'] == (float)$ivaPercent &&
                $p->unit_amount == (int)round($precioBruto * 100)) {
                return $p->id;
            }
        }

        return Price::create([
            'product' => $servicio->stripe_product_id,
            'unit_amount' => (int)round($precioBruto * 100),
            'currency' => 'eur',
            'recurring' => ['interval' => 'month'],
            'metadata' => ['iva_percent' => (string)$ivaPercent]
        ])->id;
    }
}