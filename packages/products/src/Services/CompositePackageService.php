<?php

declare(strict_types=1);

namespace AIArmada\Products\Services;

use AIArmada\CommerceSupport\Contracts\Buyable;
use AIArmada\Products\Contracts\CompositeCapacityDemandResolverInterface;
use AIArmada\Products\Contracts\CompositeCapacityResolverInterface;
use AIArmada\Products\Data\CompositeAvailability;
use AIArmada\Products\Data\CompositeCapacityRequest;
use AIArmada\Products\Data\CompositeSnapshot;
use AIArmada\Products\Enums\CompositePriceMode;
use AIArmada\Products\Exceptions\InvalidCompositePackageException;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\ProductComponent;
use AIArmada\Products\Models\Variant;
use AIArmada\Products\Support\CompositeBuyableRegistry;
use AIArmada\Products\Support\ProductShipping;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

final class CompositePackageService
{
    public function __construct(
        private readonly CompositePackageValidator $validator,
        private readonly CompositeCapacityResolverInterface $capacityResolver,
    ) {}

    /** @return Collection<int, ProductComponent> */
    public function componentsFor(Product $product, ?Variant $parentVariant = null): Collection
    {
        $this->assertParentAccessible($product);

        $this->assertParentVariant($product, $parentVariant);

        return ProductComponent::query()
            ->where('product_id', $product->getKey())
            ->where('parent_variant_id', $parentVariant?->getKey())
            ->with(['componentProduct', 'variant'])
            ->ordered()
            ->get();
    }

    /** @param Collection<int, ProductComponent>|null $components */
    public function priceFor(Product $product, ?Collection $components = null, ?Variant $parentVariant = null): int
    {
        $this->assertParentAccessible($product);
        $this->assertParentVariant($product, $parentVariant);
        $this->assertComponentScope($product, $components, $parentVariant);

        return $this->recursivePrice($product, $components, [], $parentVariant);
    }

