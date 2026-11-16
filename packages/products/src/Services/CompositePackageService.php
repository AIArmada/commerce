<?php

declare(strict_types=1);

namespace AIArmada\Products\Services;

use AIArmada\Products\Contracts\CompositeCapacityResolverInterface;
use AIArmada\Products\Enums\CompositePriceMode;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Exceptions\InvalidCompositePackageException;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\ProductComponent;
use AIArmada\Products\Models\Variant;
use AIArmada\Products\Support\CompositeAvailability;
use AIArmada\Products\Support\CompositeSnapshot;
use AIArmada\Products\Support\ProductShipping;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

final class CompositePackageService
{
    public function __construct(
        private readonly CompositePackageValidator $validator,
        private readonly CompositeCapacityResolverInterface $capacityResolver,
    ) {}

    /**
     * @return Collection<int, ProductComponent>
     */
    public function componentsFor(Product $product): Collection
    {
        $this->assertParentAccessible($product);

        return ProductComponent::query()
            ->where('product_id', $product->getKey())
            ->with(['componentProduct', 'variant'])
            ->ordered()
            ->get();
    }

    /**
     * @param  Collection<int, ProductComponent>|null  $components
     */
    public function priceFor(Product $product, ?Collection $components = null): int
    {
        $this->assertParentAccessible($product);
        $components ??= $this->componentsFor($product);

        return match ($this->priceModeFor($product)) {
            CompositePriceMode::Fixed => (int) $product->getAttribute('price'),
            CompositePriceMode::Sum => $this->sumComponentPrices($components),
        };
    }

    /**
     * @param  Collection<int, ProductComponent>|null  $components
     */
    public function snapshot(Product $product, ?Collection $components = null): CompositeSnapshot
    {
        $this->assertParentAccessible($product);
        $components ??= $this->componentsFor($product);

        $entries = [];

        foreach ($components as $component) {
            $entries[] = $this->entryFor($product, $component);
        }

        return new CompositeSnapshot(
            productId: (string) $product->getKey(),
            variantId: null,
            sku: (string) $product->sku,
            name: (string) $product->name,
            price: $this->priceFor($product, $components),
            currency: mb_strtoupper((string) $product->currency),
            priceMode: $this->priceModeFor($product)->value,
            entries: $entries,
        );
    }

    /**
     * @param  Collection<int, ProductComponent>|null  $components
     * @param  array<string, mixed>  $context
     */
    public function availability(Product $product, ?Collection $components = null, array $context = []): CompositeAvailability
    {
        $this->assertParentAccessible($product);
        $components ??= $this->componentsFor($product);

        if ($components->isEmpty()) {
            return new CompositeAvailability(0, null, null, []);
        }

        $rows = [];
        $packages = null;
        $limitingSku = null;
        $limitingAvailable = null;

        foreach ($components as $component) {
            $componentProduct = $component->componentProduct;
            $variant = $component->variant;

            if (! $componentProduct instanceof Product) {
                throw new InvalidCompositePackageException('Composite component product could not be resolved.');
            }

            $this->validator->assertComponent(
                $product,
                $componentProduct,
                $variant,
                (int) $component->quantity,
            );

            $sellable = $componentProduct->isPurchasable()
                && (! $variant instanceof Variant || $variant->is_enabled);
            $available = $sellable
                ? $this->capacityResolver->availableFor($product, $component, $context)
                : 0;
            $available = $available !== null ? max(0, $available) : null;
            $quantity = (int) $component->quantity;
            $possiblePackages = $available === null ? null : intdiv($available, $quantity);
            $sku = (string) ($variant?->sku ?? $componentProduct->sku);

            $rows[] = [
                'product_id' => (string) $componentProduct->getKey(),
                'variant_id' => $variant instanceof Variant ? (string) $variant->getKey() : null,
                'sku' => $sku,
                'quantity' => $quantity,
                'available' => $available,
                'packages' => $possiblePackages,
            ];

            // null means unbounded, not zero. A disabled/unavailable component
            // still yields an explicit zero and blocks the package.
            if ($possiblePackages !== null && ($packages === null || $possiblePackages < $packages)) {
                $packages = $possiblePackages;
                $limitingSku = $sku;
                $limitingAvailable = $available;
            }
        }

        return new CompositeAvailability(
            packages: $packages,
            limitingSku: $limitingSku,
            limitingAvailableQuantity: $limitingAvailable,
            components: $rows,
        );
    }

