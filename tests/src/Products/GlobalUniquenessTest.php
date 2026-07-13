<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScopeKey;
use AIArmada\Products\Models\Product;
use Illuminate\Database\QueryException;

it('rejects duplicate global product slugs and skus', function (): void {
    OwnerContext::withOwner(null, function (): void {
        Product::query()->create(['name' => 'Global One', 'slug' => 'shared-global', 'sku' => 'GLOBAL-SKU']);

        expect(fn () => Product::query()->create([
            'name' => 'Global Two',
            'slug' => 'shared-global',
            'sku' => 'GLOBAL-SKU-2',
        ]))->toThrow(QueryException::class);
    });
});

it('allows the same product business keys for different owners', function (): void {
    $ownerA = User::query()->create(['name' => 'Owner A', 'email' => 'owner-a-products@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'Owner B', 'email' => 'owner-b-products@example.com', 'password' => 'secret']);

    $productA = OwnerContext::withOwner($ownerA, fn (): Product => Product::query()->create([
        'name' => 'Owned A', 'slug' => 'shared-owned', 'sku' => 'SHARED-SKU',
    ]));
    $productB = OwnerContext::withOwner($ownerB, fn (): Product => Product::query()->create([
        'name' => 'Owned B', 'slug' => 'shared-owned', 'sku' => 'SHARED-SKU',
    ]));

    expect($productA->owner_scope)->toBe(OwnerScopeKey::forOwner($ownerA))
        ->and($productB->owner_scope)->toBe(OwnerScopeKey::forOwner($ownerB))
        ->and($productA->owner_scope)->not->toBe($productB->owner_scope);
});

it('recomputes the scope key from an unsaved owner tuple and does not expose it to mass assignment', function (): void {
    $owner = User::query()->create(['name' => 'Owner C', 'email' => 'owner-c-products@example.com', 'password' => 'secret']);
    $product = new Product(['name' => 'Draft', 'slug' => 'draft-owner-scope', 'sku' => 'DRAFT-SCOPE']);

    $product->forceFill(['owner_type' => $owner->getMorphClass(), 'owner_id' => $owner->getKey(), 'owner_scope' => 'forged']);
    OwnerContext::withOwner($owner, fn () => $product->save());

    expect($product->owner_scope)->toBe(OwnerScopeKey::forOwner($owner))
        ->and($product->getFillable())->not->toContain('owner_scope');
});
