<?php

declare(strict_types=1);

namespace AIArmada\Products;

use AIArmada\Products\Contracts\CompositeStockResolverInterface;
use AIArmada\Products\Contracts\VariantGeneratorInterface;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeGroup;
use AIArmada\Products\Models\AttributeSet;
use AIArmada\Products\Models\AttributeValue;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Collection;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use AIArmada\Products\Policies\AttributeGroupPolicy;
use AIArmada\Products\Policies\AttributePolicy;
use AIArmada\Products\Policies\AttributeSetPolicy;
use AIArmada\Products\Policies\AttributeValuePolicy;
use AIArmada\Products\Policies\CategoryPolicy;
use AIArmada\Products\Policies\CollectionPolicy;
use AIArmada\Products\Policies\OptionPolicy;
use AIArmada\Products\Policies\OptionValuePolicy;
use AIArmada\Products\Policies\ProductPolicy;
use AIArmada\Products\Policies\VariantPolicy;
use AIArmada\Products\Services\CompositePackageService;
use AIArmada\Products\Strategies\MatrixVariantGenerator;
use AIArmada\Products\Support\ModelStockResolver;
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
            ->runsMigrations()
            ->discoversMigrations();
    }

    public function packageRegistered(): void
    {
        $this->app->bind(VariantGeneratorInterface::class, MatrixVariantGenerator::class);
        $this->app->bind(CompositeStockResolverInterface::class, ModelStockResolver::class);
    }

    public function bootingPackage(): void
    {
        $this->registerPolicies();
        $this->registerCompositeGuards();
    }

    protected function registerCompositeGuards(): void
    {
        Product::saving(function (Product $product): void {
            $type = $product->type;

            if (! $type instanceof ProductType || $type !== ProductType::Composite) {
                return;
            }

            $product->setAttribute('requires_shipping', true);
            $product->setAttribute('tracks_inventory', false);
            $product->setAttribute('supports_variants', false);

            if ($product->getAttribute('price_mode') === null || $product->getAttribute('price_mode') === '') {
                $product->setAttribute('price_mode', 'fixed');
            }

            $status = $product->status;

            if ($status instanceof ProductStatus && $status === ProductStatus::Active) {
                $this->app->make(CompositePackageService::class)->assertPublishable($product);
            }
        });

    }

    protected function registerPolicies(): void
    {
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(Collection::class, CollectionPolicy::class);
        Gate::policy(Attribute::class, AttributePolicy::class);
        Gate::policy(AttributeGroup::class, AttributeGroupPolicy::class);
        Gate::policy(AttributeSet::class, AttributeSetPolicy::class);
        Gate::policy(Option::class, OptionPolicy::class);
        Gate::policy(OptionValue::class, OptionValuePolicy::class);
        Gate::policy(Variant::class, VariantPolicy::class);
        Gate::policy(AttributeValue::class, AttributeValuePolicy::class);
    }
}
