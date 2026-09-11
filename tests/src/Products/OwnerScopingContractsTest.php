<?php

declare(strict_types=1);

namespace AIArmada\Commerce\Tests\Products;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Testing\OwnerScopingContractTests;
use AIArmada\Products\Enums\AttributeType;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;

abstract class AbstractProductOwnerScopingContractTest extends ProductsTestCase
{
    use OwnerScopingContractTests;

    /**
     * @return class-string<Model>
     */
    abstract protected function getModelClass(): string;

    protected function createOwner(): Model
    {
        return User::query()->create([
            'name' => 'Owner ' . Str::upper(Str::random(8)),
            'email' => Str::uuid() . '@products-owner.example.com',
            'password' => 'secret',
        ]);
    }

    protected function createModelForOwner(Model $owner): Model
    {
        return $this->createModel($owner, false);
    }

    protected function createGlobalModel(): Model
    {
        return $this->createModel(null, true);
    }

    public function test_assign_owner_sets_owner(): void
    {
        $owner = $this->createOwner();
        $model = $this->createGlobalModel();

        expect(function () use ($model, $owner): void {
            $model->assignOwner($owner);
            $model->save();
        })
            ->toThrow(InvalidArgumentException::class, 'persisted global');
    }

    private function createModel(?Model $owner, bool $global): Model
    {
        $ownerAttributes = $global ? [
            'owner_type' => null,
            'owner_id' => null,
        ] : [
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner?->getKey(),
        ];

        return OwnerContext::withOwner($owner, fn (): Model => match ($this->getModelClass()) {
            Product::class => Product::query()->create($ownerAttributes + [
                'name' => 'Product ' . Str::uuid(),
                'slug' => 'product-' . Str::uuid(),
                'sku' => 'SKU-' . Str::upper(Str::random(12)),
                'price' => 1000,
            ]),
            Variant::class => $this->createVariant($owner, $ownerAttributes),
            Category::class => Category::query()->create($ownerAttributes + [
                'name' => 'Category ' . Str::uuid(),
                'slug' => 'category-' . Str::uuid(),
            ]),
            Collection::class => Collection::query()->create($ownerAttributes + [
                'name' => 'Collection ' . Str::uuid(),
                'slug' => 'collection-' . Str::uuid(),
            ]),
            Attribute::class => Attribute::query()->create($ownerAttributes + [
                'name' => 'Attribute ' . Str::uuid(),
                'code' => 'attribute-' . Str::uuid(),
                'type' => AttributeType::Text,
            ]),
            AttributeGroup::class => AttributeGroup::query()->create($ownerAttributes + [
                'name' => 'Attribute Group ' . Str::uuid(),
                'code' => 'attribute-group-' . Str::uuid(),
            ]),
            AttributeSet::class => AttributeSet::query()->create($ownerAttributes + [
                'name' => 'Attribute Set ' . Str::uuid(),
                'code' => 'attribute-set-' . Str::uuid(),
            ]),
            Option::class => $this->createOption($owner, $ownerAttributes),
            OptionValue::class => $this->createOptionValue($owner, $ownerAttributes),
            AttributeValue::class => $this->createAttributeValue($owner, $ownerAttributes),
            default => throw new LogicException('Unhandled product owner contract model.'),
        });
    }

    /**
     * @param  array{owner_type: string|null, owner_id: mixed}  $ownerAttributes
     */
    private function createVariant(?Model $owner, array $ownerAttributes): Variant
    {
        $product = Product::query()->create($ownerAttributes + [
            'name' => 'Variant Product ' . Str::uuid(),
            'slug' => 'variant-product-' . Str::uuid(),
            'sku' => 'PRODUCT-' . Str::upper(Str::random(12)),
            'price' => 1000,
        ]);

        return Variant::query()->create($ownerAttributes + [
            'product_id' => $product->getKey(),
            'name' => 'Variant ' . Str::uuid(),
            'sku' => 'VARIANT-' . Str::upper(Str::random(12)),
        ]);
    }

    /**
     * @param  array{owner_type: string|null, owner_id: mixed}  $ownerAttributes
     */
    private function createOption(?Model $owner, array $ownerAttributes): Option
    {
        $product = Product::query()->create($ownerAttributes + [
            'name' => 'Option Product ' . Str::uuid(),
            'slug' => 'option-product-' . Str::uuid(),
            'sku' => 'PRODUCT-' . Str::upper(Str::random(12)),
            'price' => 1000,
        ]);

        return Option::query()->create($ownerAttributes + [
            'product_id' => $product->getKey(),
            'name' => 'Option ' . Str::uuid(),
        ]);
    }

    /**
     * @param  array{owner_type: string|null, owner_id: mixed}  $ownerAttributes
     */
    private function createOptionValue(?Model $owner, array $ownerAttributes): OptionValue
    {
        $option = $this->createOption($owner, $ownerAttributes);

        return OptionValue::query()->create($ownerAttributes + [
            'option_id' => $option->getKey(),
            'name' => 'Option Value ' . Str::uuid(),
        ]);
    }

    /**
     * @param  array{owner_type: string|null, owner_id: mixed}  $ownerAttributes
     */
    private function createAttributeValue(?Model $owner, array $ownerAttributes): AttributeValue
    {
        $attribute = Attribute::query()->create($ownerAttributes + [
            'name' => 'Value Attribute ' . Str::uuid(),
            'code' => 'value-attribute-' . Str::uuid(),
            'type' => AttributeType::Text,
        ]);
        $product = Product::query()->create($ownerAttributes + [
            'name' => 'Value Product ' . Str::uuid(),
            'slug' => 'value-product-' . Str::uuid(),
            'sku' => 'PRODUCT-' . Str::upper(Str::random(12)),
            'price' => 1000,
        ]);

        return AttributeValue::query()->create($ownerAttributes + [
            'attribute_id' => $attribute->getKey(),
            'attributable_type' => Product::class,
            'attributable_id' => $product->getKey(),
            'value' => 'value',
        ]);
    }
}
