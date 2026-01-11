<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\FilamentCart\Models\Cart;
use AIArmada\FilamentCart\Resources\CartResource;
use AIArmada\FilamentProducts\Resources\ProductResource;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('filament plugin navigation badges are owner-scoped (no cross-tenant aggregation)', function (): void {
    // Defensive: ensure the Filament Cart snapshot models actually scope by owner.
    config()->set('filament-cart.owner.enabled', true);
    config()->set('filament-cart.owner.include_global', false);

    $ownerA = \App\Models\User::factory()->create();
    $ownerB = \App\Models\User::factory()->create();

    OwnerContext::withOwner($ownerA, function () use ($ownerA): void {
        Product::create([
            'name' => 'iPhone 15 Pro',
            'sku' => 'IP15-PRO-A',
            'price' => 539900,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
        ]);

        Cart::create([
            'owner_type' => $ownerA->getMorphClass(),
            'owner_id' => $ownerA->getKey(),
            'identifier' => 'cart-1',
            'instance' => 'default',
            'currency' => 'MYR',
            'items_count' => 0,
            'quantity' => 0,
            'subtotal' => 0,
            'total' => 0,
            'savings' => 0,
        ]);
    });

    OwnerContext::withOwner($ownerB, function () use ($ownerB): void {
        Product::create([
            'name' => 'Nike Air Jordan 1',
            'sku' => 'AJ1-B',
            'price' => 45900,
            'currency' => 'MYR',
            'status' => ProductStatus::Active,
        ]);

        Cart::create([
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
            'identifier' => 'cart-1',
            'instance' => 'default',
            'currency' => 'MYR',
            'items_count' => 0,
            'quantity' => 0,
            'subtotal' => 0,
            'total' => 0,
            'savings' => 0,
        ]);

        Cart::create([
            'owner_type' => $ownerB->getMorphClass(),
            'owner_id' => $ownerB->getKey(),
            'identifier' => 'cart-2',
            'instance' => 'default',
            'currency' => 'MYR',
            'items_count' => 0,
            'quantity' => 0,
            'subtotal' => 0,
            'total' => 0,
            'savings' => 0,
        ]);
    });

    OwnerContext::withOwner($ownerA, function (): void {
        expect(ProductResource::getNavigationBadge())->toBe('1');
        expect(CartResource::getNavigationBadge())->toBe('1');
    });

    OwnerContext::withOwner($ownerB, function (): void {
        expect(ProductResource::getNavigationBadge())->toBe('1');
        expect(CartResource::getNavigationBadge())->toBe('2');
    });
});
