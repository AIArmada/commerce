<?php

declare(strict_types=1);

namespace AIArmada\FilamentCart;

use AIArmada\FilamentCart\Pages\CartDashboard;
use AIArmada\FilamentCart\Pages\LiveDashboardPage;
use AIArmada\FilamentCart\Resources\CartConditionResource;
use AIArmada\FilamentCart\Resources\CartItemResource;
use AIArmada\FilamentCart\Resources\CartResource;
use AIArmada\FilamentCart\Resources\ConditionResource;
use AIArmada\FilamentCart\Widgets\AbandonedCartsWidget;
use AIArmada\FilamentCart\Widgets\CartStatsWidget;
use AIArmada\FilamentCart\Widgets\LiveStatsWidget;
use AIArmada\FilamentCart\Widgets\RecentActivityWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentCartPlugin implements Plugin
{
    public static function make(): static
    {
        return app(self::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    public function getId(): string
    {
        return 'filament-cart';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources($this->getResources())
            ->pages($this->getPages())
            ->widgets($this->getWidgets());
    }

    public function boot(Panel $panel): void
    {
        //
    }

    /**
     * @return array<class-string>
     */
    private function getResources(): array
    {
        $resources = [
            CartResource::class,
            CartItemResource::class,
            CartConditionResource::class,
            ConditionResource::class,
        ];

        return $resources;
    }

    /**
     * @return array<class-string>
     */
    private function getPages(): array
    {
        $pages = [];

        if (config('filament-cart.features.dashboard', true)) {
            $pages[] = CartDashboard::class;
        }

        if (config('filament-cart.features.monitoring', true)) {
            $pages[] = LiveDashboardPage::class;
        }

        return $pages;
    }

    /**
     * @return array<class-string>
     */
    private function getWidgets(): array
    {
        $widgets = [];

        if (config('filament-cart.widgets.stats_overview', true)) {
            $widgets[] = CartStatsWidget::class;
        }

        if (config('filament-cart.widgets.abandoned_carts', true)) {
            $widgets[] = AbandonedCartsWidget::class;
        }

        if (config('filament-cart.features.monitoring', true)) {
            $widgets[] = LiveStatsWidget::class;
            $widgets[] = RecentActivityWidget::class;
        }

        return $widgets;
    }
}
