<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentOrdersPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static */
        return filament(app(static::class)->getId());
    }

    public function getId(): string
    {
        return 'filament-orders';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                Resources\OrderResource::class,
            ])
            ->widgets([
                Widgets\OrderStatsWidget::class,
                Widgets\RecentOrdersWidget::class,
                Widgets\OrderStatusDistributionWidget::class,
            ]);
    }

    public function boot(Panel $panel): void {}
}
