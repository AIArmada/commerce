<?php

declare(strict_types=1);

namespace AIArmada\Stock;

use AIArmada\Stock\Services\StockService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class StockServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('stock')
            ->hasConfigFile()
            ->discoversMigrations()
            ->runsMigrations();
    }

    public function packageRegistered(): void
    {
        // Register Stock Service
        $this->app->singleton(StockService::class);
        $this->app->alias(StockService::class, 'stock');
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            StockService::class,
            'stock',
        ];
    }
}
