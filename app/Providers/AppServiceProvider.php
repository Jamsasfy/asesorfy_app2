<?php

namespace App\Providers;

use App\Policies\ClientePolicy;
use App\Observers\ClienteSuscripcionObserver;
use App\Observers\ClienteObserver;
use App\Models\Cliente;
use App\Models\ClienteSuscripcion;
use Illuminate\Support\Facades\Schema;
use App\Models\Venta;                 // <-- Añadir
use App\Observers\VentaObserver;      // <-- Añadir
use App\Models\Proyecto;              // <-- Añadir
use App\Observers\ProyectoObserver;   // <-- Añadir
use App\Models\Lead;
use App\Observers\LeadObserver;
use App\Models\Servicio;
use App\Observers\ServicioObserver;
use App\Models\ChatMensaje;
use App\Observers\ChatMensajeObserver;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{

    protected $policies = [
        Cliente::class => ClientePolicy::class,
    ];
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
      
        Schema::defaultStringLength(191);
        Venta::observe(VentaObserver::class);       // <-- Añadir esta línea
        Proyecto::observe(ProyectoObserver::class); // <-- Añadir esta línea
        ClienteSuscripcion::observe(ClienteSuscripcionObserver::class);
        Cliente::observe(ClienteObserver::class);
         Lead::observe(LeadObserver::class);
         Servicio::observe(ServicioObserver::class);
         ChatMensaje::observe(ChatMensajeObserver::class);
    }
}
