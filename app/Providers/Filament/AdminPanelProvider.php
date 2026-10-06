<?php

namespace App\Providers\Filament;

use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Reference panel provider for ProTor. If you already have one, merge the
 * lines marked "ProTor" into it rather than replacing the whole file.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->login()
            ->brandName('ProTor')                                  // ProTor
            ->brandLogo(fn () => view('filament.brand'))           // ProTor: engineering wordmark
            ->brandLogoHeight('2.25rem')
            ->favicon(asset('favicon.svg'))
            ->colors([
                'primary' => Color::hex('#7c3fc0'),   // orchid purple
                'info' => Color::hex('#7c3fc0'),
                'success' => Color::hex('#1f7a4d'),
                'warning' => Color::hex('#b7791f'),
                'danger' => Color::hex('#c42b35'),
                'peach' => Color::hex('#e0734a'),     // Pre-Design
                'pink' => Color::hex('#b5368f'),      // Post-Design
                'gray' => [                           // Apple-style neutrals
                    50 => '#fbfbfd', 100 => '#f5f5f7', 200 => '#e8e8ed', 300 => '#d2d2d7', 400 => '#a1a1a6',
                    500 => '#86868b', 600 => '#6e6e73', 700 => '#424245', 800 => '#2c2c2e', 900 => '#1c1c1e', 950 => '#050507',
                ],
            ])
            ->font('Inter')                                        // fallback; Apple devices render SF Pro (see --ds-font)
            ->defaultThemeMode(ThemeMode::Dark)
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth('17rem')
            ->maxContentWidth(Width::Full)
            ->databaseNotifications()                              // ProTor: task/leave notifications
            ->databaseNotificationsPolling('60s')                  // ProTor
            ->navigationGroups([                                   // ProTor
                'Projects',
                'People',
                'Settings',
            ])
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => view('filament.hooks.head'))
            ->renderHook(PanelsRenderHook::SIDEBAR_FOOTER, fn () => view('filament.hooks.sidebar-footer'))
            ->renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, fn () => view('filament.hooks.auth-backdrop'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->plugins([
                FilamentShieldPlugin::make(),                      // ProTor: roles & permissions UI
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
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
