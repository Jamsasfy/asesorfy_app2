<?php

namespace App\Filament\Portal\Pages;

use App\Models\Cliente;
use App\Models\ClienteSuscripcion;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Stripe\Stripe;
use Stripe\StripeClient;

class Suscripcion extends Page
{
    protected static ?string $title = 'Suscripción';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string|\UnitEnum|null $navigationGroup = 'Facturación y pagos';

    protected static ?string $navigationLabel = 'Suscripción';

    // Portal sin Shield/Policies
    protected static bool $shouldSkipAuthorization = true;

    protected string $view = 'filament.portal.pages.suscripcion';

    public ?Cliente $cliente = null;
    public ?ClienteSuscripcion $suscripcion = null;

    /**
     * Snapshot Stripe (opcional). Si falla, seguimos con datos locales.
     */
    public array $stripeSnapshot = [];
    public ?string $stripeError = null;

    public function mount(): void
{
    /** @var Cliente|null $cliente */
    $cliente = auth()->user()?->clientes()->first();

    $this->cliente = $cliente;

    if (! $this->cliente) {
        // Nada que cargar
        $this->suscripciones = collect();
        $this->suscripcion = null;
        return;
    }

    /**
     * ✅ 1) LISTADO COMPLETO (para el portal)
     * Aquí cargamos TODAS las suscripciones recurrentes del cliente.
     * (No solo la principal)
     */
    $this->suscripciones = $this->cliente->suscripciones()
        ->with('servicio')
        ->get();

    /**
     * ✅ 2) Principal (tu accessor “source of truth”)
     * La seguimos manteniendo porque tu snapshot Stripe está montado sobre UNA subscription_id.
     */
    $this->suscripcion = $this->cliente->tarifa_principal_activa;

    // Si no hay principal, no rompemos nada: el blade seguirá pudiendo pintar el listado ($suscripciones)
    if (! $this->suscripcion) {
        return;
    }

    // Si no hay Stripe IDs, no rompemos nada: seguimos con datos locales
    if (blank($this->cliente->stripe_customer_id) || blank($this->suscripcion->stripe_subscription_id)) {
        return;
    }

    try {
        $stripe = $this->makeStripeClient();

        $sub = $stripe->subscriptions->retrieve(
            $this->suscripcion->stripe_subscription_id,
            []
        );

        $upcoming = null;

        try {
            $upcoming = $stripe->invoices->upcoming([
                'customer'      => $this->cliente->stripe_customer_id,
                'subscription'  => $this->suscripcion->stripe_subscription_id,
            ]);
        } catch (\Throwable $e) {
            $upcoming = null;
        }

        $this->stripeSnapshot = [
            'subscription' => [
                'status' => (string) ($sub->status ?? ''),
                'current_period_end' => (int) ($sub->current_period_end ?? 0),
                'current_period_start' => (int) ($sub->current_period_start ?? 0),
                'cancel_at_period_end' => (bool) ($sub->cancel_at_period_end ?? false),
            ],
            'upcoming_invoice' => $upcoming ? [
                'total' => (int) ($upcoming->total ?? 0),
                'next_payment_attempt' => (int) ($upcoming->next_payment_attempt ?? 0),
                'hosted_invoice_url' => (string) ($upcoming->hosted_invoice_url ?? ''),
            ] : [],
        ];
    } catch (\Throwable $e) {
        $this->stripeError = $e->getMessage();
        // No rompemos la página: seguiremos con datos locales
    }
}


    protected function makeStripeClient(): StripeClient
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        if (app()->isLocal()) {
            // igual que en tus controllers antiguos
            Stripe::setVerifySslCerts(false);
        }

        return new StripeClient(config('services.stripe.secret'));
    }

    public function getServicioLabel(): string
    {
        if (! $this->suscripcion) return '—';

        $acronimo = $this->suscripcion->servicio?->acronimo ?? null;
        $nombre = $this->suscripcion->nombre_final ?? $this->suscripcion->servicio?->nombre ?? 'Servicio';

        return $acronimo ? ($acronimo . ' · ' . $nombre) : $nombre;
    }

    public function getEstadoLabel(): string
    {
        if (! $this->suscripcion) return '—';

       $estado = $this->suscripcion->estado;

$local = match (true) {
    is_object($estado) && method_exists($estado, 'getLabel') => $estado->getLabel(),
    is_object($estado) && property_exists($estado, 'value')   => (string) $estado->value,
    default                                                   => '—',
};


        $stripe = (string) data_get($this->stripeSnapshot, 'subscription.status', '');
        if ($stripe !== '') {
            return $local . ' · Stripe: ' . strtoupper($stripe);
        }

        return $local;
    }

    public function getProximoCobroLabel(): string
    {
        // Prioridad 1: Stripe upcoming next_payment_attempt
        $ts = data_get($this->stripeSnapshot, 'upcoming_invoice.next_payment_attempt');
        if (is_numeric($ts) && (int) $ts > 0) {
            return Carbon::createFromTimestamp((int) $ts)
                ->timezone(config('app.timezone', 'Europe/Madrid'))
                ->format('d/m/Y');
        }

        // Prioridad 2: Stripe current_period_end
        $ts = data_get($this->stripeSnapshot, 'subscription.current_period_end');
        if (is_numeric($ts) && (int) $ts > 0) {
            return Carbon::createFromTimestamp((int) $ts)
                ->timezone(config('app.timezone', 'Europe/Madrid'))
                ->format('d/m/Y');
        }

        // Fallback: campo local proxima_fecha_facturacion
        $d = $this->suscripcion?->proxima_fecha_facturacion;
        if ($d) {
            return Carbon::parse($d)->format('d/m/Y');
        }

        return '—';
    }

    public function getImporteLabel(): string
    {
        // Prioridad 1: Stripe upcoming invoice total (cents)
        $total = data_get($this->stripeSnapshot, 'upcoming_invoice.total');
        if (is_numeric($total) && (int) $total > 0) {
            $eur = ((int) $total) / 100;
            return number_format($eur, 2, ',', '.') . ' €';
        }

        // Fallback: precio acordado local
        $precio = $this->suscripcion?->precio_acordado;
        if ($precio !== null) {
            return number_format((float) $precio, 2, ',', '.') . ' €';
        }

        return '—';
    }

    public function getPeriodicidadLabel(): string
    {
        $ciclo = $this->suscripcion?->ciclo_facturacion;
        if (! $ciclo) return '—';

        // CicloFacturacionEnum (cast) -> value
        $val = $ciclo->value ?? (string) $ciclo;

        return match ($val) {
            'mensual', 'month', 'monthly' => 'Mensual',
            'trimestral', 'quarter', 'quarterly' => 'Trimestral',
            'anual', 'year', 'yearly' => 'Anual',
            default => ucfirst((string) $val),
        };
    }
}
