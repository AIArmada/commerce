<?php

declare(strict_types=1);

namespace AIArmada\Products\Actions;

use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
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
     *     product_id?: string,
     *     component_product_id?: string,
     *     variant_id?: string|null,
     *     quantity?: int,
     *     position?: int
     * }>  $components
     * @return Collection<int, ProductComponent>
     */
    public function handle(Product $product, array $components): Collection
    {
        return DB::transaction(function () use ($product, $components): Collection {
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

            $prepared = $this->prepareComponents($parent, $components);

            ProductComponent::query()
                ->withoutOwnerScope()
                ->where('product_id', $parent->getKey())
                ->delete();

            foreach ($prepared as $row) {
                ProductComponent::query()->create($row);
            }

            $current = $this->service->componentsFor($parent);

            if ($parent->status === \AIArmada\Products\Enums\ProductStatus::Active) {
                $this->service->assertPublishable($parent, $current);
            }

            return $current;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return list<array<string, mixed>>
     */
    private function prepareComponents(Product $parent, array $components): array
    {
        $prepared = [];
        $seen = [];

        foreach (array_values($components) as $index => $component) {
            $componentProductId = (string) ($component['product_id'] ?? $component['component_product_id'] ?? '');

            if ($componentProductId === '') {
                throw new InvalidCompositePackageException('Every composite component requires a component product ID.');
            }

            $variantId = $component['variant_id'] ?? null;
            $variantId = $variantId !== null && (string) $variantId !== '' ? (string) $variantId : null;
            $quantity = (int) ($component['quantity'] ?? 0);

            $componentProduct = $this->resolveComponentProduct($componentProductId);
            $variant = $variantId !== null ? $this->resolveVariant($variantId) : null;

            $this->validator->assertComponent($parent, $componentProduct, $variant, $quantity);

            $identity = $componentProduct->getKey() . ':' . ($variant?->getKey() ?? '');

            if (isset($seen[$identity])) {
                throw new InvalidCompositePackageException('A composite package cannot contain the same component twice.');
            }

            $seen[$identity] = true;

            $prepared[] = [
                'owner_type' => $parent->owner_type,
                'owner_id' => $parent->owner_id,
                'product_id' => (string) $parent->getKey(),
                'component_product_id' => (string) $componentProduct->getKey(),
                'variant_id' => $variant instanceof Variant ? (string) $variant->getKey() : null,
                'quantity' => $quantity,
                'position' => (int) ($component['position'] ?? $index),
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
