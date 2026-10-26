<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Assets\Css;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * CY-Market admin/back-office (TOR §5.2 "Admin / Back-Office Dashboard -
 * Super Admin and Inventory Officer console for catalogue, orders, users,
 * settings and reports"). Access is further scoped per role via each
 * Resource/Page's canX() checks against Spatie roles (TOR §6.10 RBAC).
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path(config('cymarket.filament_path'))
            ->login()
            ->brandName('CY-Market Admin')
            ->brandLogo(fn (): View => view('filament.brand'))
            ->brandLogoHeight('2rem')
            ->favicon('/favicon.ico')
            ->font('Plus Jakarta Sans')
            // Brand green = the storefront's `brand` scale (resources/css/app.css),
            // so both surfaces share one identity. 600 is the button/link shade
            // (about 5:1 contrast on white).
            ->colors([
                'primary' => [
                    50 => '240, 253, 244',
                    100 => '220, 252, 231',
                    200 => '187, 247, 208',
                    300 => '134, 239, 172',
                    400 => '74, 222, 128',
                    500 => '22, 163, 74',
                    600 => '21, 128, 61',
                    700 => '22, 101, 52',
                    800 => '20, 83, 45',
                    900 => '11, 56, 30',
                    950 => '5, 35, 18',
                ],
                'danger' => Color::Rose,
                'warning' => Color::Amber,
                'info' => Color::Sky,
            ])
            ->maxContentWidth(MaxWidth::Full)
            ->sidebarCollapsibleOnDesktop()
            ->unsavedChangesAlerts()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchDebounce('500ms')
            // Order follows the shop day: act on orders and money first, keep
            // the catalogue right, promote, then report and administer. Names
            // must match each resource's/page's $navigationGroup.
            ->navigationGroups([
                NavigationGroup::make('Operations'),
                NavigationGroup::make('Catalogue'),
                NavigationGroup::make('Marketing'),
                NavigationGroup::make('Reports'),
                NavigationGroup::make('Administration')->collapsed(),
            ])
            ->navigationItems([
                NavigationItem::make('View shop')
                    ->url('/', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->group('Administration')
                    ->sort(99),
            ])
            ->assets([
                // Versioned by content hash: served as a plain static file, so
                // without a changing query string browsers/CDNs keep a stale copy
                // lacking newer classes (e.g. the .cy-report-* hub cards).
                // Raw <link> with a root-relative href on purpose: asset() at
                // provider boot runs before trustProxies marks the request
                // https, so behind Caddy it emitted http:// (mixed content),
                // and Filament rewrites non-absolute paths to css/app/<id>.css.
                Css::make('admin-glass-theme', 'css/filament/admin-glass.css')
                    ->html(fn (): string => '<link href="/css/filament/admin-glass.css?v='
                        .substr((string) md5_file(public_path('css/filament/admin-glass.css')), 0, 12)
                        .'" rel="stylesheet" data-navigate-track />'),
            ])
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): View => view('filament.topbar-theme-switcher'),
            )
            // Branded sign-in: a purpose line above the form, help below it.
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE,
                fn (): View => view('filament.login-intro'),
            )
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): View => view('filament.login-help'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.sidebar-init'),
            )
            // PWA - lets staff "install" the admin panel too, sharing the
            // storefront's manifest/service worker (see public/sw.js).
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => view('filament.pwa-head'),
            )
            // Staff bell - Super Admin/Inventory Officer get a NewOrderPlaced
            // alert the moment a customer checks out (see OrderService).
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
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
            ]);
    }
}
