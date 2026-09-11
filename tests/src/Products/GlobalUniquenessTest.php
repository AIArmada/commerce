<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('rejects duplicate global product slugs and skus', function (): void {
    OwnerContext::withOwner(null, function (): void {
        Product::withoutEvents(static fn (): Product => Product::query()->create([
            'name' => 'Global One',
            'slug' => 'shared-global',
            'sku' => 'GLOBAL-SKU',
        ]));

        expect(fn () => Product::withoutEvents(static fn (): Product => Product::query()->create([
            'name' => 'Global Two',
            'slug' => 'shared-global',
            'sku' => 'GLOBAL-SKU-2',
        ])))->toThrow(QueryException::class);
    });
});

it('resolves same-owner slug retries through createOrFirst', function (): void {
    $owner = User::query()->create([
        'name' => 'Idempotent Owner',
        'email' => 'idempotent-owner-products@example.com',
        'password' => 'secret',
    ]);

    $first = OwnerContext::withOwner($owner, fn (): Product => Product::query()->createOrFirst(
        ['slug' => 'idempotent-owner-slug'],
        ['name' => 'First Product'],
    ));
    $second = OwnerContext::withOwner($owner, fn (): Product => Product::query()->createOrFirst(
        ['slug' => 'idempotent-owner-slug'],
        ['name' => 'Retry Product'],
    ));

    $count = OwnerContext::withOwner($owner, static fn (): int => Product::query()
        ->where('slug', 'idempotent-owner-slug')
        ->count());

    expect($second->is($first))->toBeTrue()
        ->and($count)->toBe(1);
});

it('scopes product slugs and skus by the owner tuple', function (): void {
    $ownerA = User::query()->create(['name' => 'Owner A', 'email' => 'owner-a-products@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'Owner B', 'email' => 'owner-b-products@example.com', 'password' => 'secret']);

    $productA = OwnerContext::withOwner($ownerA, fn (): Product => Product::query()->create([
        'name' => 'Owned A', 'slug' => 'shared-owned', 'sku' => 'SHARED-SKU',
    ]));

    $productB = OwnerContext::withOwner($ownerB, fn (): Product => Product::query()->create([
        'name' => 'Owned B', 'slug' => 'shared-owned', 'sku' => 'SHARED-SKU',
    ]));

    expect($productA->owner_type)->toBe($ownerA->getMorphClass())
        ->and($productA->owner_id)->toBe($ownerA->getKey())
        ->and($productB->owner_type)->toBe($ownerB->getMorphClass())
        ->and($productB->owner_id)->toBe($ownerB->getKey())
        ->and($productA->slug)->toBe($productB->slug)
        ->and($productB->sku)->toBe('SHARED-SKU');
});

it('removes the superseded identity columns and installs tuple indexes', function (): void {
    foreach (['products', 'product_variants', 'product_collections', 'product_attribute_groups', 'product_attributes', 'product_attribute_sets'] as $tableName) {
        expect(Schema::hasColumn($tableName, 'owner_scope'))->toBeFalse();
    }

    expect(Schema::hasColumn('product_categories', 'owner_scope'))->toBeFalse()
        ->and(Schema::hasColumn('product_categories', 'parent_scope'))->toBeFalse()
        ->and(Schema::hasIndex('products', 'products_slug_owner_unique'))->toBeTrue()
        ->and(Schema::hasIndex('products', 'products_slug_global_unique'))->toBeTrue()
        ->and(Schema::hasIndex('products', 'products_sku_owner_unique'))->toBeTrue()
        ->and(Schema::hasIndex('products', 'products_sku_global_unique'))->toBeTrue();
});
