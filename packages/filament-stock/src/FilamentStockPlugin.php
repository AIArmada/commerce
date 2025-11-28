<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock;

use AIArmada\FilamentStock\Resources\StockReservationResource;
use AIArmada\FilamentStock\Resources\StockTransactionResource;
use AIArmada\FilamentStock\Widgets\StockStatsWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentStockPlugin implements Plugin
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
        return 'filament-stock';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                StockTransactionResource::class,
                StockReservationResource::class,
            ])
            ->widgets([
                StockStatsWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        // No-op: the service provider handles runtime integration hooks.
    }
}
