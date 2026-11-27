<?php

declare(strict_types=1);

namespace AIArmada\FilamentInventory;

use AIArmada\FilamentInventory\Policies\InventoryAllocationPolicy;
use AIArmada\FilamentInventory\Policies\InventoryLevelPolicy;
use AIArmada\FilamentInventory\Policies\InventoryReorderSuggestionPolicy;
use AIArmada\FilamentInventory\Queries\InventoryStatsAggregator;
use AIArmada\FilamentInventory\Widgets\InventoryStatsWidget;
use AIArmada\FilamentInventory\Widgets\LowInventoryAlertsWidget;
use AIArmada\Inventory\Features\Replenishment\Models\InventoryReorderSuggestion;
use AIArmada\Inventory\Features\Reservations\Models\InventoryAllocation;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(InventoryLevel::class, InventoryLevelPolicy::class);
        Gate::policy(InventoryAllocation::class, InventoryAllocationPolicy::class);
        Gate::policy(InventoryReorderSuggestion::class, InventoryReorderSuggestionPolicy::class);

        // Register Livewire components for widgets
        Livewire::component('a-i-armada.filament-inventory.widgets.inventory-stats-widget', InventoryStatsWidget::class);
        Livewire::component('a-i-armada.filament-inventory.widgets.low-inventory-alerts-widget', LowInventoryAlertsWidget::class);

        Filament::registerRenderHook('panels::body.start', static function (): void {
            // Plugin discovery hook
        });
    }
}
