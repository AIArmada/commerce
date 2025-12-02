<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory;

use AIArmada\FilamentInventory\Services\InventoryStatsAggregator;
use AIArmada\FilamentInventory\Widgets\InventoryStatsWidget;
use AIArmada\FilamentInventory\Widgets\LowInventoryAlertsWidget;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentInventoryServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-inventory';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile('filament-inventory');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentInventoryPlugin::class);
        $this->app->singleton(InventoryStatsAggregator::class);
    }

    public function packageBooted(): void
    {
        // Register Livewire components for widgets
        Livewire::component('a-i-armada.filament-inventory.widgets.inventory-stats-widget', InventoryStatsWidget::class);
        Livewire::component('a-i-armada.filament-inventory.widgets.low-inventory-alerts-widget', LowInventoryAlertsWidget::class);

        Filament::registerRenderHook('panels::body.start', static function (): void {
            // Plugin discovery hook
        });
    }
}
