<?php

declare(strict_types=1);

namespace AIArmada\FilamentChip;

use AIArmada\FilamentChip\Pages\Billing\BillingDashboard;
use AIArmada\FilamentChip\Pages\Billing\Invoices;
use AIArmada\FilamentChip\Pages\Billing\PaymentMethods;
use AIArmada\FilamentChip\Pages\Billing\Subscriptions;
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
 * Billing Panel Provider for customer self-service billing portal.
 *
 * This panel provides customers access to manage their:
 * - Subscriptions
 * - Payment methods
 * - Invoice history
 *
 * To use this panel, register it in your application:
 *
 * ```php
 * // In config/app.php providers array or AppServiceProvider
 * AIArmada\FilamentChip\BillingPanelProvider::class,
 * ```
 */
class BillingPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panelId = config('filament-chip.billing.panel_id', 'billing');
        $panelPath = config('filament-chip.billing.path', 'billing');
        $brandName = config('filament-chip.billing.brand_name', 'Billing Portal');

        $panel = $panel
            ->id($panelId)
            ->path($panelPath)
            ->brandName($brandName)
            ->colors([
                'primary' => config('filament-chip.billing.primary_color', '#6366f1'),
            ])
            ->pages([
                BillingDashboard::class,
                Subscriptions::class,
                PaymentMethods::class,
                Invoices::class,
            ])
            ->middleware($this->getMiddleware())
            ->authMiddleware($this->getAuthMiddleware());

        if ((bool) config('filament-chip.billing.login_enabled', true)) {
            $panel->login();
        }

        $guard = config('filament-chip.billing.auth_guard', 'web');
        if ($guard) {
            $panel->authGuard($guard);
        }

        return $panel;
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
        $middleware = [
            Authenticate::class,
        ];

        $allowedRoles = (array) config('filament-chip.billing.allowed_roles', []);
        if (! empty($allowedRoles)) {
            $middleware[] = 'role:' . implode('|', $allowedRoles);
        }

        return $middleware;
    }
}
