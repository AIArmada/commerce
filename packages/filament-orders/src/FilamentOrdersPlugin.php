<?php

declare(strict_types=1);

namespace AIArmada\FilamentOrders;

use Filament\Contracts\Plugin;
use Filament\Panel;

class FilamentOrdersPlugin implements Plugin
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
            ->pages([
                // Pages will be added here
            ])
            ->widgets([
                Widgets\OrderStatsWidget::class,
                Widgets\RecentOrdersWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        // Boot logic here
    }
}
