<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\InstallsChart;
use App\Filament\Widgets\OpenIssues;
use App\Filament\Widgets\PlanMix;
use App\Filament\Widgets\PlatformStats;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Composa super admin (staff only). Sign-in with Google Workspace when ADMIN_GOOGLE_SSO is on;
 * password login stays available for the first Owner account. Every page and action is gated by
 * the role permissions in App\Support\AdminRoles.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path(config('composa.admin.path', 'admin'))
            ->domain(config('composa.admin.domain'))
            ->login()
            ->brandName('Composa admin')
            ->colors([
                'primary' => Color::hex('#2f6f4f'),
                'gray' => Color::Stone,
                'danger' => Color::hex('#b4532a'),
            ])
            ->navigationGroups(['Merchants', 'Catalog', 'Support', 'Website', 'Status page', 'Platform'])
            ->sidebarCollapsibleOnDesktop()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                PlatformStats::class,
                InstallsChart::class,
                PlanMix::class,
                OpenIssues::class,
            ])
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn () => config('composa.admin.google_sso') ? view('filament.google-sign-in') : '')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
