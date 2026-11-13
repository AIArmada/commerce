<?php

declare(strict_types=1);

namespace AIArmada\Products\Concerns;

use AIArmada\Products\Enums\CompositePriceMode;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\ProductComponent;
use AIArmada\Products\Services\CompositePackageService;
use AIArmada\Products\Support\CompositeAvailability;
use AIArmada\Products\Support\CompositeSnapshot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Adds package composition behavior to a product model.
 *
 * Product applies this concern during the purchase-contract integration.
 */
trait HasComponents
{
    /**
     * @return HasMany<ProductComponent, $this>
     */
    public function components(): HasMany
    {
        return $this->hasMany(ProductComponent::class, 'product_id')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function isCompositeProduct(): bool
    {
        $type = $this->getAttribute('type');

        return $type instanceof ProductType
            ? $type === ProductType::Composite
            : ProductType::tryFrom((string) $type) === ProductType::Composite;
    }

    public function compositePriceMode(): CompositePriceMode
    {
        return app(CompositePackageService::class)->priceModeFor($this);
    }

    public function compositePrice(): int
    {
        return app(CompositePackageService::class)->priceFor($this);
    }

    public function compositeSnapshot(): CompositeSnapshot
    {
        return app(CompositePackageService::class)->snapshot($this);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function compositeEntries(): array
    {
        return $this->compositeSnapshot()->entries;
    }

    public function compositeAvailability(): CompositeAvailability
    {
        return app(CompositePackageService::class)->availability($this);
    }
}
