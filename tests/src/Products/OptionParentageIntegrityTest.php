<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Exceptions\NoCurrentOwnerException;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('rejects same-owner reparenting of options and values without side effects', function (): void {
    $product = Product::create(['name' => 'Origin Product', 'price' => 1000]);
    $otherProduct = Product::create(['name' => 'Target Product', 'price' => 1000]);

    $option = Option::create(['product_id' => $product->id, 'name' => 'Color']);
    $otherOption = Option::create(['product_id' => $otherProduct->id, 'name' => 'Size']);
    $value = OptionValue::create(['option_id' => $option->id, 'name' => 'Red']);

    $variant = Variant::create(['product_id' => $product->id, 'name' => 'Red Variant', 'sku' => 'PARENT-1']);
    $variant->optionValues()->sync([$value->id]);

    $optionOwner = [$option->owner_type, $option->owner_id];
    $valueOwner = [$value->owner_type, $value->owner_id];

    expect(fn () => $option->update(['product_id' => $otherProduct->id]))
        ->toThrow(InvalidArgumentException::class, 'immutable');

    expect(fn () => $value->update(['option_id' => $otherOption->id]))
        ->toThrow(InvalidArgumentException::class, 'immutable');

    // Direct attribute assignment must not bypass the guard either.
    $option->product_id = $otherProduct->id;
    expect(fn () => $option->save())->toThrow(InvalidArgumentException::class, 'immutable');

    $value->option_id = $otherOption->id;
    expect(fn () => $value->save())->toThrow(InvalidArgumentException::class, 'immutable');

    $option->refresh();
    $value->refresh();

    expect($option->product_id)->toBe($product->id)
        ->and($value->option_id)->toBe($option->id)
        ->and([$option->owner_type, $option->owner_id])->toBe($optionOwner)
        ->and([$value->owner_type, $value->owner_id])->toBe($valueOwner)
        ->and($option->values()->count())->toBe(1)
        ->and($value->variants()->count())->toBe(1)
        ->and(Option::query()->where('product_id', $otherProduct->id)->count())->toBe(1)
        ->and(OptionValue::query()->where('option_id', $otherOption->id)->count())->toBe(0);
});

