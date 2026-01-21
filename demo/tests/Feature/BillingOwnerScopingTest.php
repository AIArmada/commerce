<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns 404 when viewing a single-product checkout for another owner', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $productA = OwnerContext::withOwner($ownerA, function (): Product {
        return Product::create([
            'name' => 'iPhone 15 Pro',
            'sku' => 'IP15-PRO-001',
            'price' => 539900,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
        ]);
    });

    OwnerContext::withOwner($ownerB, function () use ($productA): void {
        $this->get('/checkout/single/'.$productA->slug)
            ->assertNotFound();
    });
});

it('returns 404 when posting checkout for a product_id belonging to another owner', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $productA = OwnerContext::withOwner($ownerA, function (): Product {
        return Product::create([
            'name' => 'Nike Air Jordan 1',
            'sku' => 'AJ1-001',
            'price' => 45900,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
        ]);
    });

    OwnerContext::withOwner($ownerB, function () use ($productA): void {
        $this->post('/checkout/single', [
            'chip_token' => 'demo-token',
            'product_id' => (string) $productA->id,
            'email' => 'guest@example.com',
        ])->assertNotFound();
    });
});
