<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Products\Actions\ApplyAttributeChanges;
use AIArmada\Products\Enums\AttributeType;
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

it('serializes values through the attribute type when applying changes', function (): void {
    $product = Product::create(['name' => 'Serialization Product', 'price' => 1000]);

    Attribute::create(['code' => 'applied_tags', 'name' => 'Tags', 'type' => AttributeType::Multiselect]);
    Attribute::create(['code' => 'applied_flag', 'name' => 'Flag', 'type' => AttributeType::Boolean]);

    $product->setCustomAttribute('applied_tags', ['a']);
    $product->setCustomAttribute('applied_flag', false);

    $result = app(ApplyAttributeChanges::class)->execute($product, [
        'applied_tags' => ['a', 'b'],
        'applied_flag' => true,
    ]);

    expect($result->getCustomAttribute('applied_tags'))->toBe(['a', 'b'])
        ->and($result->getCustomAttribute('applied_flag'))->toBeTrue()
        ->and(AttributeValue::query()->whereHas('attribute', fn ($q) => $q->where('code', 'applied_tags'))->firstOrFail()->getAttributes()['value'])
        ->toBeJson();
});

it('fails loudly for unknown attribute codes', function (): void {
    $product = Product::create(['name' => 'Unknown Code Product', 'price' => 1000]);

    expect(fn () => app(ApplyAttributeChanges::class)->execute($product, ['missing_code' => 'x']))
        ->toThrow(ModelNotFoundException::class);
});

it('returns the instance when the product vanishes mid-apply', function (): void {
    $product = Product::create(['name' => 'Vanished Product', 'price' => 1000]);

    Attribute::create(['code' => 'vanished_note', 'name' => 'Note', 'type' => AttributeType::Text]);

    // Simulate the concurrent-delete race: remove the product row the moment
    // the new value row is created, so fresh() resolves to null.
    Event::listen('eloquent.created: ' . AttributeValue::class, function () use ($product): void {
        DB::table((new Product)->getTable())->where('id', $product->id)->delete();
    });

    try {
        $result = app(ApplyAttributeChanges::class)->execute($product, ['vanished_note' => 'after']);
    } finally {
        Event::forget('eloquent.created: ' . AttributeValue::class);
    }

    expect($result)->toBeInstanceOf(Product::class);
});

it('applies serialized changes to variants', function (): void {
    $product = Product::create(['name' => 'Variant Attr Product', 'price' => 1000]);
    $variant = Variant::create(['product_id' => $product->id, 'name' => 'V1', 'sku' => 'VATTR-1']);

    Attribute::create(['code' => 'variant_note', 'name' => 'Note', 'type' => AttributeType::Text]);
    $variant->setCustomAttribute('variant_note', 'old');

    $result = app(ApplyAttributeChanges::class)->forVariant($variant, ['variant_note' => 'new']);

    expect($result->getCustomAttribute('variant_note'))->toBe('new');
});

it('refuses attribute values attached to non-catalog models', function (): void {
    $attribute = Attribute::create(['code' => 'stray_value', 'name' => 'Stray', 'type' => AttributeType::Text]);
    $user = User::query()->create(['name' => 'Stray', 'email' => 'stray-attr@example.com', 'password' => 'secret']);
    $product = Product::create(['name' => 'Stray Host', 'price' => 1000]);

    expect(fn () => AttributeValue::create([
        'attribute_id' => $attribute->id,
        'attributable_type' => $user->getMorphClass(),
        'attributable_id' => $user->getKey(),
        'value' => 'junk',
    ]))->toThrow(InvalidArgumentException::class, 'products or variants');

    // Catalog parents still work.
    $value = AttributeValue::create([
        'attribute_id' => $attribute->id,
        'attributable_type' => $product->getMorphClass(),
        'attributable_id' => $product->getKey(),
        'value' => 'kept',
    ]);

    expect($value->exists)->toBeTrue();
});
