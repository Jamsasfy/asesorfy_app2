<?php

namespace App\Services;

use App\Enums\ClienteSuscripcionEstadoEnum;
use App\Enums\FacturaEstadoEnum;
use App\Models\Cliente;
use App\Models\ClienteSuscripcion;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Stripe\Coupon;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\InvoiceItem;
use Stripe\Price;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\StripeClient;
use Stripe\Exception\InvalidRequestException;


class StripeSubscriptionService
{
    /**
     * Activa la suscripción en Stripe.
     *
     * IMPORTANTÍSIMO (FIX):
     * - El Price de Stripe SIEMPRE debe ser el precio REAL (sin descuento) para que el cupón aplique bien.
     * - El descuento recurrente se aplica SOLO vía cupón.
     */
public static function activarSuscripcion(ClienteSuscripcion $suscripcion): void
{
    $cliente  = $suscripcion->cliente;
    $servicio = $suscripcion->servicio;

    if (! $cliente?->stripe_customer_id) {
        throw new Exception("El cliente no tiene ID de Stripe.");
    }

    Stripe::setApiKey(config('services.stripe.secret'));
    if (app()->isLocal()) {
        Stripe::setVerifySslCerts(false);
    }

    $stripeCustomer = Customer::retrieve([
        'id'     => $cliente->stripe_customer_id,
        'expand' => ['invoice_settings.default_payment_method'],
    ]);

    $defaultPm = $stripeCustomer->invoice_settings->default_payment_method ?? null;
    $pmId      = $defaultPm->id ?? null;
    $tipoPago  = $defaultPm->type ?? 'card';

    Log::info("🔄 Activando suscripción ({$tipoPago}) para #{$suscripcion->id}");

    // -------------------------
    // IVA / fechas
    // -------------------------
    $ivaPercent = Cliente::getPorcentajeImpuesto($cliente->codigo_postal, $cliente->provincia);
    $factorIva  = 1 + ($ivaPercent / 100);

    $fechaInicio        = now();
    $inicioMesSiguiente = $fechaInicio->copy()->addMonth()->startOfMonth();

    // -------------------------
    // FLAGS y blueprint line (fuente real del contrato)
    // -------------------------
    $datos = self::getDatosAdicionales($suscripcion);

    $bpLine = data_get($datos, 'sale_blueprint_line');
    if (!is_array($bpLine)) {
        $bpLine = [];
    }

    $qty = (int) ($suscripcion->cantidad ?: data_get($bpLine, 'unidades', 1));
    if ($qty <= 0) $qty = 1;

    $cobroPrimerMes = data_get($bpLine, 'cobro_primer_mes', data_get($datos, 'cobro_primer_mes', 'prorrata'));

    $rawNoCobrar = $suscripcion->getRawOriginal('no_cobrar_primer_periodo');
    $noCobrarBool = $rawNoCobrar !== null
        ? self::toBool($rawNoCobrar, false)
        : self::toBool(data_get($bpLine, 'no_cobrar_primer_periodo', data_get($datos, 'no_cobrar_primer_periodo', false)), false);

    if ($noCobrarBool) {
        $cobroPrimerMes = 'gratis';
    }

    $dtoTipoRaw = $suscripcion->getRawOriginal('descuento_tipo');
    if ($dtoTipoRaw === null) $dtoTipoRaw = data_get($bpLine, 'descuento.tipo', data_get($datos, 'descuento.tipo', ''));

    $dtoValorRaw = $suscripcion->getRawOriginal('descuento_valor');
    if ($dtoValorRaw === null) $dtoValorRaw = data_get($bpLine, 'descuento.valor', data_get($datos, 'descuento.valor', 0));

    $dtoMesesRaw = $suscripcion->getRawOriginal('descuento_duracion_meses');
    if ($dtoMesesRaw === null) $dtoMesesRaw = data_get($bpLine, 'descuento.meses', data_get($datos, 'descuento.meses', 0));

    $dtoTipo = mb_strtolower(trim((string) $dtoTipoRaw));

    if (is_string($dtoValorRaw)) {
        $dtoValorRaw = str_replace(['€', ' ', ','], ['', '', '.'], $dtoValorRaw);
    }
    $dtoValor = is_numeric($dtoValorRaw) ? (float) $dtoValorRaw : 0.0;

    $dtoMeses = (int) $dtoMesesRaw;

    $dtoPercent = 0.0;
    if ($dtoTipo === 'porcentaje') $dtoPercent = $dtoValor;

    if ($dtoPercent <= 0 || $dtoPercent > 100) $dtoPercent = 0.0;
    if ($dtoMeses < 1) $dtoMeses = 0;

    Log::info('🧩 Flags suscripción', [
        'suscripcion_id' => $suscripcion->id,
        'cobro_primer_mes' => $cobroPrimerMes,
        'dto_tipo' => $dtoTipo,
        'dto_percent' => $dtoPercent,
        'dto_meses' => $dtoMeses,
        'qty' => $qty,
    ]);

    // -------------------------
    // ✅ PRECIO MENSUAL REAL (SIN DESCUENTO) — FIX
    // -------------------------
    $baseLineaSinDto = (float) data_get($bpLine, 'subtotal_base', 0);

    if ($baseLineaSinDto <= 0) {
        $pbo = (float) data_get($bpLine, 'precio_base_original', 0);
        if ($pbo > 0) $baseLineaSinDto = $pbo * $qty;
    }

    if ($baseLineaSinDto <= 0) {
        $baseLineaSinDto = (float) ($servicio?->precio_base ?? 0) * $qty;
    }

    if ($baseLineaSinDto <= 0) {
        $baseLineaSinDto = (float) ($suscripcion->precio_acordado ?? 0) * $qty;
    }

    $precioMensualBaseUnit = round($baseLineaSinDto / $qty, 2);
    $precioMensualBrutoUnit = round($precioMensualBaseUnit * $factorIva, 2);
    $precioMensualBrutoTotal = round($precioMensualBrutoUnit * $qty, 2);

    Log::info('💶 Precio mensual para Stripe (FIX)', [
        'precio_base_linea_sin_dto' => $baseLineaSinDto,
        'precio_base_unit_sin_dto' => $precioMensualBaseUnit,
        'precio_bruto_unit' => $precioMensualBrutoUnit,
        'precio_bruto_total_mes' => $precioMensualBrutoTotal,
        'iva_percent' => $ivaPercent,
    ]);

    // -------------------------
    // CÁLCULO DE PRORRATA (Importe a cobrar HOY)
    // -------------------------
    $prorrataBruta = 0.0;

    $diasMes       = $fechaInicio->daysInMonth ?: 30;
    $diasRestantes = ($diasMes - (int) $fechaInicio->day) + 1;
    $esDia1 = (int) $fechaInicio->day === 1;

    if ($cobroPrimerMes === 'gratis') {
        $prorrataBruta = 0.0;
    } elseif ($cobroPrimerMes === 'completo') {
        $prorrataBruta = $precioMensualBrutoTotal;
    } else {
        if (! $esDia1) {
            $prorrataBruta = round(($precioMensualBrutoTotal / $diasMes) * $diasRestantes, 2);
        }
    }

    $nombreServicio = $suscripcion->nombre_personalizado ?? ($servicio->nombre ?? 'Suscripción');
    $descProrrata   = $nombreServicio;

    if ($cobroPrimerMes === 'completo') {
        $descProrrata .= ' - Mes completo (' . ucfirst($fechaInicio->locale('es')->translatedFormat('F Y')) . ')';
    } else {
        $descProrrata .= ' - Prorrata ' . ucfirst($fechaInicio->locale('es')->translatedFormat('F Y'));
    }

    $prorrataNeto = $prorrataBruta;

    if ($prorrataBruta > 0 && $dtoPercent > 0 && $dtoMeses > 0) {
        $descuentoAmount = round($prorrataBruta * ($dtoPercent / 100), 2);
        $prorrataNeto = max(0, $prorrataBruta - $descuentoAmount);

        $descProrrata .= " (Incluye dto. {$dtoPercent}%)";
    }

    // ids Stripe (solo cuando exista invoice real)
    $prorrataStripeInvoiceId = null;
    $prorrataStripePaymentIntentId = null;

    // ✅ NUEVO: referencia a factura local pendiente (solo SEPA)
    $facturaProrrataLocal = null;

    try {
        if (empty($servicio?->stripe_product_id)) {
            throw new Exception("El servicio {$servicio?->id} no tiene stripe_product_id.");
        }

        $priceId = self::ensureStripePrice($servicio, $precioMensualBrutoUnit, $ivaPercent);

        $couponId = null;
        if ($dtoPercent > 0 && $dtoMeses > 0) {
            $couponId = self::ensureStripeCoupon($servicio, $dtoPercent, $dtoMeses);
        }

        $esSepa        = ($tipoPago === 'sepa_debit');
        $sepaInmediato = $esSepa && ((int) $fechaInicio->day <= 15);

        $stripeSub = null;

        // =========================
        // TARJETA (igual que tenías)
        // =========================
        if ($tipoPago === 'card') {

            if ($prorrataNeto > 0) {
                $invoice = Invoice::create([
                    'customer'          => $cliente->stripe_customer_id,
                    'auto_advance'      => true,
                    'collection_method' => 'charge_automatically',
                    'metadata'          => ['suscripcion_local_id' => (string) $suscripcion->id],
                ]);

                InvoiceItem::create([
                    'customer'    => $cliente->stripe_customer_id,
                    'invoice'     => $invoice->id,
                    'amount'      => (int) round($prorrataNeto * 100),
                    'currency'    => 'eur',
                    'description' => $descProrrata . ' (Cobro inmediato)',
                    'metadata'    => ['suscripcion_local_id' => (string) $suscripcion->id],
                ]);

                try {
                    $invoice = Invoice::retrieve($invoice->id);
                    $invoice->finalizeInvoice();

                    if ($pmId) {
                        $invoice->pay(['payment_method' => $pmId]);
                    } else {
                        $invoice->pay();
                    }

                    $invoice = Invoice::retrieve($invoice->id);
                    $prorrataStripeInvoiceId = $invoice->id;
                    $prorrataStripePaymentIntentId = $invoice->payment_intent ?? null;

                    Log::info("💳 Prorrata tarjeta: invoice {$invoice->id} finalizada/pagada", [
                        'stripe_invoice_id' => $prorrataStripeInvoiceId,
                        'stripe_payment_intent_id' => $prorrataStripePaymentIntentId,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning("💳 Prorrata tarjeta: no se pudo finalizar/pagar invoice {$invoice->id}: " . $e->getMessage());
                }
            } else {
                if ($cobroPrimerMes === 'gratis') {
                    Log::info("💳 Prorrata tarjeta omitida por 'gratis'.");
                }
            }

            $payload = [
                'customer'                   => $cliente->stripe_customer_id,
                'items'                      => [[
                    'price'    => $priceId,
                    'quantity' => $qty,
                ]],
                'trial_end'                  => $inicioMesSiguiente->timestamp,
                'default_payment_method'     => $pmId,
                'proration_behavior'         => 'none',
                'metadata'                   => ['suscripcion_local_id' => (string) $suscripcion->id],
            ];

            if ($couponId) {
                $payload['discounts'] = [['coupon' => $couponId]];
            }

            $stripeSub = Subscription::create($payload);
        }

        // =========================
        // SEPA (NUEVO: creamos factura local PENDIENTE + metadata para que webhook la actualice)
        // =========================
        if ($esSepa) {

            // ✅ Crear factura local pendiente de prorrata antes (si hay prorrata)
            if ($prorrataNeto > 0) {
                $baseProrrata = round($prorrataNeto / $factorIva, 2);

                $facturaProrrataLocal = FacturacionRecurrenteService::crearFacturaRecurrenteManual(
                    $suscripcion,
                    now(),
                    $baseProrrata,
                    FacturaEstadoEnum::PENDIENTE_PAGO,
                    $descProrrata,
                    null,
                    null
                );

                Log::info("🧾 (SEPA) Factura local PENDIENTE creada para prorrata", [
                    'factura_id' => $facturaProrrataLocal->id,
                    'numero' => $facturaProrrataLocal->numero_factura,
                ]);
            }

            // 1) SEPA 1–15: prorrata ahora (invoice puntual)
            if ($sepaInmediato && $prorrataNeto > 0) {
                $invoice = Invoice::create([
                    'customer'          => $cliente->stripe_customer_id,
                    'auto_advance'      => true,
                    'collection_method' => 'charge_automatically',
                    'metadata'          => [
                        'suscripcion_local_id' => (string) $suscripcion->id,
                        'asesorfy_factura_local_id' => $facturaProrrataLocal ? (string) $facturaProrrataLocal->id : null,
                    ],
                ]);

                InvoiceItem::create([
                    'customer'    => $cliente->stripe_customer_id,
                    'invoice'     => $invoice->id,
                    'amount'      => (int) round($prorrataNeto * 100),
                    'currency'    => 'eur',
                    'description' => $descProrrata . ' (SEPA - se inicia cobro ahora)',
                    'metadata'    => array_filter([
                        'suscripcion_local_id' => (string) $suscripcion->id,
                        'asesorfy_tipo' => 'prorrata',
                        'asesorfy_factura_local_id' => $facturaProrrataLocal ? (string) $facturaProrrataLocal->id : null,
                        'asesorfy_factura_local_numero' => $facturaProrrataLocal ? (string) $facturaProrrataLocal->numero_factura : null,
                        'asesorfy_factura_local_serie' => $facturaProrrataLocal ? (string) $facturaProrrataLocal->serie : null,
                    ]),
                ]);

                try {
                    $invoice = Invoice::retrieve($invoice->id);
                    $invoice->finalizeInvoice();

                    if ($pmId) {
                        $invoice->pay(['payment_method' => $pmId]);
                    } else {
                        $invoice->pay();
                    }

                    $invoice = Invoice::retrieve($invoice->id);
                    $prorrataStripeInvoiceId = $invoice->id;
                    $prorrataStripePaymentIntentId = $invoice->payment_intent ?? null;

                    Log::info("🏦 SEPA (1-15): prorrata en invoice {$invoice->id} (cobro iniciado)", [
                        'stripe_invoice_id' => $prorrataStripeInvoiceId,
                        'stripe_payment_intent_id' => $prorrataStripePaymentIntentId,
                    ]);
                    // ✅ NO marcamos la factura local como pagada aquí: lo hará el webhook (más fiable)
                } catch (\Throwable $e) {
                    Log::warning("🏦 SEPA (1-15): no se pudo finalizar/iniciar pay invoice {$invoice->id}: " . $e->getMessage());
                }
            } else {
                if ($cobroPrimerMes === 'gratis') {
                    Log::info("🏦 Prorrata SEPA omitida por 'gratis'.");
                }
            }

            // 2) Suscripción limpia (trial hasta día 1)
            $payload = [
                'customer'                   => $cliente->stripe_customer_id,
                'items'                      => [[
                    'price'    => $priceId,
                    'quantity' => $qty,
                ]],
                'trial_end'                  => $inicioMesSiguiente->timestamp,
                'default_payment_method'     => $pmId,
                'proration_behavior'         => 'none',
                'metadata'                   => ['suscripcion_local_id' => (string) $suscripcion->id],
            ];

            if ($couponId) {
                $payload['discounts'] = [['coupon' => $couponId]];
            }

            $stripeSub = Subscription::create($payload);

            Log::info("🏦 SEPA: suscripción creada (trial). ID: {$stripeSub->id}");

            // 3) SEPA 16–fin: prorrata diferida vinculada a subscription (día 1)
            if (! $sepaInmediato && $prorrataNeto > 0) {
                InvoiceItem::create([
                    'customer'     => $cliente->stripe_customer_id,
                    'subscription' => $stripeSub->id,
                    'amount'       => (int) round($prorrataNeto * 100),
                    'currency'     => 'eur',
                    'description'  => $descProrrata . ' (SEPA - se cobrará día 1)',
                    'metadata'     => array_filter([
                        'suscripcion_local_id' => (string) $suscripcion->id,
                        'asesorfy_tipo' => 'prorrata',
                        'asesorfy_factura_local_id' => $facturaProrrataLocal ? (string) $facturaProrrataLocal->id : null,
                        'asesorfy_factura_local_numero' => $facturaProrrataLocal ? (string) $facturaProrrataLocal->numero_factura : null,
                        'asesorfy_factura_local_serie' => $facturaProrrataLocal ? (string) $facturaProrrataLocal->serie : null,
                    ]),
                ]);

                Log::info("🏦 SEPA (16-fin): prorrata vinculada a suscripción (cobro diferido).");
            }
        }

        if (! $stripeSub) {
            throw new Exception("No se pudo crear la suscripción en Stripe (stripeSub null).");
        }

        $suscripcion->update([
            'stripe_subscription_id' => $stripeSub->id,
            'stripe_status'          => $stripeSub->status,
            'estado'                 => ClienteSuscripcionEstadoEnum::ACTIVA,
            'fecha_inicio'           => $fechaInicio,
        ]);

        // ✅ IMPORTANTE:
        // - Ya NO creamos aquí factura local de prorrata para SEPA (porque ya la creamos antes, pendiente)
        // - Para tarjeta, si quieres mantener la factura local “manual”, se puede, pero tú dijiste que para card prefieres no colgar.
        //   Con tu modelo actual, puedes dejar la factura local de prorrata para card (ya está pagada) o moverla al webhook también.

        if ($tipoPago !== 'sepa_debit' && $prorrataNeto > 0) {
            $baseProrrata = round($prorrataNeto / $factorIva, 2);

            FacturacionRecurrenteService::crearFacturaRecurrenteManual(
                $suscripcion,
                now(),
                $baseProrrata,
                FacturaEstadoEnum::PAGADA,
                $descProrrata,
                $prorrataStripeInvoiceId,
                $prorrataStripePaymentIntentId
            );

            Log::info("🧾 (CARD) Factura prorrata local creada como PAGADA", [
                'stripe_invoice_id' => $prorrataStripeInvoiceId,
                'stripe_payment_intent_id' => $prorrataStripePaymentIntentId,
            ]);
        }

    } catch (Exception $e) {
        Log::error("❌ Error activando suscripción Stripe: " . $e->getMessage());
        throw $e;
    }
}



    /**
     * Busca un Price existente (mismo producto, mismo unit_amount, mismo iva_percent) o lo crea.
     * OJO: aquí precioBruto es POR UNIDAD (FIX).
     */
    private static function ensureStripePrice($servicio, $precioBrutoUnit, $ivaPercent): string
    {
        $targetAmount = (int) round(((float) $precioBrutoUnit) * 100);

        // subimos limit para no “perder” el price y crear otro
        $prices = Price::all([
            'product' => $servicio->stripe_product_id,
            'limit'   => 100,
        ]);

        foreach ($prices->data as $p) {
            if (
                isset($p->metadata['iva_percent']) &&
                (float) $p->metadata['iva_percent'] == (float) $ivaPercent &&
                (int) $p->unit_amount === $targetAmount &&
                (string) ($p->recurring->interval ?? '') === 'month'
            ) {
                return $p->id;
            }
        }

        return Price::create([
            'product'     => $servicio->stripe_product_id,
            'unit_amount' => $targetAmount,
            'currency'    => 'eur',
            'recurring'   => ['interval' => 'month'],
            'metadata'    => [
                'iva_percent' => (string) $ivaPercent,
                'asesorfy'    => '1',
            ],
        ])->id;
    }

    private static function ensureStripeCoupon($servicio, float $percentOff, int $durationMonths): string
    {
        $percentOff = round($percentOff, 2);
        $durationMonths = max(1, (int) $durationMonths);

        if (empty($servicio?->stripe_product_id)) {
            throw new Exception("No se puede crear cupón: el servicio no tiene stripe_product_id.");
        }

        $pKey = (int) round($percentOff * 100);
        $couponKey = 'asfy_' . (string) $servicio->stripe_product_id . '_p' . $pKey . '_m' . $durationMonths;

        try {
            $existing = Coupon::retrieve($couponKey);

            $meta = (array) ($existing->metadata ?? []);

            if (
                ($existing->valid ?? true) &&
                (float) ($existing->percent_off ?? 0) == (float) $percentOff &&
                (string) ($existing->duration ?? '') === 'repeating' &&
                (int) ($existing->duration_in_months ?? 0) === (int) $durationMonths &&
                (string) ($meta['asesorfy'] ?? '') === '1'
            ) {
                return $existing->id;
            }

            $couponKey .= '_v2';
        } catch (\Throwable $e) {}

        try {
            $name = "DTO {$percentOff}% {$durationMonths}m";

            $coupon = Coupon::create([
                'id'                 => $couponKey,
                'name'               => $name,
                'percent_off'        => $percentOff,
                'duration'           => 'repeating',
                'duration_in_months' => $durationMonths,
                'applies_to' => [
                    'products' => [(string) $servicio->stripe_product_id],
                ],
                'metadata' => [
                    'asesorfy'        => '1',
                    'type'            => 'recurrente_porcentaje',
                    'product_id'      => (string) $servicio->stripe_product_id,
                    'percent_off'     => (string) $percentOff,
                    'duration_months' => (string) $durationMonths,
                    'servicio_id'     => (string) ($servicio->id ?? ''),
                ],
            ]);

            return $coupon->id;
        } catch (\Throwable $e) {
            try {
                $existing = Coupon::retrieve($couponKey);
                return $existing->id;
            } catch (\Throwable $e2) {
                Log::error("❌ No se pudo crear/recuperar cupón {$couponKey}: " . $e->getMessage());
                throw new Exception("No se pudo crear/recuperar cupón de descuento en Stripe.");
            }
        }
    }

    private static function getDatosAdicionales(ClienteSuscripcion $suscripcion): array
    {
        $datos = $suscripcion->datos_adicionales ?? null;

        if (is_string($datos)) {
            $decoded = json_decode($datos, true);
            return is_array($decoded) ? $decoded : [];
        }

        if (is_array($datos)) {
            return $datos;
        }

        $meta = $suscripcion->meta ?? null;
        if (is_array($meta)) return $meta;

        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private static function toBool(mixed $value, bool $default = false): bool
    {
        if (is_bool($value)) return $value;
        if ($value === null) return $default;
        if (is_int($value) || is_float($value)) return ((int) $value) === 1;

        if (is_string($value)) {
            $v = mb_strtolower(trim($value));
            if ($v === '') return $default;

            $parsed = filter_var($v, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            return $parsed === null ? $default : (bool) $parsed;
        }

        return (bool) $value;
    }

public static function getStripeSubscriptionSnapshot(string $subId): array
{
    \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
    if (app()->isLocal()) {
        \Stripe\Stripe::setVerifySslCerts(false);
    }

    $client = new \Stripe\StripeClient(config('services.stripe.secret'));

    // 1) Subscription (con expand)
    $sub = $client->subscriptions->retrieve($subId, [
        'expand' => [
            'customer',
            'items.data.price.product',
            'latest_invoice',
            'latest_invoice.payment_intent',
            'pending_setup_intent',

            // ✅ CLAVE para cupones/promos en Stripe actual:
            // Subscription tiene "discounts" (array). Hay que expandirlo.
            'discounts',

            // ✅ por si el descuento está a nivel de subscription item
            'items.data.discounts',
        ],
    ]);

    $customerId = is_string($sub->customer)
        ? $sub->customer
        : ($sub->customer->id ?? null);

    // 2) Customer + default payment method (para “Método de pago”)
    $pmSummary = null;
    try {
        if ($customerId) {
            $customer = $client->customers->retrieve($customerId, [
                'expand' => ['invoice_settings.default_payment_method'],
            ]);

            $defaultPm = $customer->invoice_settings->default_payment_method ?? null;

            if ($defaultPm) {
                if (isset($defaultPm->card)) {
                    $pmSummary = [
                        'type' => 'card',
                        'brand' => $defaultPm->card->brand ?? null,
                        'last4' => $defaultPm->card->last4 ?? null,
                        'exp_month' => $defaultPm->card->exp_month ?? null,
                        'exp_year' => $defaultPm->card->exp_year ?? null,
                    ];
                } elseif (isset($defaultPm->sepa_debit)) {
                    $pmSummary = [
                        'type' => 'sepa_debit',
                        'last4' => $defaultPm->sepa_debit->last4 ?? null,
                    ];
                } else {
                    $pmSummary = [
                        'type' => $defaultPm->type ?? 'unknown',
                    ];
                }
            }
        }
    } catch (\Throwable $e) {
        $pmSummary = null;
    }

    // 3) Items resumidos (unit_amount en CENTIMOS)
    $items = [];
    foreach (($sub->items->data ?? []) as $it) {
        $price = $it->price ?? null;
        $product = $price?->product ?? null;

        $items[] = [
            'subscription_item_id' => $it->id ?? null,
            'product_name' => is_object($product) ? ($product->name ?? null) : null,
            'price_id' => $price->id ?? null,
            'unit_amount' => $price->unit_amount ?? null, // ✅ cents
            'currency' => $price->currency ?? null,
            'interval' => $price->recurring->interval ?? null,
            'interval_count' => $price->recurring->interval_count ?? null,
            'quantity' => $it->quantity ?? null,
        ];
    }

    // Helper: sacar el primer Discount expandido desde subscription/items/preview
    $pickFirstDiscount = static function ($sub, $preview = null) {
        // A) subscription-level discounts
        if (isset($sub->discounts) && is_array($sub->discounts) && count($sub->discounts) > 0) {
            $d = $sub->discounts[0];
            if (is_object($d)) return $d;
        }

        // B) item-level discounts
        foreach (($sub->items->data ?? []) as $it) {
            if (isset($it->discounts) && is_array($it->discounts) && count($it->discounts) > 0) {
                $d = $it->discounts[0];
                if (is_object($d)) return $d;
            }
        }

        // C) invoice preview discounts
        if ($preview && isset($preview->discounts) && is_array($preview->discounts) && count($preview->discounts) > 0) {
            $d = $preview->discounts[0];
            if (is_object($d)) return $d;
        }

        return null;
    };

    // 4) Próxima factura (preview) => “Próxima factura” del dashboard
    $upcomingSummary = null;
    $previewObj = null;

    try {
        if ($customerId) {
            $previewObj = $client->invoices->createPreview([
                'customer' => $customerId,
                'subscription' => $sub->id,
                'preview_mode' => 'next',
                'expand' => [
                    'lines.data.price.product',
                    // ✅ por si la promo/cupón solo se ve “aplicada” aquí
                    'discounts',
                ],
            ]);

            $lines = [];
            foreach (($previewObj->lines->data ?? []) as $l) {
                $price = $l->price ?? null;
                $product = $price?->product ?? null;

                $lines[] = [
                    'description' => $l->description ?? null,
                    'quantity' => $l->quantity ?? null,
                    'amount' => $l->amount ?? null, // ✅ cents
                    'currency' => $previewObj->currency ?? null,
                    'period_start' => $l->period->start ?? null,
                    'period_end' => $l->period->end ?? null,
                    'proration' => (bool) ($l->proration ?? false),

                    'price_id' => $price->id ?? null,
                    'product_name' => is_object($product) ? ($product->name ?? null) : null,
                ];
            }

            $upcomingSummary = [
                'currency' => $previewObj->currency ?? null,
                'subtotal' => $previewObj->subtotal ?? null,      // cents
                'tax' => $previewObj->tax ?? null,                // cents (nullable)
                'total' => $previewObj->total ?? null,            // cents
                'amount_due' => $previewObj->amount_due ?? null,  // cents
                'next_payment_attempt' => $previewObj->next_payment_attempt ?? null,
                'lines' => $lines,
            ];
        }
    } catch (\Stripe\Exception\InvalidRequestException $e) {
        $upcomingSummary = ['error' => mb_substr((string) $e->getMessage(), 0, 220)];
    } catch (\Throwable $e) {
        $upcomingSummary = ['error' => mb_substr((string) $e->getMessage(), 0, 220)];
    }

    // 5) Últimas facturas reales
    $invoicesSummary = [];
    try {
        $invList = $client->invoices->all([
            'subscription' => $sub->id,
            'limit' => 10,
        ]);

        foreach (($invList->data ?? []) as $inv) {
            $invoicesSummary[] = [
                'id' => $inv->id ?? null,
                'status' => $inv->status ?? null,
                'created' => $inv->created ?? null,
                'currency' => $inv->currency ?? null,

                'total' => $inv->total ?? null,             // cents
                'amount_paid' => $inv->amount_paid ?? null, // cents
                'amount_due' => $inv->amount_due ?? null,   // cents

                'hosted_invoice_url' => $inv->hosted_invoice_url ?? null,
                'invoice_pdf' => $inv->invoice_pdf ?? null,
                'number' => $inv->number ?? null,
            ];
        }
    } catch (\Throwable $e) {
        $invoicesSummary = [];
    }

    // 6) Subscription resumen
    $latestInvoiceId = is_object($sub->latest_invoice ?? null)
        ? ($sub->latest_invoice->id ?? null)
        : ($sub->latest_invoice ?? null);

    // 7) ✅ Descuento / Cupón / Promo code (FIABLE)
    $discountSummary = null;

    try {
        // pillamos el primer Discount “expandido” donde esté
        $d = $pickFirstDiscount($sub, $previewObj);

        if ($d) {
            // Según el Discount object: cupón está en source.coupon y promo en promotion_code :contentReference[oaicite:3]{index=3}
            $couponId = $d->source->coupon ?? null;
            $promoId  = $d->promotion_code ?? null;

            $coupon = null;
            $promo  = null;

            // Para times_redeemed/max_redemptions => hay que traer el cupón
            if ($couponId) {
                try {
                    $coupon = $client->coupons->retrieve($couponId, []);
                } catch (\Throwable $e) {
                    $coupon = null;
                }
            }

            // Para el "code" => traer promotion code por ID (promo_...) :contentReference[oaicite:4]{index=4}
            if ($promoId) {
                try {
                    $promo = $client->promotionCodes->retrieve($promoId, []);
                } catch (\Throwable $e) {
                    $promo = null;
                }
            }

            $discountSummary = [
                'coupon' => $coupon ? [
                    'id' => $coupon->id ?? null,
                    'name' => $coupon->name ?? null,
                    'valid' => isset($coupon->valid) ? (bool) $coupon->valid : null,

                    'percent_off' => $coupon->percent_off ?? null,
                    'amount_off'  => $coupon->amount_off ?? null,   // cents
                    'currency'    => $coupon->currency ?? null,

                    'duration' => $coupon->duration ?? null,
                    'duration_in_months' => $coupon->duration_in_months ?? null,

                    'max_redemptions' => $coupon->max_redemptions ?? null,
                    'times_redeemed'  => $coupon->times_redeemed ?? null,
                    'redeem_by'       => $coupon->redeem_by ?? null,
                ] : ($couponId ? ['id' => $couponId] : null),

                'promotion_code' => $promo ? [
                    'id' => $promo->id ?? null,
                    'code' => $promo->code ?? null,
                    'active' => isset($promo->active) ? (bool) $promo->active : null,
                ] : ($promoId ? ['id' => $promoId] : null),
            ];
        }
    } catch (\Throwable $e) {
        $discountSummary = null;
    }

    return [
        'subscription' => [
            'id' => $sub->id,
            'status' => $sub->status ?? null,
            'created' => $sub->created ?? null,
            'collection_method' => $sub->collection_method ?? null,

            'current_period_start' => $sub->current_period_start ?? null,
            'current_period_end' => $sub->current_period_end ?? null,

            'trial_start' => $sub->trial_start ?? null,
            'trial_end' => $sub->trial_end ?? null,

            'cancel_at_period_end' => (bool) ($sub->cancel_at_period_end ?? false),

            'customer_id' => $customerId,
            'latest_invoice_id' => $latestInvoiceId,

            'latest_invoice_amount_paid' => is_object($sub->latest_invoice ?? null) ? ($sub->latest_invoice->amount_paid ?? null) : null,
            'latest_invoice_amount_due'  => is_object($sub->latest_invoice ?? null) ? ($sub->latest_invoice->amount_due ?? null) : null,
        ],

        'items' => $items,
        'payment_method' => $pmSummary,
        'upcoming_invoice' => $upcomingSummary,
        'invoices' => $invoicesSummary,
        'discount' => $discountSummary,
    ];
}










}