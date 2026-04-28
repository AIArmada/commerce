<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart\Pages;

use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Widgets\AbandonedCartsWidget;
use AIArmada\FilamentCart\Widgets\CartStatsOverviewWidget;
use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

/**
 * Cart analytics dashboard page.
 *
 * Provides an overview of cart activity and abandonment.
 */
class CartDashboard extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string | UnitEnum | null $navigationGroup = 'Commerce';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament-cart::pages.cart-dashboard';

    protected static ?string $title = 'Cart Analytics';

    protected static ?string $slug = 'cart-dashboard';

    public static function getNavigationLabel(): string
    {
        return 'Cart Analytics';
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::getAbandonedCartCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $abandonedCount = self::getAbandonedCartCount();

        if ($abandonedCount >= 10) {
            return 'warning';
        }

        if ($abandonedCount >= 5) {
            return 'info';
        }

        return 'success';
    }

    protected function getHeaderWidgets(): array
    {
        $widgets = [];

        if (config('filament-cart.widgets.stats_overview', true)) {
            $widgets[] = CartStatsOverviewWidget::class;
        }

        return $widgets;
    }

    protected function getFooterWidgets(): array
    {
        $widgets = [];

        if (config('filament-cart.widgets.abandoned_carts', true) && config('filament-cart.features.abandonment_tracking', true)) {
            $widgets[] = AbandonedCartsWidget::class;
        }

        return $widgets;
    }

    private static function getAbandonedCartCount(): int
    {
        if (! class_exists(Cart::class)) {
            return 0;
        }

        return Cart::query()->forOwner(includeGlobal: Cart::includeGlobalRecords())
            ->whereNotNull('checkout_abandoned_at')
            ->where('checkout_abandoned_at', '>=', now()->subDay())
            ->count();
    }
}
