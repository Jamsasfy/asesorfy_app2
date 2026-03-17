<?php

namespace App\Providers\Filament;

use App\Filament\Portal\Pages\Auth\Login; // 👈 AÑADIR
use App\Http\Middleware\SetClienteActivoMiddleware; // 👈 AÑADIR
use App\Filament\Portal\Pages\Auth\RequestPasswordReset;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use App\Http\Middleware\VerificarAccesoPortal;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Filament\Portal\Widgets\SelectorEmpresaWidget;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;


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
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->login()
            ->loginRouteSlug('login')
            ->homeUrl('/portal')  // 👈 AÑADIR ESTA LÍNEA
            ->authPasswordBroker('portal')
            ->passwordReset(RequestPasswordReset::class)
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->font('Varela Round')
            ->favicon(asset('images/favicon.png'))
            ->discoverResources(in: app_path('Filament/Portal/Resources'), for: 'App\Filament\Portal\Resources')
            ->discoverPages(in: app_path('Filament/Portal/Pages'), for: 'App\Filament\Portal\Pages')
            ->brandLogo(asset('images/logo.png'))
            ->darkModeBrandLogo(asset('images/logo_dark.png'))
            ->brandLogoHeight('4rem')
            ->discoverWidgets(in: app_path('Filament/Portal/Widgets'), for: 'App\Filament\Portal\Widgets')
            ->widgets([
                SelectorEmpresaWidget::class, // 👈 AÑADIR
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])

            ->renderHook(
    PanelsRenderHook::SIDEBAR_NAV_START,
    fn () => Blade::render('
        @php
            $user = auth()->user();
            $clientes = $user?->clientes()->get() ?? collect();
        @endphp
        @if($clientes->count() > 1)
            @php $clienteActivo = $clientes->find(session("cliente_activo_id")); @endphp
            @if($clienteActivo)
            <div x-data="{ open: false }" class="p-3 mx-3 mb-2 rounded-xl border border-primary-200 dark:border-primary-800 bg-primary-50 dark:bg-primary-950">
                <div class="relative">
                    <button @click="open = !open"
                        class="w-full flex items-center gap-2 rounded-xl px-3 py-2.5 text-left hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
                        <div class="flex items-center justify-center w-8 h-8 rounded-full bg-primary-500 text-white font-bold text-sm shrink-0">
                            {{ strtoupper(substr($clienteActivo->razon_social ?? $clienteActivo->nombre ?? "?", 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-medium text-primary-600 dark:text-primary-400 uppercase tracking-wide">Empresa activa</div>
                            <div class="text-xs font-semibold text-gray-900 dark:text-white truncate">
                                {{ $clienteActivo->razon_social ?? $clienteActivo->nombre }}
                            </div>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 text-gray-400 shrink-0 transition-transform" :class="open ? \'-rotate-180\' : \'\'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="open" x-transition @click.outside="open = false"
                         class="absolute bottom-full left-0 right-0 mb-1 z-50 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg py-1">
                        @foreach($clientes as $c)
                            <a href="{{ url(\'/portal/seleccionar-empresa?cambiar=\' . $c->id) }}"
                               class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors {{ $c->id === session(\'cliente_activo_id\') ? \'bg-primary-50 dark:bg-primary-950\' : \'\' }}">
                                <div class="flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold shrink-0 {{ $c->id === session(\'cliente_activo_id\') ? \'bg-primary-500 text-white\' : \'bg-gray-200 dark:bg-gray-600 text-gray-600\' }}">
                                    {{ strtoupper(substr($c->razon_social ?? $c->nombre ?? "?", 0, 1)) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs font-medium text-gray-900 dark:text-white truncate">
                                        {{ $c->razon_social ?? $c->nombre . " " . $c->apellidos }}
                                    </div>
                                    <div class="text-xs text-gray-500">{{ $c->dni_cif }}</div>
                                </div>
                                @if($c->id === session(\'cliente_activo_id\'))
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-primary-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif
        @endif
    ')
)


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
                SetClienteActivoMiddleware::class, // 👈 AÑADIR al final
               //VerificarAccesoPortal::class,
            ])
            ->plugins([
                
            ])
            ->authMiddleware([
                Authenticate::class,
                    \App\Http\Middleware\VerificarAccesoPortal::class, // 👈 AÑADIR AQUÍ
            ]);
    }
}