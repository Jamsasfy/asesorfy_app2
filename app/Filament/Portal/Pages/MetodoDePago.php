<?php

namespace App\Filament\Portal\Pages;

use App\Models\Cliente;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class MetodoDePago extends Page
{
    protected string $view = 'filament.portal.pages.metodo-de-pago';

    // ✅ Tipados Filament v4 (como tu LeadResource)
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';
    protected static string|\UnitEnum|null $navigationGroup = 'Facturación y pagos';
    protected static ?string $navigationLabel = 'Método de pago';
    protected static ?string $title = 'Método de pago';

    // Datos para la vista
    public ?Cliente $cliente = null;
    public ?int $cliente_id = null;

    /** @var array{type:?string,label:string,last4:?string,brand:?string,source:?string} */
    public array $paymentMethod = [
        'type'   => null,
        'label'  => 'Sin método configurado',
        'last4'  => null,
        'brand'  => null,
        'source' => null,
    ];

    public ?string $error = null;
    public int $debugClicks = 0;

    public function mount(): void
    {
        // 1) Priorizamos el cliente activo de la sesión
        $this->cliente_id = request()->integer('cliente_id') ?: session('cliente_activo_id');

        // 2) Cargamos cliente y lo fijamos
        $this->loadClienteFromPortalContext();
        $this->cliente_id = $this->cliente?->id;

        if ($this->cliente_id) {
            // Sincronizamos por si acaso
            if (!session()->has('cliente_activo_id')) {
                session()->put('cliente_activo_id', $this->cliente_id);
            }
        }

        // 3) Resolvemos método de pago
        $this->resolvePaymentMethod();
    }

    /**
     * ✅ Selecciona el cliente "del portal" (hoy: el primero del usuario; mañana: el seleccionado en el selector).
     */
    protected function loadClienteFromPortalContext(): void
    {
        $user = auth()->user();

        if (! $user) {
            $this->cliente = null;
            $this->cliente_id = null;
            return;
        }

        // Si viene cliente_id, intentamos usar ese (si pertenece al usuario)
        if ($this->cliente_id) {
            $c = $user->clientes()->where('clientes.id', $this->cliente_id)->first();
            if ($c) {
                $this->cliente = $c;
                return;
            }
        }

        // Fallback: primer cliente del usuario
        $this->cliente = $user->clientes()->first();
    }

    /**
     * ✅ Importante: NO re-selecciona cliente (para que no "salte" a otro).
     */
    public function refreshMetodoPago(): void
    {
        $this->debugClicks++;

        try {
            if ($this->cliente_id) {
                $this->cliente = Cliente::query()->whereKey($this->cliente_id)->first();
            }

            if ($this->cliente) {
                $this->cliente->refresh();
            }

            $this->resolvePaymentMethod();

            // fuerza refresco de la vista Livewire
            $this->dispatch('$refresh');
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            Log::error('MetodoDePago refreshMetodoPago error', [
                'cliente_id' => $this->cliente_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function resolvePaymentMethod(): void
    {
        $this->error = null;

        if (! $this->cliente) {
            $this->paymentMethod = [
                'type'   => null,
                'label'  => 'Sin método configurado',
                'last4'  => null,
                'brand'  => null,
                'source' => null,
            ];
            return;
        }

        try {
            // Usa tu accessor en Cliente:
            // protected function stripeMetodoPago(): Attribute { get: fn () => StripePaymentMethodResolver::resolve($this) }
            $pm = $this->cliente->stripe_metodo_pago;

            $pm = is_array($pm) ? $pm : [];

            $this->paymentMethod = array_merge([
                'type'   => null,
                'label'  => 'Sin método configurado',
                'last4'  => null,
                'brand'  => null,
                'source' => null,
            ], $pm);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            Log::error('MetodoDePago resolvePaymentMethod error', [
                'cliente_id' => $this->cliente->id,
                'error' => $e->getMessage(),
            ]);

            $this->paymentMethod = [
                'type'   => null,
                'label'  => 'Sin método configurado',
                'last4'  => null,
                'brand'  => null,
                'source' => null,
            ];
        }
    }

    /**
     * URL del portal de Stripe (tu controlador invokable)
     */
    public function getBillingPortalUrl(): string
    {
        // OJO: tu ruta está nombrada como portal.stripe.billing-portal
        return route('portal.stripe.billing-portal');
    }
}