it('rejects cross-owner reparenting attempts from the owning context', function (): void {
    $ownerA = User::query()->create(['name' => 'Parent Owner A', 'email' => 'option-parent-a@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'Parent Owner B', 'email' => 'option-parent-b@example.com', 'password' => 'secret']);

    $productA = OwnerContext::withOwner($ownerA, fn (): Product => Product::create(['name' => 'A Product', 'price' => 1000]));
    $productB = OwnerContext::withOwner($ownerB, fn (): Product => Product::create(['name' => 'B Product', 'price' => 1000]));

    $optionA = OwnerContext::withOwner($ownerA, fn (): Option => Option::create(['product_id' => $productA->id, 'name' => 'A Option']));
    $optionB = OwnerContext::withOwner($ownerB, fn (): Option => Option::create(['product_id' => $productB->id, 'name' => 'B Option']));
    $valueA = OwnerContext::withOwner($ownerA, fn (): OptionValue => OptionValue::create(['option_id' => $optionA->id, 'name' => 'A Value']));

    OwnerContext::withOwner($ownerA, function () use ($optionA, $valueA, $productB, $optionB): void {
        $optionA->product_id = $productB->id;
        expect(fn () => $optionA->save())->toThrow(InvalidArgumentException::class, 'immutable');

        $valueA->option_id = $optionB->id;
        expect(fn () => $valueA->save())->toThrow(InvalidArgumentException::class, 'immutable');
    });

    $freshOption = OwnerContext::withOwner($ownerA, fn (): ?Option => Option::query()->whereKey($optionA->id)->first());
    $freshValue = OwnerContext::withOwner($ownerA, fn (): ?OptionValue => OptionValue::query()->whereKey($valueA->id)->first());

    expect($freshOption?->product_id)->toBe($productA->id)
        ->and($freshValue?->option_id)->toBe($optionA->id)
        ->and($freshOption?->owner_id)->toBe((string) $ownerA->getKey())
        ->and($freshValue?->owner_id)->toBe((string) $ownerA->getKey());

    $foreignState = OwnerContext::withOwner($ownerB, fn (): array => [
        Option::query()->where('product_id', $productB->id)->count(),
        OptionValue::query()->where('option_id', $optionB->id)->count(),
    ]);

    expect($foreignState)->toBe([1, 0]);
});

it('keeps parent identity immutable when owner enforcement is disabled', function (): void {
    config()->set('products.features.owner.enabled', false);

    $product = Product::create(['name' => 'Unscoped Origin', 'price' => 1000]);
    $otherProduct = Product::create(['name' => 'Unscoped Target', 'price' => 1000]);

    $option = Option::create(['product_id' => $product->id, 'name' => 'Color']);
    $otherOption = Option::create(['product_id' => $otherProduct->id, 'name' => 'Size']);
    $value = OptionValue::create(['option_id' => $option->id, 'name' => 'Red']);

    expect(fn () => $option->update(['product_id' => $otherProduct->id]))
        ->toThrow(InvalidArgumentException::class, 'immutable');

    expect(fn () => $value->update(['option_id' => $otherOption->id]))
        ->toThrow(InvalidArgumentException::class, 'immutable');

    expect($option->fresh()?->product_id)->toBe($product->id)
        ->and($value->fresh()?->option_id)->toBe($option->id);
});

it('rejects options and values with missing parents or broken lineage', function (): void {
    expect(fn () => Option::create(['product_id' => (string) Str::uuid(), 'name' => 'Orphan Option']))
        ->toThrow(AuthorizationException::class);

    expect(fn () => OptionValue::create(['option_id' => (string) Str::uuid(), 'name' => 'Orphan Value']))
        ->toThrow(AuthorizationException::class);

    expect(Option::query()->count())->toBe(0)
        ->and(OptionValue::query()->count())->toBe(0);

    // Lineage: a value cannot attach to an option whose product is gone.
    config()->set('products.features.owner.include_global', true);

    $legacyOption = OwnerContext::withOwner(null, fn (): Option => Option::withoutEvents(
        fn (): Option => Option::query()->create([
            'product_id' => (string) Str::uuid(),
            'name' => 'Dangling Option',
            'owner_type' => null,
            'owner_id' => null,
        ]),
    ));

    expect(fn () => OptionValue::create(['option_id' => $legacyOption->id, 'name' => 'Orphan Value']))
        ->toThrow(AuthorizationException::class);
});

it('keeps parent existence validation when owner enforcement is disabled', function (): void {
    config()->set('products.features.owner.enabled', false);

    expect(fn () => Option::create(['product_id' => (string) Str::uuid(), 'name' => 'Orphan Option']))
        ->toThrow(InvalidArgumentException::class, 'product not found');

    expect(fn () => OptionValue::create(['option_id' => (string) Str::uuid(), 'name' => 'Orphan Value']))
        ->toThrow(InvalidArgumentException::class, 'option not found');
});

it('still allows normal option and value edits', function (): void {
    $product = Product::create(['name' => 'Editable Product', 'price' => 1000]);
    $option = Option::create(['product_id' => $product->id, 'name' => 'Color']);
    $value = OptionValue::create(['option_id' => $option->id, 'name' => 'Red']);

    $option->update(['name' => 'Finish', 'display_name' => 'Choose a finish']);
    $value->update(['name' => 'Crimson', 'swatch_color' => '#FF0000', 'position' => 2]);

    $option->refresh();
    $value->refresh();

    expect($option->name)->toBe('Finish')
        ->and($option->display_name)->toBe('Choose a finish')
        ->and($value->name)->toBe('Crimson')
        ->and($value->swatch_color)->toBe('#FF0000')
        ->and($value->position)->toBe(2)
        ->and($value->option_id)->toBe($option->id);
});

it('applies the same parent rules to relation creates', function (): void {
    $ownerA = User::query()->create(['name' => 'Relation Owner A', 'email' => 'option-relation-a@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'Relation Owner B', 'email' => 'option-relation-b@example.com', 'password' => 'secret']);

    $productA = OwnerContext::withOwner($ownerA, fn (): Product => Product::create(['name' => 'Relation Product A', 'price' => 1000]));
    $productB = OwnerContext::withOwner($ownerB, fn (): Product => Product::create(['name' => 'Relation Product B', 'price' => 1000]));
    $optionB = OwnerContext::withOwner($ownerB, fn (): Option => Option::create(['product_id' => $productB->id, 'name' => 'B Option']));

    $value = OwnerContext::withOwner($ownerA, fn (): OptionValue => $productA->options()->create(['name' => 'Size'])->values()->create(['name' => 'Large']));

    expect($value->option_id)->not->toBeNull()
        ->and(OwnerContext::withOwner($ownerA, fn (): int => OptionValue::query()->whereKey($value->id)->count()))->toBe(1);

    OwnerContext::withOwner($ownerA, function () use ($optionB): void {
        expect(fn () => OptionValue::create(['option_id' => $optionB->id, 'name' => 'Hijack']))
            ->toThrow(AuthorizationException::class);
    });

    expect(OwnerContext::withOwner($ownerA, fn (): int => OptionValue::query()->count()))->toBe(1);
});

it('requires explicit global context for children of a global aggregate', function (): void {
    $globalProduct = OwnerContext::withOwner(null, fn (): Product => Product::create(['name' => 'Global Product', 'price' => 1000]));

    $owner = User::query()->create(['name' => 'Context Owner', 'email' => 'option-context@example.com', 'password' => 'secret']);

    // include_global off: the global product is not reachable at all.
    config()->set('products.features.owner.include_global', false);

    OwnerContext::withOwner($owner, function () use ($globalProduct): void {
        expect(fn () => Option::create(['product_id' => $globalProduct->id, 'name' => 'Scoped Option']))
            ->toThrow(AuthorizationException::class);
    });

    // include_global on: global records are readable, but the tenant cannot
    // attach its own option/value lineage to the global aggregate.
    config()->set('products.features.owner.include_global', true);

    OwnerContext::withOwner($owner, function () use ($globalProduct, $owner): void {
        expect(fn () => Option::create(['product_id' => $globalProduct->id, 'name' => 'Scoped Option']))
            ->toThrow(InvalidArgumentException::class, 'does not match its product owner');

        expect(fn () => Option::create([
            'product_id' => $globalProduct->id,
            'name' => 'Scoped Option',
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]))->toThrow(InvalidArgumentException::class, 'does not match its product owner');
    });

    expect(OwnerContext::withOwner($owner, fn (): int => Option::query()->withoutOwnerScope()->count()))->toBe(0);

    // Children of a global aggregate are created from explicit-global context
    // and share the global tuple, so the parent cascade can always see them.
    [$option, $value, $variant] = OwnerContext::withOwner(null, function () use ($globalProduct): array {
        $option = Option::create(['product_id' => $globalProduct->id, 'name' => 'Global Option']);
        $value = OptionValue::create(['option_id' => $option->id, 'name' => 'Global Value']);
        $variant = Variant::create(['product_id' => $globalProduct->id, 'name' => 'Global Variant', 'sku' => 'GLOBAL-LINEAGE']);
        $variant->optionValues()->sync([$value->id]);

        return [$option, $value, $variant];
    });

    expect([$option->owner_type, $option->owner_id])->toBe([null, null])
        ->and([$value->owner_type, $value->owner_id])->toBe([null, null])
        ->and([$variant->owner_type, $variant->owner_id])->toBe([null, null]);

    // Global records can only be written from an explicit global context.
    OwnerContext::withOwner($owner, function () use ($globalProduct): void {
        expect(fn () => $globalProduct->update(['name' => 'Hijacked']))
            ->toThrow(AuthorizationException::class);
    });

    OwnerContext::withOwner(null, function () use ($globalProduct): void {
        $globalProduct->update(['name' => 'Global Renamed']);
    });

    expect($globalProduct->fresh()?->name)->toBe('Global Renamed');

    // Deleting the global aggregate in explicit-global context leaves no
    // descendant rows, including variant pivots.
    OwnerContext::withOwner(null, function () use ($globalProduct): void {
        $globalProduct->delete();
    });

    expect(Option::query()->withoutOwnerScope()->whereKey($option->id)->exists())->toBeFalse()
        ->and(OptionValue::query()->withoutOwnerScope()->whereKey($value->id)->exists())->toBeFalse()
        ->and(Variant::query()->withoutOwnerScope()->whereKey($variant->id)->exists())->toBeFalse()
        ->and(DB::table(config('products.database.tables.variant_options', 'product_variant_options'))->count())->toBe(0)
        ->and(Option::query()->withoutOwnerScope()->count())->toBe(0)
        ->and(OptionValue::query()->withoutOwnerScope()->count())->toBe(0);
});

it('requires explicit-global context for global children even when auto assignment is disabled', function (): void {
    config()->set('products.features.owner.include_global', true);
    config()->set('products.features.owner.auto_assign_on_create', false);

    $owner = User::query()->create(['name' => 'No Assign Owner', 'email' => 'option-no-assign@example.com', 'password' => 'secret']);

    $globalProduct = OwnerContext::withOwner(null, fn (): Product => Product::create(['name' => 'Global No Assign', 'price' => 1000]));

    OwnerContext::withOwner($owner, function () use ($globalProduct): void {
        expect(fn () => Option::create(['product_id' => $globalProduct->id, 'name' => 'No Assign Option']))
            ->toThrow(AuthorizationException::class, 'Explicit global owner context is required');
    });

    expect(Option::query()->withoutOwnerScope()->count())->toBe(0);
});

it('requires explicit child ownership when auto assignment is disabled', function (): void {
    config()->set('products.features.owner.auto_assign_on_create', false);

    $owner = User::query()->create(['name' => 'Explicit Child Owner', 'email' => 'option-explicit-child@example.com', 'password' => 'secret']);

    $product = OwnerContext::withOwner($owner, fn (): Product => Product::create([
        'name' => 'Explicit Child Product',
        'price' => 1000,
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]));

    OwnerContext::withOwner($owner, function () use ($product, $owner): void {
        // Omitting the owner tuple would create a global child that the
        // tenant-owned parent cascade can never see.
        expect(fn () => Option::create(['product_id' => $product->id, 'name' => 'Implicit Option']))
            ->toThrow(InvalidArgumentException::class, 'Supply the parent owner tuple');

        $option = Option::create([
            'product_id' => $product->id,
            'name' => 'Explicit Option',
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);

        expect(fn () => OptionValue::create(['option_id' => $option->id, 'name' => 'Implicit Value']))
            ->toThrow(InvalidArgumentException::class, 'Supply the parent owner tuple');

        $value = OptionValue::create([
            'option_id' => $option->id,
            'name' => 'Explicit Value',
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);

        expect([$option->owner_type, $option->owner_id])->toBe([$owner->getMorphClass(), (string) $owner->getKey()])
            ->and([$value->owner_type, $value->owner_id])->toBe([$owner->getMorphClass(), (string) $owner->getKey()]);
    });

    expect(OwnerContext::withOwner($owner, fn (): int => Option::query()->count()))->toBe(1)
        ->and(OwnerContext::withOwner($owner, fn (): int => OptionValue::query()->count()))->toBe(1);
});

it('rejects owner-protected creates without a resolvable owner context', function (): void {
    $owner = User::query()->create(['name' => 'Missing Context Owner', 'email' => 'option-missing-context@example.com', 'password' => 'secret']);

    $globalProduct = OwnerContext::withOwner(null, fn (): Product => Product::create(['name' => 'Global Missing Context', 'price' => 1000]));

    $globalOption = OwnerContext::withOwner(null, fn (): Option => Option::create([
        'product_id' => $globalProduct->id,
        'name' => 'Global Missing Context Option',
    ]));

    expect($globalOption->owner_type)->toBeNull();

    // Without a resolvable owner context, owner-protected creates fail fast.
    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    expect(fn () => Option::create(['product_id' => $globalProduct->id, 'name' => 'No Context Option']))
        ->toThrow(NoCurrentOwnerException::class);

    expect(fn () => OptionValue::create(['option_id' => $globalOption->id, 'name' => 'No Context Value']))
        ->toThrow(NoCurrentOwnerException::class);

    expect(Option::query()->withoutOwnerScope()->count())->toBe(1)
        ->and(OptionValue::query()->withoutOwnerScope()->count())->toBe(0)
        ->and(OwnerContext::withOwner($owner, fn (): int => Option::query()->count()))->toBe(0);
});
