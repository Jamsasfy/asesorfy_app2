<?php

namespace App\Providers;

use App\Models\Cliente;
use App\Models\ClienteSuscripcion;
use Illuminate\Support\Facades\Schema;
use App\Models\Venta;                 // <-- Añadir
use App\Observers\VentaObserver;      // <-- Añadir
use App\Models\Proyecto;              // <-- Añadir
use App\Observers\ProyectoObserver;   // <-- Añadir
use App\Models\Lead;
use App\Observers\LeadObserver;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{

    protected $policies = [
        \App\Models\Cliente::class => \App\Policies\ClientePolicy::class,
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
        ClienteSuscripcion::observe(\App\Observers\ClienteSuscripcionObserver::class);
        Cliente::observe(\App\Observers\ClienteObserver::class);
         Lead::observe(LeadObserver::class);
    }
}