    /**
     * @param  Collection<int, ProductComponent>|null  $components
     */
    public function assertPublishable(Product $product, ?Collection $components = null): void
    {
        $components ??= $this->componentsFor($product);

        $this->validator->assertPublishable($product, $components);
    }

    public function priceModeFor(Product $product): CompositePriceMode
    {
        $value = $product->getAttribute('price_mode');

        if ($value instanceof CompositePriceMode) {
            return $value;
        }

        $mode = CompositePriceMode::tryFrom((string) $value);

        if (! $mode instanceof CompositePriceMode) {
            throw new InvalidCompositePackageException('A composite package requires a valid fixed or sum price mode.');
        }

        return $mode;
    }

    /**
     * @param  Collection<int, ProductComponent>  $components
     */
    private function sumComponentPrices(Collection $components): int
    {
        $total = 0;

        foreach ($components as $component) {
            $componentProduct = $component->componentProduct;

            if (! $componentProduct instanceof Product) {
                throw new InvalidCompositePackageException('Composite component product could not be resolved.');
            }

            $unitPrice = $component->unit_price ?? $this->basePriceFor($componentProduct, $component->variant);

            $total += $unitPrice * (int) $component->quantity;
        }

        return $total;
    }

    private function basePriceFor(Product $componentProduct, ?Variant $variant): int
    {
        if ($variant instanceof Variant && $variant->price !== null) {
            return (int) $variant->price;
        }

        return (int) $componentProduct->price;
    }

    /**
     * @return array{
     *     product_id: string,
     *     variant_id: string|null,
     *     inventoryable_type: string,
     *     inventoryable_id: string,
     *     quantity: int,
     *     sku: string,
     *     name: string,
     *     weight: int|null,
     *     dimensions: array{length: int, width: int, height: int}|null,
     *     requires_shipping: bool,
     *     tracks_inventory: bool,
     *     unit_price?: int,
     *     metadata?: array<string, mixed>
     * }
     */
    private function entryFor(Product $parent, ProductComponent $component): array
    {
        $componentProduct = $component->componentProduct;
        $variant = $component->variant;

        if (! $componentProduct instanceof Product) {
            throw new InvalidCompositePackageException('Composite component product could not be resolved.');
        }

        $this->validator->assertComponent($parent, $componentProduct, $variant, (int) $component->quantity);

        $inventoryable = $variant instanceof Variant ? $variant : $componentProduct;

        $entry = [
            'product_id' => (string) $componentProduct->getKey(),
            'variant_id' => $variant instanceof Variant ? (string) $variant->getKey() : null,
            'inventoryable_type' => $inventoryable->getMorphClass(),
            'inventoryable_id' => (string) $inventoryable->getKey(),
            'quantity' => (int) $component->quantity,
            'sku' => (string) ($variant?->sku ?? $componentProduct->sku),
            'name' => (string) (($variant?->name ?? '') !== '' ? $variant->name : $componentProduct->name),
            'weight' => ProductShipping::weightGrams(
                $variant?->weight ?? $componentProduct->weight,
                $componentProduct->weight_unit,
            ),
            'dimensions' => ProductShipping::dimensionsCm(
                $variant?->length ?? $componentProduct->length,
                $variant?->width ?? $componentProduct->width,
                $variant?->height ?? $componentProduct->height,
                $componentProduct->dimension_unit,
            ),
            'requires_shipping' => (bool) $componentProduct->requires_shipping,
            'tracks_inventory' => (bool) $componentProduct->tracks_inventory,
        ];

        if ($component->unit_price !== null) {
            $entry['unit_price'] = (int) $component->unit_price;
        }

        if (is_array($component->metadata) && $component->metadata !== []) {
            $entry['metadata'] = $component->metadata;
        }

        return $entry;
    }

    private function assertParentAccessible(Product $product): void
    {
        if (! $product->exists) {
            return;
        }

        $accessible = Product::query()->whereKey($product->getKey())->exists();

        if (! $accessible) {
            throw new AuthorizationException('Composite package is not accessible in the current owner scope.');
        }

        $type = $product->type;

        if ($type instanceof ProductType && $type !== ProductType::Composite) {
            throw new InvalidCompositePackageException('Only composite products have package components.');
        }
    }
}
