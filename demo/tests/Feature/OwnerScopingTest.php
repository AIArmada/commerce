<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('products are owner-scoped (read isolation + write guard)', function (): void {
    $ownerA = \App\Models\User::factory()->create();
    $ownerB = \App\Models\User::factory()->create();

    $productA = OwnerContext::withOwner($ownerA, function () {
        return Product::create([
            'name' => 'iPhone 15 Pro',
            'sku' => 'IP15-PRO-001',
            'price' => 539900,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
        ]);
    });

    $productB = OwnerContext::withOwner($ownerB, function () {
        return Product::create([
            'name' => 'Nike Air Jordan 1',
            'sku' => 'AJ1-001',
            'price' => 45900,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
        ]);
    });

    OwnerContext::withOwner($ownerA, function () use ($productA, $productB): void {
        $ids = Product::query()->pluck('id')->all();

        expect($ids)->toContain($productA->id);
        expect($ids)->not->toContain($productB->id);
    });

    OwnerContext::withOwner($ownerB, function () use ($productA, $productB): void {
        $ids = Product::query()->pluck('id')->all();

        expect($ids)->toContain($productB->id);
        expect($ids)->not->toContain($productA->id);
    });

    OwnerContext::withOwner($ownerA, function () use ($ownerB): void {
        expect(fn () => Product::create([
            'name' => 'Cross-tenant product',
            'sku' => 'X-TENANT',
            'price' => 1000,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
        ]))->toThrow(\InvalidArgumentException::class);
    });
});
