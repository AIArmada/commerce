<?php

declare(strict_types=1);

namespace AIArmada\FilamentCashier\CustomerPortal;

use AIArmada\FilamentCashier\CustomerPortal\Pages\BillingOverview;
use AIArmada\FilamentCashier\CustomerPortal\Pages\ManagePaymentMethods;
use AIArmada\FilamentCashier\CustomerPortal\Pages\ManageSubscriptions;
use AIArmada\FilamentCashier\CustomerPortal\Pages\ViewInvoices;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

final class BillingPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $config = config('filament-cashier.billing_portal', []);

        return $panel
            ->id($config['panel_id'] ?? 'billing')
            ->path($config['path'] ?? 'billing')
            ->brandName($config['brand_name'] ?? 'Billing Portal')
            ->colors([
                'primary' => $this->parsePrimaryColor($config['primary_color'] ?? '#6366f1'),
            ])
            ->login($config['login_enabled'] ?? true)
            ->authGuard($config['auth_guard'] ?? 'web')
            ->pages([
                BillingOverview::class,
                ManageSubscriptions::class,
                ManagePaymentMethods::class,
                ViewInvoices::class,
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

    /**
     * @return array<int, int>|string
     */
    protected function parsePrimaryColor(string $color): array | string
    {
        // If it's a hex color, try to match to a preset
        if (str_starts_with($color, '#')) {
            return match (mb_strtolower($color)) {
                '#6366f1' => Color::Indigo,
                '#3b82f6' => Color::Blue,
                '#10b981' => Color::Emerald,
                '#f59e0b' => Color::Amber,
                '#ef4444' => Color::Red,
                '#8b5cf6' => Color::Violet,
                default => Color::Indigo,
            };
        }

        return Color::Indigo;
    }
}
