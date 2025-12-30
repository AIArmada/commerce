<?php

declare(strict_types=1);

namespace AIArmada\Products;

use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Policies\CategoryPolicy;
use AIArmada\Products\Policies\ProductPolicy;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class ProductsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('products')
            ->hasConfigFile()
            ->hasTranslations()
            ->discoversMigrations();
    }

    public function bootingPackage(): void
    {
        $this->registerPolicies();
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
    }
}
