<?php

declare(strict_types=1);

namespace AIArmada\FilamentStock;

use AIArmada\FilamentStock\Services\StockStatsAggregator;
use AIArmada\FilamentStock\Support\FilamentCartBridge;
use AIArmada\FilamentStock\Support\StockableTypeRegistry;
use AIArmada\FilamentStock\Widgets\LowStockAlertsWidget;
use AIArmada\FilamentStock\Widgets\StockStatsWidget;
use AIArmada\FilamentStock\Widgets\StockTransactionTimelineWidget;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentStockServiceProvider extends PackageServiceProvider
{
    public static string $name = 'filament-stock';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile('filament-stock')
            ->hasViews('filament-stock');
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(FilamentStockPlugin::class);
        $this->app->singleton(StockStatsAggregator::class);
        $this->app->singleton(StockableTypeRegistry::class);
        $this->app->singleton(FilamentCartBridge::class);
    }

    public function packageBooted(): void
    {
        // Register Livewire components for widgets
        Livewire::component('filament-stock::stock-stats-widget', StockStatsWidget::class);
        Livewire::component('filament-stock::stock-transaction-timeline-widget', StockTransactionTimelineWidget::class);
        Livewire::component('filament-stock::low-stock-alerts-widget', LowStockAlertsWidget::class);

        Filament::registerRenderHook('panels::body.start', static function (): void {
            // Registering the plugin implicitly ensures it is discoverable via Filament's panel registry.
        });

        Filament::serving(static function (): void {
            // The bridge lazily inspects whether Filament Cart is present. Resolving the singleton ensures
            // any integration hooks are prepared before Filament renders resources.
            app(FilamentCartBridge::class)->warm();
        });
    }
}
