<?php

declare(strict_types=1);

namespace AIArmada\Products\Services;

use AIArmada\Products\Contracts\CompositeCapacityDemandResolverInterface;
use AIArmada\Products\Contracts\CompositeCapacityResolverInterface;
use AIArmada\Products\Enums\CompositePriceMode;
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

    /** @return Collection<int, ProductComponent> */
    public function componentsFor(Product $product): Collection
    {
        $this->assertParentAccessible($product);

        return ProductComponent::query()
            ->where('product_id', $product->getKey())
            ->with(['componentProduct', 'variant'])
            ->ordered()
            ->get();
    }

    /** @param Collection<int, ProductComponent>|null $components */
    public function priceFor(Product $product, ?Collection $components = null): int
    {
        $this->assertParentAccessible($product);

        return $this->recursivePrice($product, $components, []);
    }

    /** @param Collection<int, ProductComponent>|null $components */
    public function snapshot(Product $product, ?Collection $components = null): CompositeSnapshot
    {
        $this->assertParentAccessible($product);
        $components ??= $this->componentsFor($product);

        $leaves = $this->flatten($product, $components, 1, [], []);
        $entries = [];

        foreach ($leaves as $leaf) {
            $entry = $this->entryFor($leaf['product'], $leaf['component'], $leaf['quantity']);

            // The original hierarchy is immutable order provenance; operational
            // integrations consume only these flattened, exact leaf identities.
            if (count($leaf['path']) > 1) {
                $entry['composition_path'] = $leaf['path'];
            }

            $entries[] = $entry;
        }

        return new CompositeSnapshot(
            productId: (string) $product->getKey(),
            variantId: null,
            sku: (string) $product->sku,
            name: (string) $product->name,
            price: $this->recursivePrice($product, $components, []),
            currency: mb_strtoupper((string) $product->currency),
            priceMode: $this->priceModeFor($product)->value,
            entries: $entries,
            discountBasisPoints: (int) ($product->discount_basis_points ?? 0),
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

        $leaves = $this->flatten($product, $components, 1, [], []);
        if ($leaves === []) {
            return new CompositeAvailability(0, null, null, []);
        }

        // Aggregate by the actual capacity resource and scope, not only the
        // Product identity. Different products may draw from one shared pool;
        // one product may draw from distinct windows or other independent scopes.
        $grouped = [];

        foreach ($leaves as $leaf) {
            $component = $leaf['component'];
            $variant = $component->variant;
            $componentProduct = $leaf['product'];
            $inventoryable = $variant instanceof Variant ? $variant : $componentProduct;
            $inventoryKey = $inventoryable->getMorphClass() . ':' . $inventoryable->getKey();
            $sellable = $leaf['sellable'] && (! $variant instanceof Variant || $variant->is_enabled);
            $leafContext = count($leaf['path']) > 1
                ? array_replace($context, ['composition_path' => $leaf['path']])
                : $context;

            $resourceKey = $inventoryKey;
            $scopeKey = '';
            $required = $leaf['quantity'];

            if ($this->capacityResolver instanceof CompositeCapacityDemandResolverInterface) {
                $demand = $this->capacityResolver->demandFor($product, $component, $leafContext);
                $resourceKey = $demand->resourceKey;
                $scopeKey = $demand->scopeKey;
                $required = $this->safeMultiply($required, $demand->unitsPerComponent);
                $available = $sellable ? $demand->available : 0;
            } else {
                $available = $sellable ? $this->capacityResolver->availableFor($product, $component, $leafContext) : 0;
                $available = $available === null ? null : max(0, $available);
            }

            $key = json_encode([$resourceKey, $scopeKey], JSON_THROW_ON_ERROR);

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'product_id' => (string) $componentProduct->getKey(),
                    'variant_id' => $variant instanceof Variant ? (string) $variant->getKey() : null,
                    'sku' => (string) ($variant?->sku ?? $componentProduct->sku),
                    'quantity' => 0,
                    'available' => null,
                    'packages' => null,
                ];

                if ($this->capacityResolver instanceof CompositeCapacityDemandResolverInterface) {
                    $grouped[$key]['resource_key'] = $resourceKey;
                    $grouped[$key]['scope_key'] = $scopeKey;
                }
            }

            $grouped[$key]['quantity'] = $this->safeAdd($grouped[$key]['quantity'], $required);

            // Capacity observations for the same pool must be consistent.
            // If different edges report different values, use the conservative
            // lower bound rather than allowing an oversell.
            if ($available !== null) {
                $current = $grouped[$key]['available'];
                $grouped[$key]['available'] = $current === null ? $available : min($current, $available);
            }
        }

        $packages = null;
        $limitingSku = null;
        $limitingAvailable = null;

        foreach ($grouped as &$row) {
            $available = $row['available'];
            $possiblePackages = $available === null ? null : intdiv($available, $row['quantity']);
            $row['packages'] = $possiblePackages;

            if ($possiblePackages !== null && ($packages === null || $possiblePackages < $packages)) {
                $packages = $possiblePackages;
                $limitingSku = $row['sku'];
                $limitingAvailable = $available;
            }
        }
        unset($row);

        return new CompositeAvailability($packages, $limitingSku, $limitingAvailable, array_values($grouped));
    }

    /** @param Collection<int, ProductComponent>|null $components */
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
            throw new InvalidCompositePackageException('A composite package requires a valid price mode.');
        }

        return $mode;
    }

    /** Return the physical shipping requirement of the actual leaf products. */
    public function requiresShippingFor(Product $product): bool
    {
        foreach ($this->flatten($product, null, 1, [], []) as $leaf) {
            if ($leaf['product']->requires_shipping) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, ProductComponent>|null  $components
     * @param  list<string>  $ancestors
     */
    private function recursivePrice(Product $product, ?Collection $components, array $ancestors): int
    {
        $this->assertNotCyclic($product, $ancestors);

        $mode = $this->priceModeFor($product);
        if ($mode === CompositePriceMode::Fixed) {
            return (int) $product->price;
        }

        $ancestors[] = (string) $product->getKey();
        $components ??= $this->componentsFor($product);
        $total = 0;

        foreach ($components as $component) {
            $child = $this->resolveChild($product, $component);
            $variant = $component->variant;

            $unitPrice = $component->unit_price;
            if ($unitPrice === null) {
                $unitPrice = $child->isCompositeProduct()
                    ? $this->recursivePrice($child, null, $ancestors)
                    : (int) ($variant instanceof Variant && $variant->price !== null ? $variant->price : $child->price);
            }

            $total = $this->safeAdd($total, $this->safeMultiply((int) $unitPrice, (int) $component->quantity));
        }

        if ($mode === CompositePriceMode::PercentageDiscount) {
            $basisPoints = (int) ($product->discount_basis_points ?? 0);
            if ($basisPoints < 0 || $basisPoints > 10000) {
                throw new InvalidCompositePackageException('Composite discount basis points must be between 0 and 10000.');
            }

            // Compute a half-up rounded discount without multiplying a potentially
            // 64-bit basket amount by 10000 (which would overflow PHP integers).
            $discount = intdiv($total, 10000) * $basisPoints
                + intdiv(($total % 10000) * $basisPoints + 5000, 10000);

            return $total - $discount;
        }

        return $total;
    }

    /**
     * @param  Collection<int, ProductComponent>|null  $components
     * @param  list<string>  $ancestors
     * @param  list<array{product_id: string, component_id: string, quantity: int}>  $path
     * @return list<array{product: Product, component: ProductComponent, quantity: int, sellable: bool, path: list<array{product_id: string, component_id: string, quantity: int}>}>
     */
    private function flatten(Product $product, ?Collection $components, int $multiplier, array $ancestors, array $path): array
    {
        $this->assertNotCyclic($product, $ancestors);
        $ancestors[] = (string) $product->getKey();
        $components ??= $this->componentsFor($product);
        $leaves = [];

        foreach ($components as $component) {
            $child = $this->resolveChild($product, $component);
            $quantity = $this->safeMultiply($multiplier, (int) $component->quantity);
            $step = [
                'product_id' => (string) $child->getKey(),
                'component_id' => (string) $component->getKey(),
                'quantity' => (int) $component->quantity,
            ];

            if ($component->unit_price !== null) {
                $step['unit_price'] = (int) $component->unit_price;
            }

            if (is_array($component->metadata) && $component->metadata !== []) {
                $step['metadata'] = $component->metadata;
            }

            $childPath = [...$path, $step];

            if ($child->isCompositeProduct()) {
                array_push($leaves, ...$this->flatten($child, null, $quantity, $ancestors, $childPath));

                continue;
            }

            $leaves[] = [
                'product' => $child,
                'component' => $component,
                'quantity' => $quantity,
                'sellable' => $child->isPurchasable(),
                'path' => $childPath,
            ];
        }

        return $leaves;
    }

    private function assertNotCyclic(Product $product, array $ancestors): void
    {
        if (count($ancestors) >= CompositePackageValidator::MAX_DEPTH) {
            throw new InvalidCompositePackageException('Composite package nesting exceeds the maximum depth.');
        }

        if (in_array((string) $product->getKey(), $ancestors, true)) {
            throw new InvalidCompositePackageException('Composite package composition contains a cycle.');
        }
    }

    private function resolveChild(Product $parent, ProductComponent $component): Product
    {
        $child = $component->componentProduct;

        if (! $child instanceof Product) {
            throw new InvalidCompositePackageException('Composite component product could not be resolved.');
        }

        $this->validator->assertComponent($parent, $child, $component->variant, (int) $component->quantity);

        return $child;
    }

    private function safeMultiply(int $a, int $b): int
    {
        if ($a < 0 || $b < 0 || ($b !== 0 && $a > intdiv(PHP_INT_MAX, $b))) {
            throw new InvalidCompositePackageException('Composite package quantity or price exceeds the supported range.');
        }

        return $a * $b;
    }

    private function safeAdd(int $a, int $b): int
    {
        if ($a < 0 || $b < 0 || $a > PHP_INT_MAX - $b) {
            throw new InvalidCompositePackageException('Composite package quantity or price exceeds the supported range.');
        }

        return $a + $b;
    }

    /**
     * @return array{
     *   product_id: string, variant_id: string|null, inventoryable_type: string,
     *   inventoryable_id: string, quantity: int, sku: string, name: string,
     *   weight: int|null, dimensions: array{length:int,width:int,height:int}|null,
     *   requires_shipping: bool, tracks_inventory: bool, unit_price?: int,
     *   metadata?: array<string,mixed>
     * }
     */
    private function entryFor(Product $product, ProductComponent $component, int $quantity): array
    {
        $variant = $component->variant;
        $inventoryable = $variant instanceof Variant ? $variant : $product;
        $entry = [
            'product_id' => (string) $product->getKey(),
            'variant_id' => $variant instanceof Variant ? (string) $variant->getKey() : null,
            'inventoryable_type' => $inventoryable->getMorphClass(),
            'inventoryable_id' => (string) $inventoryable->getKey(),
            'quantity' => $quantity,
            'sku' => (string) ($variant?->sku ?? $product->sku),
            'name' => (string) (($variant?->name ?? '') !== '' ? $variant->name : $product->name),
            'weight' => ProductShipping::weightGrams($variant?->weight ?? $product->weight, $product->weight_unit),
            'dimensions' => ProductShipping::dimensionsCm(
                $variant?->length ?? $product->length,
                $variant?->width ?? $product->width,
                $variant?->height ?? $product->height,
                $product->dimension_unit,
            ),
            'requires_shipping' => (bool) $product->requires_shipping,
            'tracks_inventory' => (bool) $product->tracks_inventory,
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

        if (! Product::query()->whereKey($product->getKey())->exists()) {
            throw new AuthorizationException('Composite package is not accessible in the current owner scope.');
        }

        if (! $product->isCompositeProduct()) {
            throw new InvalidCompositePackageException('Only composite products have package components.');
        }
    }
}
