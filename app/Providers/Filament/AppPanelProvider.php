<?php

namespace App\Providers\Filament;

use App\Filament\Widgets;
use App\Http\Middleware\SetTenantFromUser;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\MaxWidth;
use Filament\View\PanelsRenderHook;
use Filament\Widgets as FilamentWidgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('panel')
            ->login()
            ->colors([
                'primary' => Color::Amber,
                'gray'    => Color::Zinc,
            ])
            ->font('Geist')
            // Marca dinámica: muestra el nombre y logo del taller actual (con
            // fallback a Motor1000 si el taller no cargó logo). Se resuelve por
            // request, cuando el middleware ya fijó el tenant.
            ->brandName(fn (): string => \App\Support\CurrentTenant::get()?->name ?? 'Motor1000')
            ->brandLogo(fn (): string => \App\Helpers\TenantBranding::logoPath() ?? asset('images/motor1000-mark.svg'))
            ->brandLogoHeight('1.9rem')
            // Falla #1: el tema custom (theme-overrides.blade.php) está diseñado para
            // superficies claras. Con el modo oscuro activo, Filament emitía colores de
            // texto/etiquetas claros sobre esos fondos claros => texto ilegible y
            // desplegables con fondo negro. Forzamos modo claro para que todo sea coherente.
            ->darkMode(false)
            ->maxContentWidth(MaxWidth::Full)
            ->sidebarCollapsibleOnDesktop()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->navigationGroups([
                NavigationGroup::make(__('Taller'))
                    ->collapsed(false),
                NavigationGroup::make(__('CRM'))
                    ->collapsed(false),
                NavigationGroup::make(__('Financiero'))
                    ->collapsed(false),
                NavigationGroup::make(__('Configuraciones'))
                    ->collapsed(true),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                FilamentWidgets\AccountWidget::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.theme-overrides')->render()
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): string => view('filament.topbar-module')->render()
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.theme-scripts')->render()
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
                SetTenantFromUser::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
