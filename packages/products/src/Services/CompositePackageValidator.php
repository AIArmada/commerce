<?php

declare(strict_types=1);

namespace AIArmada\Products\Services;

use AIArmada\Products\Enums\CompositePriceMode;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Exceptions\InvalidCompositePackageException;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\ProductComponent;
use AIArmada\Products\Models\Variant;
use Illuminate\Database\Eloquent\Collection;

/**
 * Domain rules for merchant-configured composite packages.
 *
 * The parent product is only a commercial identity: it has no inventory of
 * its own and every sellable unit resolves to exact physical component SKUs.
 */
final class CompositePackageValidator
{
    public function assertComponent(Product $parent, Product $componentProduct, ?Variant $variant, int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidCompositePackageException('A composite component quantity must be at least one.');
        }

        if ((string) $parent->getKey() === (string) $componentProduct->getKey()) {
            throw new InvalidCompositePackageException('A composite package cannot contain itself.');
        }

        if ($this->typeOf($parent) !== ProductType::Composite) {
            throw new InvalidCompositePackageException('Only a composite product can own package components.');
        }

        if ($this->typeOf($componentProduct) === ProductType::Composite) {
            throw new InvalidCompositePackageException('Nested composite packages are not supported.');
        }

        if (! $this->sameOwner($parent, $componentProduct)) {
            throw new InvalidCompositePackageException('A composite component product must share the package owner.');
        }

        if (! $this->typeOf($componentProduct)->isPhysical() || ! (bool) $componentProduct->requires_shipping) {
            throw new InvalidCompositePackageException('A composite component must be a physical product that requires shipping.');
        }

        if (! (bool) $componentProduct->tracks_inventory) {
            throw new InvalidCompositePackageException('A composite component must track inventory.');
        }

        if (mb_trim((string) $componentProduct->sku) === '') {
            throw new InvalidCompositePackageException('A composite component product must have a SKU.');
        }

        if (mb_strtoupper((string) $parent->currency) !== mb_strtoupper((string) $componentProduct->currency)) {
            throw new InvalidCompositePackageException('Composite components must use the package currency.');
        }

        if ((bool) $parent->is_taxable !== (bool) $componentProduct->is_taxable
            || (string) $parent->tax_class !== (string) $componentProduct->tax_class) {
            throw new InvalidCompositePackageException('Composite components must use the same tax treatment as the package.');
        }

        $componentType = $this->typeOf($componentProduct);

        if ($componentType === ProductType::Configurable) {
            if (! $variant instanceof Variant) {
                throw new InvalidCompositePackageException('A configurable component requires an exact variant.');
            }

            if ((string) $variant->product_id !== (string) $componentProduct->getKey()) {
                throw new InvalidCompositePackageException('The selected variant does not belong to the component product.');
            }

            if (! $this->variantSharesOwner($componentProduct, $variant)) {
                throw new InvalidCompositePackageException('A component variant must share the component product owner.');
            }

            if (mb_trim((string) $variant->sku) === '') {
                throw new InvalidCompositePackageException('A component variant must have a SKU.');
            }

            return;
        }

        if ($variant instanceof Variant) {
            throw new InvalidCompositePackageException('Only configurable components may select a variant.');
        }
    }

    public function assertPublishableComponent(Product $parent, Product $componentProduct, ?Variant $variant, int $quantity): void
    {
        $this->assertComponent($parent, $componentProduct, $variant, $quantity);

        if (! $componentProduct->isPurchasable()) {
            throw new InvalidCompositePackageException(sprintf(
                'Component product [%s] must be active before the package can be published.',
                $componentProduct->sku ?: $componentProduct->getKey(),
            ));
        }

        if ($variant instanceof Variant && ! $variant->is_enabled) {
            throw new InvalidCompositePackageException(sprintf(
                'Component variant [%s] must be enabled before the package can be published.',
                $variant->sku,
            ));
        }
    }

    /**
     * @param  Collection<int, ProductComponent>  $components
     */
    public function assertPublishable(Product $parent, Collection $components): void
    {
        if ($this->typeOf($parent) !== ProductType::Composite) {
            throw new InvalidCompositePackageException('Only composite products can be published as packages.');
        }

        if (mb_trim((string) $parent->sku) === '') {
            throw new InvalidCompositePackageException('A composite package must have a parent SKU.');
        }

        if ($components->isEmpty()) {
            throw new InvalidCompositePackageException('An active composite package must contain at least one component.');
        }

        $mode = $parent->getAttribute('price_mode');

        if (! $mode instanceof CompositePriceMode) {
            $mode = CompositePriceMode::tryFrom((string) $mode);
        }

        if (! $mode instanceof CompositePriceMode) {
            throw new InvalidCompositePackageException('A composite package requires a valid fixed or sum price mode.');
        }

        $seen = [];

        foreach ($components as $component) {
            $componentProduct = $component->componentProduct;
            $variant = $component->variant;

            if (! $componentProduct instanceof Product) {
                throw new InvalidCompositePackageException('Composite component product could not be resolved.');
            }

            $this->assertPublishableComponent($parent, $componentProduct, $variant, (int) $component->quantity);

            $identity = $componentProduct->getKey() . ':' . ($variant?->getKey() ?? '');

            if (isset($seen[$identity])) {
                throw new InvalidCompositePackageException('A composite package cannot contain the same component twice.');
            }

            $seen[$identity] = true;
        }
    }

    public function sameOwner(Product $parent, Product $componentProduct): bool
    {
        return (string) $parent->owner_type === (string) $componentProduct->owner_type
            && (string) $parent->owner_id === (string) $componentProduct->owner_id;
    }

    private function variantSharesOwner(Product $componentProduct, Variant $variant): bool
    {
        return (string) $componentProduct->owner_type === (string) $variant->owner_type
            && (string) $componentProduct->owner_id === (string) $variant->owner_id;
    }

    private function typeOf(Product $product): ProductType
    {
        $type = $product->type;

        if ($type instanceof ProductType) {
            return $type;
        }

        return ProductType::tryFrom((string) $type) ?? ProductType::Simple;
    }
}
