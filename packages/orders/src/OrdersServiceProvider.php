<?php

declare(strict_types=1);

namespace AIArmada\Orders;

use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class OrdersServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('orders')
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            ->discoversMigrations();
    }

    public function bootingPackage(): void
    {
        $this->registerPolicies();
        $this->registerEventListeners();
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Models\Order::class, Policies\OrderPolicy::class);
        Gate::policy(Models\OrderItem::class, Policies\OrderItemPolicy::class);
    }

    protected function registerEventListeners(): void
    {
        // Event listeners will be registered here
    }
}
