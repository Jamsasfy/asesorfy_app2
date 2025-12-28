<?php

namespace App\Observers;

use Exception;
use App\Models\Servicio;
use App\Enums\ServicioTipoEnum;
use Stripe\Stripe;
use Stripe\Product;
use Stripe\Price;
use Illuminate\Support\Facades\Log;

class ServicioObserver
{
    /**
     * Se ejecuta automáticamente al CREAR un servicio.
     */
    public function created(Servicio $servicio): void
    {
        $this->syncWithStripe($servicio);
    }

    /**
     * Se ejecuta automáticamente al ACTUALIZAR un servicio.
     * (Opcional: solo si quieres que cambios de nombre/precio se reflejen en Stripe)
     */
    public function updated(Servicio $servicio): void
    {
        // Solo sincronizamos si ha cambiado algo relevante para evitar llamadas innecesarias
        if ($servicio->isDirty(['nombre', 'descripcion', 'precio_base', 'tipo'])) {
            $this->syncWithStripe($servicio);
        }
    }

    /**
     * Lógica centralizada de sincronización (Misma lógica que tu comando).
     */
    private function syncWithStripe(Servicio $servicio): void
    {
        // Evitamos bucles infinitos si guardamos el ID de Stripe
        if ($servicio->isDirty(['stripe_product_id', 'stripe_price_id'])) {
            return;
        }

        Stripe::setApiKey(config('services.stripe.secret'));
        if (app()->isLocal()) Stripe::setVerifySslCerts(false);

        try {
            // A) CREAR PRODUCTO (Si falta)
            if (!$servicio->stripe_product_id) {
                $product = Product::create([
                    'name'        => $servicio->nombre,
                    'description' => $servicio->descripcion ?? $servicio->nombre,
                ]);
                
                // Guardamos directos en la BBDD sin disparar eventos de nuevo
                $servicio->stripe_product_id = $product->id;
                $servicio->saveQuietly(); 
                
                Log::info("✅ Producto Stripe creado para servicio: {$servicio->nombre}");
            }

            // B) CREAR PRECIO BASE (Sin impuestos)
            // Solo si tiene precio base definido y no tiene ID de precio aún
            if (!$servicio->stripe_price_id && is_numeric($servicio->precio_base) && $servicio->precio_base >= 0) {
                
                $priceData = [
                    'product'     => $servicio->stripe_product_id,
                    'unit_amount' => (int) round($servicio->precio_base * 100), // Precio BASE
                    'currency'    => 'eur',
                ];

                // Comprobación de tipo (recurrente vs único)
                $esRecurrente = false;
                if ($servicio->tipo instanceof ServicioTipoEnum) {
                    $esRecurrente = $servicio->tipo === ServicioTipoEnum::RECURRENTE;
                } else {
                    $val = is_string($servicio->tipo) ? $servicio->tipo : $servicio->tipo->value;
                    $esRecurrente = $val === 'recurrente';
                }

                if ($esRecurrente) {
                    $priceData['recurring'] = ['interval' => 'month'];
                }

                $price = Price::create($priceData);
                
                $servicio->stripe_price_id = $price->id;
                $servicio->saveQuietly();

                Log::info("✅ Precio Base Stripe creado: {$servicio->precio_base}€");
            }

        } catch (Exception $e) {
            Log::error("❌ Error sync Stripe en Observer (ID {$servicio->id}): " . $e->getMessage());
        }
    }
}