<?php

namespace App\Filament\Resources\ClienteSuscripcionResource\Pages;

use App\Filament\Resources\ClienteSuscripcionResource;
use App\Services\StripeSubscriptionService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Log;

class ViewClienteSuscripcion extends ViewRecord
{
    protected static string $resource = ClienteSuscripcionResource::class;

    public array $stripeSnapshot = [];
    public ?string $stripeError = null;

    public function mount($record): void
    {
        parent::mount($record);

        $this->loadStripeSnapshot();
    }

public function loadStripeSnapshot(): void
{
    $this->stripeError = null;
    $this->stripeSnapshot = [];

    $this->record->refresh();

    $subId = $this->record->stripe_subscription_id;

    if (! filled($subId)) {
        return;
    }

    try {
        $snap = \App\Services\StripeSubscriptionService::getStripeSubscriptionSnapshot($subId);

    Log::info('🧪 DISCOUNT DEBUG', [
        'discount' => data_get($snap, 'discount'),
        'subscription_discounts' => data_get($snap, 'subscription.discounts'),
        ]);



        // Normalizar
        if ($snap instanceof \Stripe\StripeObject) {
            $snap = $snap->toArray();
        }

        if (! is_array($snap)) {
            $snap = ['raw' => (string) $snap];
        }

        // ✅ ya viene “small payload” desde el Service
        $this->stripeSnapshot = $snap;

        // (opcional) debug rápido
        // \Log::info('🧪 STRIPE SNAPSHOT KEYS', [
        //     'suscripcion_local_id' => $this->record->id,
        //     'stripe_subscription_id' => $subId,
        //     'keys' => array_keys($this->stripeSnapshot),
        //     'upcoming_keys' => is_array(data_get($this->stripeSnapshot, 'upcoming_invoice')) ? array_keys($this->stripeSnapshot['upcoming_invoice']) : null,
        // ]);

    } catch (\Throwable $e) {
        $this->stripeError = $e->getMessage();

        \Log::warning('⚠️ Stripe snapshot error', [
            'suscripcion_id' => $this->record->id,
            'stripe_subscription_id' => $subId,
            'error' => $e->getMessage(),
        ]);
    }

    $this->record->refresh();
    $this->dispatch('$refresh');
}




    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshStripe')
                ->label('Actualizar desde Stripe')
                ->icon('heroicon-m-arrow-path')
                ->action(fn () => $this->loadStripeSnapshot()),

            Action::make('openStripe')
                ->label('Abrir Suscripción en Stripe')
                ->icon('heroicon-m-arrow-top-right-on-square')
                ->url(fn () => filled($this->record->stripe_subscription_id)
                    ? $this->stripeDashboardSubscriptionUrl($this->record->stripe_subscription_id)
                    : null)
                ->openUrlInNewTab()
                ->visible(fn () => filled($this->record->stripe_subscription_id)),
        ];
    }

    private function stripeDashboardSubscriptionUrl(string $subId): string
    {
        $isLive = str_starts_with((string) config('services.stripe.secret'), 'sk_live_');

        return $isLive
            ? "https://dashboard.stripe.com/subscriptions/{$subId}"
            : "https://dashboard.stripe.com/test/subscriptions/{$subId}";
    }
}
