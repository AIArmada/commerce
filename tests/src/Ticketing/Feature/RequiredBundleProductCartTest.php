<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use AIArmada\Ticketing\Actions\AddTicketTypeToCartAction;
use AIArmada\Ticketing\Models\TicketType;
use Tests\Support\Cart\InMemoryStorage;

it('adds required variant merchandise with the real variant identity and reconciles repeated ticket additions', function (): void {
    $product = Product::create([
        'name' => 'Event T-Shirt',
        'slug' => 'event-t-shirt',
        'price' => 1000,
        'currency' => 'USD',
        'status' => ProductStatus::Active,
        'tracks_inventory' => false,
    ]);

    $variant = $product->variants()->create([
        'name' => 'Large Tee',
        'sku' => 'TEE-L',
        'price' => 1200,
        'is_enabled' => true,
    ]);

    $ticketType = TicketType::factory()->create([
        'status' => 'active',
        'visibility' => 'public',
        'price' => 5000,
        'currency' => 'USD',
    ]);

    $ticketType->bundleProducts()->create([
        'product_type' => Product::class,
        'product_id' => $product->getKey(),
        'variant_type' => Variant::class,
        'variant_id' => $variant->getKey(),
        'quantity' => 2,
        'inclusion_mode' => 'required',
    ]);

    $cart = new Cart(new InMemoryStorage, 'ticket-bundle-cart');
    $action = app(AddTicketTypeToCartAction::class);

    $action->handle($cart, $ticketType, 1);

    $bundleLineId = sprintf('ticket-bundle:%s:%s:%s', $ticketType->getKey(), Variant::class, $variant->getKey());
    $bundleLine = $cart->get($bundleLineId);

    expect($bundleLine)->not->toBeNull()
        ->and($bundleLine->name)->toBe('Large Tee')
        ->and($bundleLine->price)->toBe(1200)
        ->and($bundleLine->quantity)->toBe(2)
        ->and($bundleLine->getAttribute('purchasable_id'))->toBe($variant->getKey())
        ->and($bundleLine->getAttribute('purchasable_type'))->toBe(Variant::class)
        ->and($bundleLine->getAttribute('product_id'))->toBe($product->getKey());

    $action->handle($cart, $ticketType, 1);

    expect($cart->get($ticketType->getKey())?->quantity)->toBe(2)
        ->and($cart->get($bundleLineId)?->quantity)->toBe(4);

    $cart->addBuyable($variant, 1);

    expect($cart->get($variant->getKey())?->quantity)->toBe(1)
        ->and($cart->get($bundleLineId)?->quantity)->toBe(4)
        ->and($cart->countItems())->toBe(3);
});
