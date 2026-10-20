<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Option;
use AIArmada\Products\Models\OptionValue;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

it('keeps variant parent and tenancy immutable after creation', function (): void {
    $product = Product::create(['name' => 'Immutable Parent', 'price' => 1000]);
    $other = Product::create(['name' => 'Other Parent', 'price' => 1000]);
    $variant = Variant::create(['product_id' => $product->id, 'name' => 'V', 'sku' => 'IMMUT-1']);

    expect(fn () => $variant->update(['product_id' => $other->id]))
        ->toThrow(InvalidArgumentException::class, 'immutable');

    expect(fn () => $variant->update(['owner_id' => (string) Str::uuid()]))
        ->toThrow(InvalidArgumentException::class, 'cannot be reassigned');
});

it('blocks cross-tenant and global variant updates but allows owned ones', function (): void {
    config()->set('products.features.owner.include_global', false);

    $ownerA = User::query()->create(['name' => 'A', 'email' => 'variant-guard-a@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'B', 'email' => 'variant-guard-b@example.com', 'password' => 'secret']);

    $productA = OwnerContext::withOwner($ownerA, fn (): Product => Product::create(['name' => 'Guarded', 'price' => 1000]));
    $variantA = OwnerContext::withOwner($ownerA, fn (): Variant => Variant::create([
        'product_id' => $productA->id, 'name' => 'VA', 'sku' => 'GUARD-A',
    ]));
    $globalProduct = OwnerContext::withOwner(null, fn (): Product => Product::create(['name' => 'Global Guarded', 'price' => 1000]));
    $globalVariant = OwnerContext::withOwner(null, fn (): Variant => Variant::create([
        'product_id' => $globalProduct->id, 'name' => 'VG', 'sku' => 'GUARD-G',
    ]));

    OwnerContext::withOwner($ownerB, function () use ($variantA, $globalVariant): void {
        expect(fn () => $variantA->update(['name' => 'Hijacked']))
            ->toThrow(AuthorizationException::class, 'Cross-owner save blocked');

        expect(fn () => $globalVariant->update(['name' => 'Hijacked Global']))
            ->toThrow(AuthorizationException::class, 'Explicit global owner context is required');
    });

    OwnerContext::withOwner($ownerA, function () use ($variantA): void {
        $variantA->update(['name' => 'Renamed']);

        expect($variantA->fresh()->name)->toBe('Renamed');
    });
});

it('falls back to local stock and logs when inventory lookups fail', function (): void {
    Log::spy();

    // The inventory tables are absent here, so the real service call fails
    // exactly like an inventory outage would.
    $product = Product::create(['name' => 'Outage Product', 'price' => 1000, 'tracks_inventory' => true]);
    $variant = Variant::create(['product_id' => $product->id, 'name' => 'VO', 'sku' => 'OUTAGE-1']);
    $variant->forceFill(['stock' => 7]);

    expect($variant->getStockQuantity())->toBe(7);

    Log::shouldHaveReceived('warning')->once();
});

it('reuses loaded option values across summary calls', function (): void {
    $product = Product::create(['name' => 'Summary Product', 'price' => 1000]);
    $option = Option::create(['product_id' => $product->id, 'name' => 'Color', 'position' => 1]);
    OptionValue::create(['option_id' => $option->id, 'name' => 'Red', 'position' => 1]);
    $variant = Variant::create(['product_id' => $product->id, 'name' => 'VR', 'sku' => 'SUMMARY-1']);
    $variant->optionValues()->sync(OptionValue::query()->pluck('id')->all());

    expect($variant->getOptionSummary())->toBe('Red');

    DB::enableQueryLog();
    DB::flushQueryLog();

    $variant->getOptionSummary();
    $variant->getOptionSummary();

    $queries = DB::getQueryLog();

    DB::disableQueryLog();

    expect($queries)->toBeEmpty();
});

it('requires variant ownership to match its product including global parents', function (): void {
    config()->set('products.features.owner.include_global', true);

    $owner = User::query()->create(['name' => 'Variant Lineage Owner', 'email' => 'variant-lineage-owner@example.com', 'password' => 'secret']);

    $globalProduct = OwnerContext::withOwner(null, fn (): Product => Product::create(['name' => 'Global Variant Parent', 'price' => 1000]));

    // Tenant contexts can read the global product but cannot attach tenant-owned variants.
    OwnerContext::withOwner($owner, function () use ($globalProduct, $owner): void {
        expect(fn () => Variant::create(['product_id' => $globalProduct->id, 'sku' => 'GV-TENANT']))
            ->toThrow(InvalidArgumentException::class, 'does not match its product owner');

        expect(fn () => Variant::create([
            'product_id' => $globalProduct->id,
            'sku' => 'GV-TENANT-EXPLICIT',
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]))->toThrow(InvalidArgumentException::class, 'does not match its product owner');
    });

    expect(Variant::query()->withoutOwnerScope()->count())->toBe(0);

    // Global variants are created from explicit-global context and share the global tuple.
    $globalVariant = OwnerContext::withOwner(null, fn (): Variant => Variant::create([
        'product_id' => $globalProduct->id,
        'name' => 'Global Variant',
        'sku' => 'GV-GLOBAL',
    ]));

    expect([$globalVariant->owner_type, $globalVariant->owner_id])->toBe([null, null]);

    OwnerContext::withOwner(null, fn () => $globalProduct->delete());

    expect(Variant::query()->withoutOwnerScope()->whereKey($globalVariant->id)->exists())->toBeFalse()
        ->and(Variant::query()->withoutOwnerScope()->count())->toBe(0);
});

it('requires explicit variant ownership when auto assignment is disabled', function (): void {
    config()->set('products.features.owner.auto_assign_on_create', false);

    $owner = User::query()->create(['name' => 'Variant No Assign Owner', 'email' => 'variant-no-assign-owner@example.com', 'password' => 'secret']);

    $product = OwnerContext::withOwner($owner, fn (): Product => Product::create([
        'name' => 'No Assign Variant Parent',
        'price' => 1000,
        'owner_type' => $owner->getMorphClass(),
        'owner_id' => $owner->getKey(),
    ]));

    OwnerContext::withOwner($owner, function () use ($owner, $product): void {
        expect(fn () => Variant::create(['product_id' => $product->id, 'sku' => 'NOASSIGN-1']))
            ->toThrow(InvalidArgumentException::class, 'Supply the parent owner tuple');

        $variant = Variant::create([
            'product_id' => $product->id,
            'sku' => 'NOASSIGN-2',
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->getKey(),
        ]);

        expect([$variant->owner_type, $variant->owner_id])->toBe([$owner->getMorphClass(), (string) $owner->getKey()]);
    });

    expect(OwnerContext::withOwner($owner, fn (): int => Variant::query()->where('product_id', $product->id)->count()))->toBe(1);
});