    /** @param Collection<int, ProductComponent>|null $components */
    public function snapshot(Product $product, ?Collection $components = null, ?Variant $parentVariant = null): CompositeSnapshot
    {
        $this->assertParentAccessible($product);
        $this->assertParentVariant($product, $parentVariant);
        $this->assertComponentScope($product, $components, $parentVariant);
        $components ??= $this->componentsFor($product, $parentVariant);

        $leaves = $this->flatten($product, $components, 1, [], [], $parentVariant);
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
            variantId: $parentVariant?->getKey(),
            sku: (string) ($parentVariant?->sku ?? $product->sku),
            name: (string) ($parentVariant?->name ?? $product->name),
            price: $this->recursivePrice($product, $components, [], $parentVariant),
            currency: mb_strtoupper((string) $product->currency),
            priceMode: $this->resolvePriceMode($product, $parentVariant)->value,
            entries: $entries,
            discountBasisPoints: (int) ($parentVariant?->discount_basis_points ?? $product->discount_basis_points ?? 0),
        );
    }

    /**
     * @param  Collection<int, ProductComponent>|null  $components
     * @param  array<string, mixed>  $context
     */
    public function availability(Product $product, ?Collection $components = null, array $context = [], ?Variant $parentVariant = null): CompositeAvailability
    {
        $this->assertParentAccessible($product);
        $this->assertParentVariant($product, $parentVariant);
        $this->assertComponentScope($product, $components, $parentVariant);
        $components ??= $this->componentsFor($product, $parentVariant);

        if ($components->isEmpty()) {
            return new CompositeAvailability(0, null, null, []);
        }

        $complete = true;
        $leaves = $this->flatten($product, $components, 1, [], [], $parentVariant, complete: $complete);
        if (! $complete || $leaves === []) {
            return new CompositeAvailability(0, null, null, []);
        }

        // Aggregate by the actual capacity resource and scope, not only the
        // Product identity. Different products may draw from one shared pool;
        // one product may draw from distinct windows or other independent scopes.
        $grouped = [];
        $stockByIdentity = [];

        foreach ($leaves as $leaf) {
            $component = $leaf['component'];
            $variant = $component->variant;
            $componentProduct = $leaf['product'];
            $inventoryable = $variant instanceof Variant ? $variant : $componentProduct;
            if (! $inventoryable instanceof Model) {
                throw new InvalidCompositePackageException('Composite capacity requires an Eloquent inventory identity.');
            }
            $inventoryKey = $inventoryable->getMorphClass() . ':' . $inventoryable->getKey();
            $sellable = $leaf['sellable'] && (! $variant instanceof Variant || $variant->is_enabled);
            $leafContext = count($leaf['path']) > 1
                ? array_replace($context, ['composition_path' => $leaf['path']])
                : $context;

            if (! array_key_exists($inventoryKey, $stockByIdentity)) {
                $stock = $inventoryable instanceof Buyable ? $inventoryable->getBuyableStock() : null;
                $stockByIdentity[$inventoryKey] = $stock === null ? null : max(0, $stock);
            }

            $request = new CompositeCapacityRequest(
                parentProductId: (string) $product->getKey(),
                componentId: (string) $component->getKey(),
                componentProductId: $component->component_product_id,
                variantId: $component->variant_id,
                inventoryableType: $inventoryable->getMorphClass(),
                inventoryableId: (string) $inventoryable->getKey(),
                quantity: $leaf['quantity'],
                metadata: $component->metadata ?? [],
                context: $leafContext,
                stockAvailable: $stockByIdentity[$inventoryKey],
            );

            $resourceKey = $inventoryKey;
            $scopeKey = '';
            $required = $leaf['quantity'];

            if ($this->capacityResolver instanceof CompositeCapacityDemandResolverInterface) {
                $demand = $this->capacityResolver->demandFor($request);
                $resourceKey = $demand->resourceKey;
                $scopeKey = $demand->scopeKey;
                $required = $this->safeMultiply($required, $demand->unitsPerComponent);
                $available = $sellable ? $demand->available : 0;
            } else {
                $available = $sellable ? $this->capacityResolver->availableFor($request) : 0;
                $available = $available === null ? null : max(0, $available);
            }

            $key = json_encode([$resourceKey, $scopeKey], JSON_THROW_ON_ERROR);

            if (! isset($grouped[$key])) {
                $grouped[$key] = [
                    'product_id' => $componentProduct instanceof Product ? (string) $componentProduct->getKey() : null,
                    'variant_id' => $variant instanceof Variant ? (string) $variant->getKey() : null,
                    'sku' => $componentProduct instanceof Product
                        ? (string) ($variant?->sku ?? $componentProduct->sku)
                        : (string) ($componentProduct->getBuyableAttributes()['sku'] ?? $componentProduct->getBuyableIdentifier()),
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
    public function assertPublishable(Product $product, ?Collection $components = null, ?Variant $parentVariant = null): void
    {
        $this->assertParentAccessible($product);
        $this->assertParentVariant($product, $parentVariant);
        $this->assertComponentScope($product, $components, $parentVariant);

        if ($product->supportsVariants() && $parentVariant === null) {
            $variants = $product->variants()->where('is_enabled', true)->get();

            if ($variants->isEmpty()) {
                throw new InvalidCompositePackageException('An active composite variant product must have at least one enabled variant.');
            }

            foreach ($variants as $variant) {
                $this->validator->assertPublishable(
                    $product,
                    $this->componentsFor($product, $variant),
                );
            }

            return;
        }

        $components ??= $this->componentsFor($product, $parentVariant);
        $this->validator->assertPublishable($product, $components);
    }

    public function priceModeFor(Product $product, ?Variant $parentVariant = null): CompositePriceMode
    {
        $this->assertParentAccessible($product);
        $this->assertParentVariant($product, $parentVariant);

        return $this->resolvePriceMode($product, $parentVariant);
    }

    private function resolvePriceMode(Product $product, ?Variant $parentVariant): CompositePriceMode
    {
        $value = $parentVariant?->price_mode ?? $product->getAttribute('price_mode');

        if ($value instanceof CompositePriceMode) {
            return $value;
        }

        $mode = CompositePriceMode::tryFrom((string) $value);

        if (! $mode instanceof CompositePriceMode) {
            throw new InvalidCompositePackageException('A composite package requires a valid price mode.');
        }

        return $mode;
    }

    /** Shipping is the union of physical leaf components, never a parent allocation. */
    public function requiresShippingFor(Product $product, ?Variant $parentVariant = null): bool
    {
        $this->assertParentAccessible($product);
        $this->assertParentVariant($product, $parentVariant);

        if ($product->supportsVariants() && $parentVariant === null) {
            foreach ($product->variants()->where('is_enabled', true)->get() as $variant) {
                if ($this->requiresShippingFor($product, $variant)) {
                    return true;
                }
            }

            return false;
        }

        foreach ($this->flatten($product, null, 1, [], [], $parentVariant) as $leaf) {
            if ($leaf['product'] instanceof Product && $leaf['product']->requires_shipping) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, ProductComponent>|null  $components
     * @param  list<string>  $ancestors
     */
    private function recursivePrice(Product $product, ?Collection $components, array $ancestors, ?Variant $parentVariant = null): int
    {
        $this->assertNotCyclic($product, $ancestors);

        $mode = $this->resolvePriceMode($product, $parentVariant);
        if ($mode === CompositePriceMode::Fixed) {
            return (int) ($parentVariant?->price ?? $product->price);
        }

        $ancestors[] = (string) $product->getKey();
        $components ??= $this->componentsFor($product, $parentVariant);
        $total = 0;

        foreach ($components as $component) {
            $child = $this->resolveChild($product, $component);
            $variant = $component->variant;

            $unitPrice = $component->unit_price;
            if ($unitPrice === null) {
                $unitPrice = $child instanceof Product && $child->isCompositeProduct()
                    ? $this->recursivePrice($child, null, $ancestors, $variant instanceof Variant ? $variant : null)
                    : ($child instanceof Product
                        ? (int) ($variant instanceof Variant && $variant->price !== null ? $variant->price : $child->price)
                        : $child->getBuyablePrice());
            }

            $total = $this->safeAdd($total, $this->safeMultiply((int) $unitPrice, (int) $component->quantity));
        }

        if ($mode === CompositePriceMode::PercentageDiscount) {
            $basisPoints = (int) ($parentVariant?->discount_basis_points ?? $product->discount_basis_points ?? 0);
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
     * @return list<array{product: Product|Buyable, component: ProductComponent, quantity: int, sellable: bool, path: list<array<string, mixed>>}>
     */
    private function flatten(Product $product, ?Collection $components, int $multiplier, array $ancestors, array $path, ?Variant $parentVariant = null, bool $ancestorsSellable = true, ?bool &$complete = null): array
    {
        $this->assertNotCyclic($product, $ancestors);
        $ancestors[] = (string) $product->getKey();
        $components ??= $this->componentsFor($product, $parentVariant);

        if ($components->isEmpty()) {
            $complete = false;

            return [];
        }

        $leaves = [];

        foreach ($components as $component) {
            $child = $this->resolveChild($product, $component);
            $quantity = $this->safeMultiply($multiplier, (int) $component->quantity);
            $step = [
                'product_id' => $child instanceof Product ? (string) $child->getKey() : null,
                'component_id' => (string) $component->getKey(),
                'quantity' => (int) $component->quantity,
            ];

            if (! $child instanceof Product) {
                $step['buyable_type'] = (string) $component->buyable_type;
                $step['buyable_id'] = (string) $component->buyable_id;
            }

            if ($component->variant_id !== null) {
                $step['variant_id'] = (string) $component->variant_id;
            }

            if ($component->unit_price !== null) {
                $step['unit_price'] = (int) $component->unit_price;
            }

            if (is_array($component->metadata) && $component->metadata !== []) {
                $step['metadata'] = $component->metadata;
            }

            $childPath = [...$path, $step];

            if ($child instanceof Product && $child->isCompositeProduct()) {
                $variant = $component->variant;
                $sellable = $ancestorsSellable && $child->isPurchasable()
                    && (! $variant instanceof Variant || $variant->is_enabled);

                array_push($leaves, ...$this->flatten($child, null, $quantity, $ancestors, $childPath, $variant, $sellable, $complete));

                continue;
            }

            $leaves[] = [
                'product' => $child,
                'component' => $component,
                'quantity' => $quantity,
                'sellable' => $ancestorsSellable && ($child instanceof Product ? $child->isPurchasable() : $child->canBePurchased()),
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

    private function resolveChild(Product $parent, ProductComponent $component): Product | Buyable
    {
        if ($component->component_product_id === null) {
            $buyable = app(CompositeBuyableRegistry::class)->resolve(
                $parent,
                (string) $component->buyable_type,
                (string) $component->buyable_id,
            );

            $this->validator->assertBuyableComponent($parent, $buyable, (int) $component->quantity);

            return $buyable;
        }

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
    private function entryFor(Product | Buyable $sellable, ProductComponent $component, int $quantity): array
    {
        if (! $sellable instanceof Product) {
            $attributes = $sellable->getBuyableAttributes();
            $entry = [
                'product_id' => null,
                'variant_id' => null,
                'inventoryable_type' => $sellable instanceof Model ? $sellable->getMorphClass() : (string) $component->buyable_type,
                'inventoryable_id' => (string) $component->buyable_id,
                'quantity' => $quantity,
                'sku' => (string) ($attributes['sku'] ?? $sellable->getBuyableIdentifier()),
                'name' => $sellable->getBuyableName(),
                'weight' => null,
                'dimensions' => null,
                'requires_shipping' => false,
                'tracks_inventory' => false,
                'buyable_type' => (string) $component->buyable_type,
                'buyable_id' => (string) $component->buyable_id,
            ];
        } else {
            $variant = $component->variant;
            $inventoryable = $variant instanceof Variant ? $variant : $sellable;
            $entry = [
                'product_id' => (string) $sellable->getKey(),
                'variant_id' => $variant instanceof Variant ? (string) $variant->getKey() : null,
                'inventoryable_type' => $inventoryable->getMorphClass(),
                'inventoryable_id' => (string) $inventoryable->getKey(),
                'quantity' => $quantity,
                'sku' => (string) ($variant?->sku ?? $sellable->sku),
                'name' => (string) (($variant?->name ?? '') !== '' ? $variant->name : $sellable->name),
                'weight' => ProductShipping::weightGrams($variant?->weight ?? $sellable->weight, $sellable->weight_unit),
                'dimensions' => ProductShipping::dimensionsCm(
                    $variant?->length ?? $sellable->length,
                    $variant?->width ?? $sellable->width,
                    $variant?->height ?? $sellable->height,
                    $sellable->dimension_unit,
                ),
                'requires_shipping' => (bool) $sellable->requires_shipping,
                'tracks_inventory' => (bool) $sellable->tracks_inventory,
            ];
        }

        if ($component->unit_price !== null) {
            $entry['unit_price'] = (int) $component->unit_price;
        }

        if (is_array($component->metadata) && $component->metadata !== []) {
            $entry['metadata'] = $component->metadata;
        }

        return $entry;
    }

    /** @param Collection<int, ProductComponent>|null $components */
    private function assertComponentScope(Product $product, ?Collection $components, ?Variant $parentVariant): void
    {
        if ($components === null) {
            return;
        }

        foreach ($components as $component) {
            if ((string) $component->product_id !== (string) $product->getKey()
                || (string) $component->parent_variant_id !== (string) $parentVariant?->getKey()
                || (string) $component->owner_type !== (string) $product->owner_type
                || (string) $component->owner_id !== (string) $product->owner_id) {
                throw new InvalidCompositePackageException('Composite components must belong to the selected product, variant and owner.');
            }
        }
    }

    private function assertParentVariant(Product $product, ?Variant $parentVariant): void
    {
        if ($parentVariant === null) {
            return;
        }

        if ((string) $parentVariant->product_id !== (string) $product->getKey()
            || ! $product->supportsVariants()
            || (string) $parentVariant->owner_type !== (string) $product->owner_type
            || (string) $parentVariant->owner_id !== (string) $product->owner_id) {
            throw new InvalidCompositePackageException('Composite parent variant must belong to the selected product.');
        }
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
