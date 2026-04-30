<?php

declare(strict_types=1);

namespace AIArmada\Products;

use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeGroup;
use AIArmada\Products\Models\AttributeSet;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Collection;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Policies\AttributeGroupPolicy;
use AIArmada\Products\Policies\AttributePolicy;
use AIArmada\Products\Policies\AttributeSetPolicy;
use AIArmada\Products\Policies\CategoryPolicy;
use AIArmada\Products\Policies\CollectionPolicy;
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
        Gate::policy(Collection::class, CollectionPolicy::class);
        Gate::policy(Attribute::class, AttributePolicy::class);
        Gate::policy(AttributeGroup::class, AttributeGroupPolicy::class);
        Gate::policy(AttributeSet::class, AttributeSetPolicy::class);
    }
}
