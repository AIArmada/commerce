<?php

declare(strict_types=1);

namespace AIArmada\FilamentAffiliates;

use AIArmada\FilamentAffiliates\Pages\Portal\PortalConversions;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalDashboard;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalLinks;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalPayouts;
use AIArmada\FilamentAffiliates\Pages\Portal\PortalRegistration;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Affiliate Portal Panel Provider for affiliate self-service portal.
 *
 * This panel provides affiliates access to manage their:
 * - Dashboard with stats and summary
 * - Affiliate links management
 * - Conversion history
 * - Payout history
 *
 * To use this panel, register it in your application:
 *
 * ```php
 * // In config/app.php providers array or AppServiceProvider
 * AIArmada\FilamentAffiliates\AffiliatePanelProvider::class,
 * ```
 */
class AffiliatePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panelId = config('filament-affiliates.portal.panel_id', 'affiliate');
        $panelPath = config('filament-affiliates.portal.path', 'affiliate');
        $brandName = config('filament-affiliates.portal.brand_name', 'Affiliate Portal');

        $panel = $panel
            ->id($panelId)
            ->path($panelPath)
            ->brandName($brandName)
            ->colors([
                'primary' => config('filament-affiliates.portal.primary_color', '#6366f1'),
            ])
            ->pages($this->getPages())
            ->middleware($this->getMiddleware())
            ->authMiddleware($this->getAuthMiddleware());

        if ((bool) config('filament-affiliates.portal.login_enabled', true)) {
            $panel->login();
        }

        if ((bool) config('filament-affiliates.portal.registration_enabled', true)) {
            $panel->registration(PortalRegistration::class);
        }

        $guard = config('filament-affiliates.portal.auth_guard', 'web');
        if ($guard) {
            $panel->authGuard($guard);
        }

        return $panel;
    }

    /**
     * @return array<class-string>
     */
    protected function getPages(): array
    {
        $pages = [];
        $features = config('filament-affiliates.portal.features', []);

        if ($features['dashboard'] ?? true) {
            $pages[] = PortalDashboard::class;
        }

        if ($features['links'] ?? true) {
            $pages[] = PortalLinks::class;
        }

        if ($features['conversions'] ?? true) {
            $pages[] = PortalConversions::class;
        }

        if ($features['payouts'] ?? true) {
            $pages[] = PortalPayouts::class;
        }

        return $pages;
    }

    /**
     * @return array<class-string>
     */
    protected function getMiddleware(): array
    {
        return [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            VerifyCsrfToken::class,
            SubstituteBindings::class,
            DisableBladeIconComponents::class,
            DispatchServingFilamentEvent::class,
        ];
    }

    /**
     * @return array<class-string>
     */
    protected function getAuthMiddleware(): array
    {
        return [
            Authenticate::class,
        ];
    }
}
