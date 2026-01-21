<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use DiogoGPinto\AuthUIEnhancer\AuthUIEnhancerPlugin;
use Filament\View\PanelsRenderHook;


class PortalPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('portal')
            ->path('portal')
            ->viteTheme('resources/css/filament/portal/theme.css')
            ->colors([
                'primary' => '#41c0e9',
            ])
            ->login()
            ->passwordReset(\App\Filament\Portal\Pages\Auth\RequestPasswordReset::class)
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
             ->font('Varela Round')
              ->favicon(asset('images/favicon.png'))
            ->discoverResources(in: app_path('Filament/Portal/Resources'), for: 'App\Filament\Portal\Resources')
            ->discoverPages(in: app_path('Filament/Portal/Pages'), for: 'App\Filament\Portal\Pages')
            ->brandLogo(asset('images/logo.png'))
            ->darkModeBrandLogo(asset('images/logo_dark.png'))
            ->brandLogoHeight('4rem')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Portal/Widgets'), for: 'App\Filament\Portal\Widgets')
            ->databaseNotifications()
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
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
            ->plugins([
                AuthUIEnhancerPlugin::make()
                // Form panel (derecha) + estética limpia
                        ->formPanelPosition('right')
                        ->formPanelWidth('42%')
                     //   ->formPanelBackgroundColor(Color::hex('#ffffff'))
                     ->formPanelBackgroundColor(Color::Sky, '100')
                        // Empty panel (izquierda): azul corporativo + imagen
                        ->emptyPanelBackgroundColor(Color::hex('#41c0e9'))
                        ->emptyPanelBackgroundImageUrl(asset('images/portal/login.png'))
                        ->emptyPanelBackgroundImageOpacity('68%'),
            ])
            
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
