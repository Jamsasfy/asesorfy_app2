<?php

namespace App\Providers\Filament;

use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Support\Enums\MaxWidth;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\ClienteResource;
use App\Filament\Resources\ClienteResource\Pages\MisClientes;
use App\Filament\Resources\VentaResource\Widgets\AnnualSalesChart;
use App\Filament\Widgets\ClientesPorMesChart;

use App\Filament\Resources\LeadResource;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
        ->spa()
        ->default()
        ->id('admin')
        ->path('admin')
        ->sidebarCollapsibleOnDesktop()
        ->login(Login::class)
        ->colors([
            'primary' => '#41c0e9',
        ])
      //  ->theme(asset('css/filament/admin/theme.css'))
              ->viteTheme('resources/css/filament/admin/theme.css')

        ->font('Varela Round')
        ->breadcrumbs(false)
         ->maxContentWidth('full')
        ->brandName('Plataforma AsesorFy')
        ->brandLogo(asset('images/logo.png'))
        ->darkModeBrandLogo(asset('images/logo_dark.png'))
        ->brandLogoHeight('4rem')
        ->favicon(asset('images/favicon.png'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
                           

            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
               // AnnualSalesChart::class,
                ClientesPorMesChart::class, 
            ])

          ->databaseNotifications() // <-- AÑADE ESTA LÍNEA
            ->databaseNotificationsPolling('30s') // <-- (Opcional) Actualiza notificaciones cada 30s   


            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ]);
    }

    public function boot(): void
{
    // ... (código que ya tuvieras)

    FilamentAsset::register([
        Css::make('custom-stylesheet', asset('css/custom.css')),
    ]);
}


}
