<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use App\Models\Servicio;
use App\Enums\ServicioTipoEnum;
use Stripe\Stripe;
use Stripe\Product;
use Stripe\Price;

class StripeSyncServices extends Command
{
    protected $signature = 'stripe:sync-services';
    protected $description = 'Crea productos y precios en Stripe para los servicios que no los tengan.';

    public function handle()
    {
        // 1. Configurar Stripe
        Stripe::setApiKey(env('STRIPE_SECRET'));

        // 🚑 PARCHE LOCAL: Desactivar verificación SSL para que funcione en WAMP
        if (app()->isLocal()) {
            Stripe::setVerifySslCerts(false);
        }

        $servicios = Servicio::whereNull('stripe_product_id')
            ->orWhereNull('stripe_price_id')
            ->get();

        if ($servicios->isEmpty()) {
            $this->info('Todos los servicios ya están sincronizados con Stripe.');
            return;
        }

        $this->info("Sincronizando {$servicios->count()} servicios con Stripe...");
        $bar = $this->output->createProgressBar($servicios->count());
foreach ($servicios as $servicio) {
            try {
                // A) CREAR PRODUCTO (Si falta)
                if (!$servicio->stripe_product_id) {
                    $product = Product::create([
                        'name' => $servicio->nombre,
                        'description' => $servicio->descripcion,
                    ]);
                    $servicio->stripe_product_id = $product->id;
                }

                // B) CREAR PRECIO ESTÁNDAR
                // CAMBIO AQUÍ: Permitimos 0 con >= 0
                // IMPORTANTE: Aunque sea editable, creamos el precio base como referencia.
                if (!$servicio->stripe_price_id && is_numeric($servicio->precio_base) && $servicio->precio_base >= 0) {
                    
                    $priceData = [
                        'product' => $servicio->stripe_product_id,
                        'unit_amount' => (int) round($servicio->precio_base * 100),
                        'currency' => 'eur',
                    ];

                    if ($servicio->tipo === ServicioTipoEnum::RECURRENTE) {
                        $priceData['recurring'] = ['interval' => 'month'];
                    }

                    $price = Price::create($priceData);
                    $servicio->stripe_price_id = $price->id;
                }

                $servicio->saveQuietly();
                $bar->advance();

            } catch (Exception $e) {
                $this->error("Error en servicio ID {$servicio->id}: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine();
        $this->info('¡Sincronización completada! 🚀');
    }
}