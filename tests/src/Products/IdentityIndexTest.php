<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Enums\AttributeType;
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeValue;
use AIArmada\Products\Models\Category;
use AIArmada\Products\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('allows category slugs to repeat below different parents but not the same parent', function (): void {
    $firstParent = Category::create(['name' => 'First Parent', 'slug' => 'first-parent']);
    $secondParent = Category::create(['name' => 'Second Parent', 'slug' => 'second-parent']);

    $firstChild = Category::create([
        'name' => 'Shared Child',
        'slug' => 'shared-child',
        'parent_id' => $firstParent->id,
    ]);
    $secondChild = Category::create([
        'name' => 'Shared Child',
        'slug' => 'shared-child',
        'parent_id' => $secondParent->id,
    ]);

    expect($firstChild->id)->not->toBe($secondChild->id);

    $owner = OwnerContext::resolve();

    expect(fn () => Category::withoutEvents(fn (): Category => Category::create([
        'name' => 'Duplicate Child',
        'slug' => 'shared-child',
        'parent_id' => $firstParent->id,
        'owner_type' => $owner?->getMorphClass(),
        'owner_id' => $owner?->getKey(),
    ])))->toThrow(QueryException::class);
});

it('treats a null locale as one unique attribute value', function (): void {
    $attribute = Attribute::create([
        'code' => 'identity-index-brand',
        'name' => 'Brand',
        'type' => AttributeType::Text,
    ]);
    $product = Product::create([
        'name' => 'Identity Index Product',
        'slug' => 'identity-index-product',
    ]);

    $value = [
        'attribute_id' => $attribute->id,
        'attributable_type' => (new Product)->getMorphClass(),
        'attributable_id' => $product->id,
        'value' => 'Acme',
        'locale' => null,
    ];

    AttributeValue::create($value);

    expect(fn () => AttributeValue::create($value))->toThrow(QueryException::class);
});

it('records the final identity shape without derived scope columns', function (): void {
    expect(Schema::hasColumn('products', 'owner_scope'))->toBeFalse()
        ->and(Schema::hasColumn('product_categories', 'parent_scope'))->toBeFalse()
        ->and(Schema::hasIndex('product_categories', 'product_categories_identity_owner_root_unique'))->toBeTrue()
        ->and(Schema::hasIndex('product_categories', 'product_categories_identity_owner_child_unique'))->toBeTrue()
        ->and(Schema::hasIndex('product_attribute_values', 'product_attribute_values_identity_default_locale_unique'))->toBeTrue()
        ->and(Schema::hasIndex('product_attribute_values', 'product_attribute_values_identity_locale_unique'))->toBeTrue();
});
