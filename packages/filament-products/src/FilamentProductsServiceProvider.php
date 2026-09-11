<?php

declare(strict_types=1);

namespace AIArmada\FilamentProducts;

use AIArmada\FilamentProducts\Support\ProductStatsCache;
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeGroup;
use AIArmada\Products\Models\AttributeSet;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Collection;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class FilamentProductsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('filament-products')
            ->hasConfigFile()
            ->hasTranslations();
    }

    public function packageBooted(): void
    {
        foreach ([Product::class, Category::class, Collection::class, Attribute::class, AttributeGroup::class, AttributeSet::class, Variant::class] as $modelClass) {
            /** @var class-string<Model> $modelClass */
            $modelClass::saved(static function (Model $model): void {
                ProductStatsCache::forgetFor($model);
            });
            $modelClass::deleted(static function (Model $model): void {
                ProductStatsCache::forgetFor($model);
            });
        }
    }
}
