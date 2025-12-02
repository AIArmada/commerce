<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory;

use AIArmada\FilamentInventory\Resources\InventoryAllocationResource;
use AIArmada\FilamentInventory\Resources\InventoryLevelResource;
use AIArmada\FilamentInventory\Resources\InventoryLocationResource;
use AIArmada\FilamentInventory\Resources\InventoryMovementResource;
use AIArmada\FilamentInventory\Widgets\InventoryStatsWidget;
use AIArmada\FilamentInventory\Widgets\LowInventoryAlertsWidget;
use Filament\Contracts\Plugin;
use Filament\Panel;

final class FilamentInventoryPlugin implements Plugin
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
        return 'filament-inventory';
    }

    public function register(Panel $panel): void
    {
        $panel
            ->resources([
                InventoryLocationResource::class,
                InventoryLevelResource::class,
                InventoryMovementResource::class,
                InventoryAllocationResource::class,
            ])
            ->widgets([
                InventoryStatsWidget::class,
                LowInventoryAlertsWidget::class,
            ]);
    }

    public function boot(Panel $panel): void
    {
        // No-op: the service provider handles runtime integration hooks.
    }
}
