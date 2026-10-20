<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Actions\ApplyAttributeChanges;
use AIArmada\Products\Enums\AttributeType;
use AIArmada\Products\Models\Attribute;
use AIArmada\Products\Models\AttributeValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

it('rejects attribute values whose definition belongs to another owner', function (): void {
    $ownerA = User::query()->create(['name' => 'Value Owner A', 'email' => 'value-owner-a@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'Value Owner B', 'email' => 'value-owner-b@example.com', 'password' => 'secret']);

    $productA = OwnerContext::withOwner($ownerA, fn (): Product => Product::create(['name' => 'A Product', 'price' => 1000]));
    $attributeB = OwnerContext::withOwner($ownerB, fn (): Attribute => Attribute::create([
        'code' => 'foreign_definition',
        'name' => 'Foreign Definition',
        'type' => AttributeType::Text,
    ]));

    OwnerContext::withOwner($ownerA, function () use ($productA, $attributeB): void {
        expect(fn () => AttributeValue::create([
            'attribute_id' => $attributeB->id,
            'attributable_type' => Product::class,
            'attributable_id' => $productA->id,
            'value' => 'poison',
        ]))->toThrow(AuthorizationException::class);

        // Relation creates must obey the same definition rule.
        expect(fn () => $productA->attributeValues()->create([
            'attribute_id' => $attributeB->id,
            'value' => 'poison',
        ]))->toThrow(AuthorizationException::class);
    });

    expect(OwnerContext::withOwner($ownerA, fn (): int => AttributeValue::query()->count()))->toBe(0)
        ->and(OwnerContext::withOwner($ownerB, fn (): int => AttributeValue::query()->count()))->toBe(0)
        ->and(OwnerContext::withOwner($ownerA, fn (): int => Product::query()->count()))->toBe(1)
        ->and(OwnerContext::withOwner($ownerB, fn (): int => Attribute::query()->count()))->toBe(1);
});

it('rejects missing definitions and missing targets without partial writes', function (): void {
    $owner = User::query()->create(['name' => 'Missing Owner', 'email' => 'missing-owner@example.com', 'password' => 'secret']);

    [$attribute, $product] = OwnerContext::withOwner($owner, fn (): array => [
        Attribute::create(['code' => 'existing_definition', 'name' => 'Existing', 'type' => AttributeType::Text]),
        Product::create(['name' => 'Existing Product', 'price' => 1000]),
    ]);

    OwnerContext::withOwner($owner, function () use ($attribute, $product): void {
        expect(fn () => AttributeValue::create([
            'attribute_id' => (string) Str::uuid(),
            'attributable_type' => Product::class,
            'attributable_id' => $product->id,
            'value' => 'orphan definition',
        ]))->toThrow(AuthorizationException::class);

        expect(fn () => AttributeValue::create([
            'attribute_id' => $attribute->id,
            'attributable_type' => Product::class,
            'attributable_id' => (string) Str::uuid(),
            'value' => 'orphan target',
        ]))->toThrow(AuthorizationException::class);

        // Forged morph targets remain on the allowlist.
        $user = User::query()->create(['name' => 'Stray Target', 'email' => 'value-stray-target@example.com', 'password' => 'secret']);

        expect(fn () => AttributeValue::create([
            'attribute_id' => $attribute->id,
            'attributable_type' => $user->getMorphClass(),
            'attributable_id' => $user->getKey(),
            'value' => 'junk',
        ]))->toThrow(InvalidArgumentException::class, 'products or variants');
    });

    expect(OwnerContext::withOwner($owner, fn (): int => AttributeValue::query()->count()))->toBe(0);
});

it('keeps persisted definition and target identity immutable', function (): void {
    $owner = User::query()->create(['name' => 'Immutable Owner', 'email' => 'immutable-owner@example.com', 'password' => 'secret']);

    [$product, $otherProduct, $variant, $attribute, $otherAttribute] = OwnerContext::withOwner($owner, function (): array {
        $product = Product::create(['name' => 'Immutable Product', 'price' => 1000]);

        return [
            $product,
            Product::create(['name' => 'Immutable Target', 'price' => 1000]),
            Variant::create(['product_id' => $product->id, 'name' => 'Real Variant', 'sku' => 'IMMUTABLE-V1']),
            Attribute::create(['code' => 'immutable_definition', 'name' => 'Immutable Definition', 'type' => AttributeType::Text]),
            Attribute::create(['code' => 'other_definition', 'name' => 'Other Definition', 'type' => AttributeType::Text]),
        ];
    });

    $value = OwnerContext::withOwner($owner, fn (): AttributeValue => AttributeValue::create([
        'attribute_id' => $attribute->id,
        'attributable_type' => Product::class,
        'attributable_id' => $product->id,
        'value' => 'original',
    ]));

    $originalOwner = [$value->owner_type, $value->owner_id];

    OwnerContext::withOwner($owner, function () use ($value, $otherProduct, $variant, $otherAttribute): void {
        expect(fn () => $value->update(['attribute_id' => $otherAttribute->id]))
            ->toThrow(InvalidArgumentException::class, 'immutable');

        expect(fn () => $value->update(['attributable_id' => $otherProduct->id]))
            ->toThrow(InvalidArgumentException::class, 'immutable');

        expect(fn () => $value->update(['attributable_type' => Variant::class]))
            ->toThrow(InvalidArgumentException::class, 'immutable');

        // Direct assignment must not bypass the guard.
        $value->attribute_id = $otherAttribute->id;
        expect(fn () => $value->save())->toThrow(InvalidArgumentException::class, 'immutable');

        $value->attributable_id = $variant->id;
        expect(fn () => $value->save())->toThrow(InvalidArgumentException::class, 'immutable');

        // Supported value edits still work.
        $value->refresh();
        $value->update(['value' => 'updated', 'locale' => 'en']);
    });

    $value->refresh();

    expect($value->attribute_id)->toBe($attribute->id)
        ->and($value->attributable_type)->toBe((new Product)->getMorphClass())
        ->and($value->attributable_id)->toBe($product->id)
        ->and($value->value)->toBe('updated')
        ->and($value->locale)->toBe('en')
        ->and([$value->owner_type, $value->owner_id])->toBe($originalOwner);
});

it('keeps definition and target existence validation when owner enforcement is disabled', function (): void {
    config()->set('products.features.owner.enabled', false);

    $attribute = Attribute::create(['code' => 'unscoped_definition', 'name' => 'Unscoped', 'type' => AttributeType::Text]);
    $product = Product::create(['name' => 'Unscoped Product', 'price' => 1000]);

    expect(fn () => AttributeValue::create([
        'attribute_id' => (string) Str::uuid(),
        'attributable_type' => Product::class,
        'attributable_id' => $product->id,
        'value' => 'orphan definition',
    ]))->toThrow(InvalidArgumentException::class, 'attribute not found');

    expect(fn () => AttributeValue::create([
        'attribute_id' => $attribute->id,
        'attributable_type' => Product::class,
        'attributable_id' => (string) Str::uuid(),
        'value' => 'orphan target',
    ]))->toThrow(InvalidArgumentException::class, 'unable to resolve attributable');

    $value = AttributeValue::create([
        'attribute_id' => $attribute->id,
        'attributable_type' => Product::class,
        'attributable_id' => $product->id,
        'value' => 'kept',
    ]);

    expect($value->exists)->toBeTrue()
        ->and($value->attribute?->id)->toBe($attribute->id);

    expect(fn () => $value->update(['attribute_id' => (string) Str::uuid()]))
        ->toThrow(InvalidArgumentException::class, 'immutable');

    expect($value->fresh()?->attribute_id)->toBe($attribute->id);
});

it('allows global definitions only under the include-global policy', function (): void {
    $owner = User::query()->create(['name' => 'Global Owner', 'email' => 'global-value-owner@example.com', 'password' => 'secret']);

    $globalAttribute = OwnerContext::withOwner(null, fn (): Attribute => Attribute::create([
        'code' => 'global_definition',
        'name' => 'Global Definition',
        'type' => AttributeType::Text,
    ]));

    $product = OwnerContext::withOwner($owner, fn (): Product => Product::create(['name' => 'Global Rule Product', 'price' => 1000]));

    config()->set('products.features.owner.include_global', false);

    OwnerContext::withOwner($owner, function () use ($product, $globalAttribute): void {
        expect(fn () => AttributeValue::create([
            'attribute_id' => $globalAttribute->id,
            'attributable_type' => Product::class,
            'attributable_id' => $product->id,
            'value' => 'blocked global',
        ]))->toThrow(AuthorizationException::class);
    });

    config()->set('products.features.owner.include_global', true);

    $value = OwnerContext::withOwner($owner, fn (): AttributeValue => AttributeValue::create([
        'attribute_id' => $globalAttribute->id,
        'attributable_type' => Product::class,
        'attributable_id' => $product->id,
        'value' => 'permitted global',
    ]));

    // The owner global scope snapshots include_global at boot, so resolve the
    // definition with the owning context's include-global policy explicitly.
    $value->load(['attribute' => fn ($query) => $query->forOwner($owner, includeGlobal: true)]);

    expect($value->owner_id)->toBe((string) $owner->getKey())
        ->and($value->attribute?->id)->toBe($globalAttribute->id)
        ->and($value->typed_value)->toBe('permitted global');

    expect(OwnerContext::withOwner($owner, fn (): int => AttributeValue::query()->count()))->toBe(1);
});

it('preserves typed values and locale isolation through direct models and the canonical action', function (): void {
    $product = Product::create(['name' => 'Typed Product', 'price' => 1000]);

    Attribute::create(['code' => 'integrity_flag', 'name' => 'Integrity Flag', 'type' => AttributeType::Boolean]);
    Attribute::create(['code' => 'integrity_note', 'name' => 'Integrity Note', 'type' => AttributeType::Text]);

    $product->setCustomAttribute('integrity_note', 'English', 'en');
    $product->setCustomAttribute('integrity_note', 'Malay', 'ms');
    $product->setCustomAttribute('integrity_note', 'Default');

    $result = app(ApplyAttributeChanges::class)->execute($product, ['integrity_flag' => true]);

    expect($result->getCustomAttribute('integrity_flag'))->toBeTrue()
        ->and($result->getCustomAttribute('integrity_note', 'en'))->toBe('English')
        ->and($result->getCustomAttribute('integrity_note', 'ms'))->toBe('Malay')
        ->and($result->getCustomAttribute('integrity_note'))->toBe('Default')
        ->and(AttributeValue::query()->count())->toBe(4);

    // Re-setting a locale replaces it without duplicating rows.
    $result->setCustomAttribute('integrity_note', 'Updated English', 'en');

    expect($result->getCustomAttribute('integrity_note', 'en'))->toBe('Updated English')
        ->and(AttributeValue::query()->count())->toBe(4);
});

it('blocks cross-owner, global, and missing-context writes', function (): void {
    $ownerA = User::query()->create(['name' => 'Boundary Owner A', 'email' => 'value-boundary-a@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'Boundary Owner B', 'email' => 'value-boundary-b@example.com', 'password' => 'secret']);

    [$productA, $attributeA] = OwnerContext::withOwner($ownerA, fn (): array => [
        Product::create(['name' => 'Boundary Product A', 'price' => 1000]),
        Attribute::create(['code' => 'boundary_definition_a', 'name' => 'Boundary A', 'type' => AttributeType::Text]),
    ]);

    $valueA = OwnerContext::withOwner($ownerA, fn (): AttributeValue => AttributeValue::create([
        'attribute_id' => $attributeA->id,
        'attributable_type' => Product::class,
        'attributable_id' => $productA->id,
        'value' => 'owned by A',
    ]));

    OwnerContext::withOwner($ownerB, function () use ($valueA): void {
        expect(fn () => $valueA->update(['value' => 'hijacked']))
            ->toThrow(AuthorizationException::class);
    });

    [$globalAttribute, $globalProduct] = OwnerContext::withOwner(null, fn (): array => [
        Attribute::create(['code' => 'boundary_global_definition', 'name' => 'Boundary Global', 'type' => AttributeType::Text]),
        Product::create(['name' => 'Boundary Global Product', 'price' => 1000]),
    ]);

    $globalValue = OwnerContext::withOwner(null, fn (): AttributeValue => AttributeValue::create([
        'attribute_id' => $globalAttribute->id,
        'attributable_type' => Product::class,
        'attributable_id' => $globalProduct->id,
        'value' => 'global',
    ]));

    OwnerContext::withOwner($ownerB, function () use ($globalValue): void {
        expect(fn () => $globalValue->update(['value' => 'hijacked']))
            ->toThrow(AuthorizationException::class);
    });

    $valueA->refresh();
    $globalValue->refresh();

    expect($valueA->value)->toBe('owned by A')
        ->and($globalValue->value)->toBe('global');

    // Without a resolvable owner context, owner-protected creates fail fast.
    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    expect(fn () => AttributeValue::create([
        'attribute_id' => $attributeA->id,
        'attributable_type' => Product::class,
        'attributable_id' => $productA->id,
        'value' => 'no context',
    ]))->toThrow(NoCurrentOwnerException::class);
});

it('rejects tenant attribute values on a global target', function (): void {
    config()->set('products.features.owner.include_global', true);

    $owner = User::query()->create(['name' => 'Global Target Owner', 'email' => 'global-target-owner@example.com', 'password' => 'secret']);

    [$globalAttribute, $globalProduct] = OwnerContext::withOwner(null, fn (): array => [
        Attribute::create(['code' => 'global_target_definition', 'name' => 'Global Target Definition', 'type' => AttributeType::Text]),
        Product::create(['name' => 'Global Target Product', 'price' => 1000]),
    ]);

    OwnerContext::withOwner($owner, function () use ($globalAttribute, $globalProduct, $owner): void {
        expect(fn () => AttributeValue::create([
            'attribute_id' => $globalAttribute->id,
            'attributable_type' => Product::class,
            'attributable_id' => $globalProduct->id,
            'value' => 'tenant poison',
        ]))->toThrow(InvalidArgumentException::class, 'does not match its attributable owner');

        expect(fn () => AttributeValue::create([
            'attribute_id' => $globalAttribute->id,
            'attributable_type' => Product::class,
            'attributable_id' => $globalProduct->id,
            'value' => 'tenant poison',
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]))->toThrow(InvalidArgumentException::class, 'does not match its attributable owner');
    });

    expect(AttributeValue::query()->withoutOwnerScope()->count())->toBe(0);

    // Global values on the global target remain valid from explicit-global context.
    $globalValue = OwnerContext::withOwner(null, fn (): AttributeValue => AttributeValue::create([
        'attribute_id' => $globalAttribute->id,
        'attributable_type' => Product::class,
        'attributable_id' => $globalProduct->id,
        'value' => 'global',
    ]));

    expect([$globalValue->owner_type, $globalValue->owner_id])->toBe([null, null]);

    // Deleting the global target cascades its values through the owner-visible lineage.
    OwnerContext::withOwner(null, fn () => $globalProduct->delete());

    expect(AttributeValue::query()->withoutOwnerScope()->whereKey($globalValue->id)->exists())->toBeFalse()
        ->and(AttributeValue::query()->withoutOwnerScope()->count())->toBe(0);
});

it('requires target-matching attribute value ownership when auto assignment is disabled', function (): void {
    config()->set('products.features.owner.auto_assign_on_create', false);

    $owner = User::query()->create(['name' => 'No Assign Value Owner', 'email' => 'no-assign-value-owner@example.com', 'password' => 'secret']);

    [$product, $attribute] = OwnerContext::withOwner($owner, fn (): array => [
        Product::create([
            'name' => 'No Assign Attribute Target',
            'price' => 1000,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]),
        Attribute::create([
            'code' => 'no_assign_value_definition',
            'name' => 'No Assign Value',
            'type' => AttributeType::Text,
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]),
    ]);

    OwnerContext::withOwner($owner, function () use ($attribute, $owner, $product): void {
        expect(fn () => AttributeValue::create([
            'attribute_id' => $attribute->id,
            'attributable_type' => Product::class,
            'attributable_id' => $product->id,
            'value' => 'implicit',
        ]))->toThrow(InvalidArgumentException::class, 'Supply the parent owner tuple');

        $value = AttributeValue::create([
            'attribute_id' => $attribute->id,
            'attributable_type' => Product::class,
            'attributable_id' => $product->id,
            'value' => 'explicit',
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);

        expect([$value->owner_type, $value->owner_id])->toBe([$owner->getMorphClass(), (string) $owner->getKey()]);
    });

    expect(OwnerContext::withOwner($owner, fn (): int => AttributeValue::query()->count()))->toBe(1);
});
