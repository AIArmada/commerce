<?php

declare(strict_types=1);

namespace AIArmada\Products\Persistence;

use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Exceptions\InvalidCompositePackageException;
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeGroup;
use AIArmada\Products\Models\AttributeSet;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Collection;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\ProductComponent;
use AIArmada\Products\Models\Variant;
use Illuminate\Support\Facades\DB;

final readonly class CleanupCatalogRelationships
{
    public function beforeAttributeDeletion(Attribute $attribute): void
    {
        // Delete all attribute values
        $attribute->values()->delete();
        // Detach from groups and sets
        $attribute->groups()->detach();
        $attribute->attributeSets()->detach();
    }

    public function beforeAttributeGroupDeletion(AttributeGroup $group): void
    {
        $group->groupAttributes()->detach();
        $group->attributeSets()->detach();
    }

    public function beforeAttributeSetDeletion(AttributeSet $set): void
    {
        // Detach from attributes and groups
        $set->setAttributes()->detach();
        $set->groups()->detach();
    }

    public function beforeCategoryDeletion(Category $category): void
    {
        // Nullify parent_id for children
        $category->children()->update(['parent_id' => null]);
        // Detach from products pivot
        $category->products()->detach();
    }

    public function beforeCollectionDeletion(Collection $collection): void
    {
        $collection->products()->detach();
    }

    public function beforeOptionDeletion(Option $option): void
    {
        // Delete values individually so OptionValue::deleting detaches
        // variant pivots instead of leaving orphaned pivot rows.
        $option->values()->reorder()->chunkById(100, function ($values): void {
            $values->each(fn ($value) => $value->delete());
        });
    }

    public function beforeOptionValueDeletion(OptionValue $optionValue): void
    {
        $optionValue->variants()->detach();
    }

    public function beforeProductDeletion(Product $product): void
    {
        if ((bool) config('products.features.owner.enabled', false)
            && ! Product::query()->whereKey($product->getKey())->exists()) {
            throw new InvalidCompositePackageException('Cross-tenant write blocked: product is not accessible in the current owner context.');
        }

        if (ProductComponent::query()->withoutOwnerScope()->where('component_product_id', $product->getKey())->exists()) {
            throw new InvalidCompositePackageException('A component product referenced by a composite package cannot be deleted.');
        }

        DB::transaction(function () use ($product): void {
            // Delete variants individually to trigger model events (for pivot cleanup)
            $product->variants()->chunkById(100, function ($variants): void {
                $variants->each(fn ($variant) => $variant->delete());
            });

            // Delete options individually so Option::deleting removes its
            // values (and their variant pivots) instead of orphaning them.
            $product->options()->chunkById(100, function ($options): void {
                $options->each(fn ($option) => $option->delete());
            });

            $product->categories()->detach();
            $product->collections()->detach();

            if ($product->isCompositeProduct()) {
                ProductComponent::query()->where('product_id', $product->getKey())->delete();
            }
        });
    }

    public function beforeVariantDeletion(Variant $variant): void
    {
        if (ProductComponent::query()->withoutOwnerScope()->where('variant_id', $variant->getKey())->exists()) {
            throw new InvalidCompositePackageException('A component variant referenced by a composite package cannot be deleted.');
        }

        if (ProductComponent::query()->withoutOwnerScope()->where('parent_variant_id', $variant->getKey())->exists()) {
            throw new InvalidCompositePackageException('A composite variant with a component graph cannot be deleted.');
        }

        $product = $variant->product()->first();

        if ($product instanceof Product && $product->isCompositeProduct()
            && $product->status === ProductStatus::Active && $variant->is_enabled
            && ! Variant::query()
                ->where('product_id', $product->getKey())
                ->where('is_enabled', true)
                ->whereKeyNot($variant->getKey())
                ->exists()) {
            throw new InvalidCompositePackageException('An active composite must retain at least one enabled variant.');
        }

        $variant->optionValues()->detach();
    }
}
