<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashierChip\CustomerPortal;

use AIArmada\FilamentCashierChip\CustomerPortal\Pages\BillingDashboard;
use AIArmada\FilamentCashierChip\CustomerPortal\Pages\Invoices;
use AIArmada\FilamentCashierChip\CustomerPortal\Pages\PaymentMethods;
use AIArmada\FilamentCashierChip\CustomerPortal\Pages\Subscriptions;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class BillingPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panelId = config('filament-cashier-chip.billing.panel_id', 'billing');
        $panelPath = config('filament-cashier-chip.billing.path', 'billing');
        $brandName = config('filament-cashier-chip.billing.brand_name', 'Billing Portal');

        $panel = $panel
            ->id($panelId)
            ->path($panelPath)
            ->brandName($brandName)
            ->colors([
                'primary' => config('filament-cashier-chip.billing.primary_color', '#6366f1'),
            ])
            ->pages([
                BillingDashboard::class,
                Subscriptions::class,
                PaymentMethods::class,
                Invoices::class,
            ])
            ->middleware($this->getMiddleware())
            ->authMiddleware($this->getAuthMiddleware());

        if ((bool) config('filament-cashier-chip.billing.login_enabled', true)) {
            $panel->login();
        }

        $guard = config('filament-cashier-chip.billing.auth_guard', 'web');
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
            commerce_csrf_middleware(),
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

        $allowedRoles = (array) config('filament-cashier-chip.billing.allowed_roles', []);
        if (! empty($allowedRoles)) {
            $middleware[] = 'role:' . implode('|', $allowedRoles);
        }

        return $middleware;
    }
}
