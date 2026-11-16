<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Products\Support\CompositeBuyableRegistry;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Exceptions\InvalidCompositePackageException;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\ProductComponent;
use AIArmada\Products\Models\Variant;
use AIArmada\Products\Services\CompositePackageService;
use AIArmada\Products\Services\CompositePackageValidator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Transactionally replaces the complete component list of one composite package.
 *
 * Empty component lists are allowed while the parent is not active. An active
 * package must remain non-empty and publishable after every replacement.
 */
final class ReplaceProductComponents
{
    public function __construct(
        private readonly CompositePackageValidator $validator,
        private readonly CompositePackageService $service,
    ) {}

    /**
     * @param  list<array{
     *     component_product_id: string,
     *     variant_id?: string|null,
     *     quantity?: int,
     *     position?: int,
     *     unit_price?: int|null,
     *     metadata?: array<string, mixed>|null
     * }>  $components
     * @return Collection<int, ProductComponent>
     */
    public function handle(Product $product, array $components, ?Variant $parentVariant = null): Collection
    {
        return DB::transaction(function () use ($product, $components, $parentVariant): Collection {
            $parent = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->first();

            if (! $parent instanceof Product) {
                throw new InvalidCompositePackageException('Composite package is not accessible in the current owner scope.');
            }

            $type = $parent->type;

            if (! $type instanceof ProductType || $type !== ProductType::Composite) {
                throw new InvalidCompositePackageException('Only composite products can own package components.');
            }

            if ($parent->supportsVariants() !== ($parentVariant instanceof Variant)) {
                throw new InvalidCompositePackageException('Variant-specific composite products require a parent variant.');
            }

            if ($parentVariant instanceof Variant
                && ((string) $parentVariant->product_id !== (string) $parent->getKey()
                    || ! $parentVariant->is_enabled)) {
                throw new InvalidCompositePackageException('The selected composite variant is not enabled on this product.');
            }

            $prepared = $this->prepareComponents($parent, $components, $parentVariant);

            ProductComponent::query()
                ->withoutOwnerScope()
                ->where('product_id', $parent->getKey())
                ->where('parent_variant_id', $parentVariant?->getKey())
                ->delete();

            foreach ($prepared as $row) {
                ProductComponent::query()->create($row);
            }

            $current = $this->service->componentsFor($parent, $parentVariant);

            // Recalculate the persisted parent shipping flag from the exact
            // composition, including when a draft package becomes empty.
            $parent->save();

            if ($parent->status === ProductStatus::Active) {
                $this->service->assertPublishable($parent, $current, $parentVariant);
            }

            return $current;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return list<array<string, mixed>>
     */
    private function prepareComponents(Product $parent, array $components, ?Variant $parentVariant): array
    {
        $prepared = [];
        $seen = [];

        foreach (array_values($components) as $index => $component) {
            $componentProductId = (string) ($component['component_product_id'] ?? '');

            $buyableType = $component['buyable_type'] ?? null;
            $buyableId = $component['buyable_id'] ?? null;
            $hasProduct = $componentProductId !== '';
            $hasExternal = $buyableType !== null || $buyableId !== null;

            if ($hasProduct === $hasExternal) {
                throw new InvalidCompositePackageException('Every component requires exactly one Product or registered Buyable identity.');
            }

            if ($hasExternal && (! is_string($buyableType) || mb_trim($buyableType) === ''
                || ! is_string($buyableId) || mb_trim($buyableId) === '')) {
                throw new InvalidCompositePackageException('External component requires a registered Buyable type and a non-empty ID.');
            }

            $isExternal = $hasExternal;

            $variantId = $component['variant_id'] ?? null;
            $variantId = $variantId !== null && (string) $variantId !== '' ? (string) $variantId : null;
            $quantity = (int) ($component['quantity'] ?? 0);

            $componentProduct = $isExternal ? null : $this->resolveComponentProduct($componentProductId);
            $variant = $variantId !== null ? $this->resolveVariant($variantId) : null;

            if ($componentProduct instanceof Product) {
                $this->validator->assertComponent($parent, $componentProduct, $variant, $quantity);
            } else {
                if ($variant !== null) {
                    throw new InvalidCompositePackageException('External Buyable components cannot select a Product variant.');
                }

                $buyable = app(CompositeBuyableRegistry::class)->resolve($parent, $buyableType, $buyableId);
                $this->validator->assertBuyableComponent($parent, $buyable, $quantity);
            }

            $unitPrice = $component['unit_price'] ?? null;
            if ($unitPrice !== null && (! is_int($unitPrice) || $unitPrice < 0)) {
                throw new InvalidCompositePackageException('A component unit price must be a non-negative integer in minor units.');
            }

            $metadata = $component['metadata'] ?? null;
            if ($metadata !== null && ! is_array($metadata)) {
                throw new InvalidCompositePackageException('Composite component metadata must be an array.');
            }

            $identity = $componentProduct instanceof Product
                ? $componentProduct->getKey() . ':' . ($variant?->getKey() ?? '')
                : 'buyable:' . $buyableType . ':' . $buyableId;

            if (isset($seen[$identity])) {
                throw new InvalidCompositePackageException('A composite package cannot contain the same component twice.');
            }

            $seen[$identity] = true;

            $prepared[] = [
                'owner_type' => $parent->owner_type,
                'owner_id' => $parent->owner_id,
                'product_id' => (string) $parent->getKey(),
                'parent_variant_id' => $parentVariant?->getKey(),
                'component_product_id' => $componentProduct instanceof Product ? (string) $componentProduct->getKey() : null,
                'buyable_type' => $isExternal ? $buyableType : null,
                'buyable_id' => $isExternal ? $buyableId : null,
                'variant_id' => $variant instanceof Variant ? (string) $variant->getKey() : null,
                'quantity' => $quantity,
                'position' => (int) ($component['position'] ?? $index),
                'unit_price' => $unitPrice,
                'metadata' => $metadata,
            ];
        }

        return $prepared;
    }

    private function resolveComponentProduct(string $id): Product
    {
        if (! (bool) config('products.features.owner.enabled', true)) {
            $product = Product::query()->whereKey($id)->first();

            if (! $product instanceof Product) {
                throw new InvalidCompositePackageException('Composite component product could not be resolved.');
            }

            return $product;
        }

        /** @var Product $product */
        $product = OwnerWriteGuard::findOrFailForOwner(
            Product::class,
            $id,
            includeGlobal: (bool) config('products.features.owner.include_global', false),
            message: 'Cross-tenant write blocked: component product is not accessible in the current owner context.',
        );

        return $product;
    }

    private function resolveVariant(string $id): Variant
    {
        if (! (bool) config('products.features.owner.enabled', true)) {
            $variant = Variant::query()->whereKey($id)->first();

            if (! $variant instanceof Variant) {
                throw new InvalidCompositePackageException('Composite component variant could not be resolved.');
            }

            return $variant;
        }

        /** @var Variant $variant */
        $variant = OwnerWriteGuard::findOrFailForOwner(
            Variant::class,
            $id,
            includeGlobal: (bool) config('products.features.owner.include_global', false),
            message: 'Cross-tenant write blocked: component variant is not accessible in the current owner context.',
        );

        return $variant;
    }
}
