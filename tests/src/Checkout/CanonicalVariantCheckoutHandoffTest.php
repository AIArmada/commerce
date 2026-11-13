<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Cart\Facades\Cart as CartFacade;
use AIArmada\Checkout\Contracts\CheckoutServiceInterface;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Orders\Actions\CreateOrder;
use AIArmada\Orders\Actions\CreateOrderFromCart;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use Tests\Support\Cart\InMemoryStorage;

function createCanonicalCheckoutVariant(): array
{
    $product = Product::create([
        'name' => 'Checkout Hoodie',
        'slug' => 'checkout-hoodie',
        'price' => 2000,
        'currency' => 'USD',
        'status' => ProductStatus::Active,
        'tracks_inventory' => false,
        'requires_shipping' => true,
        'is_taxable' => true,
        'tax_class' => 'reduced',
        'weight' => 1.5,
        'weight_unit' => 'kg',
        'length' => 20,
        'width' => 10,
        'height' => 5,
        'dimension_unit' => 'cm',
    ]);

    $variant = $product->variants()->create([
        'name' => 'Checkout Hoodie - Large',
        'sku' => 'HOODIE-L',
        'price' => 2500,
        'is_enabled' => true,
    ]);

    return [$product, $variant];
}

it('captures canonical variant identity and shipping data in the checkout snapshot', function (): void {
    [$product, $variant] = createCanonicalCheckoutVariant();

    CartFacade::setIdentifier('canonical-variant-checkout');
    CartFacade::addBuyable($variant, 2);

    $cartId = CartFacade::getId();

    expect($cartId)->not->toBeNull();

    $session = app(CheckoutServiceInterface::class)->startCheckout($cartId);
    $item = array_values($session->cart_snapshot['items'] ?? [])[0] ?? [];

    expect($item['id'] ?? null)->toBe($variant->getKey())
        ->and($item['name'] ?? null)->toBe('Checkout Hoodie - Large')
        ->and($item['associated_model']['class'] ?? null)->toBe(Variant::class)
        ->and($item['associated_model']['id'] ?? null)->toBe($variant->getKey())
        ->and($item['attributes']['purchasable_type'] ?? null)->toBe(Variant::class)
        ->and($item['attributes']['purchasable_id'] ?? null)->toBe($variant->getKey())
        ->and($item['attributes']['product_id'] ?? null)->toBe($product->getKey())
        ->and($item['attributes']['weight'] ?? null)->toBe(1500)
        ->and($item['attributes']['dimensions'] ?? null)->toBe(['length' => 20, 'width' => 10, 'height' => 5])
        ->and($item['attributes']['requires_shipping'] ?? null)->toBeTrue()
        ->and($item['attributes']['is_taxable'] ?? null)->toBeTrue()
        ->and($item['attributes']['tax_class'] ?? null)->toBe('reduced')
        ->and($item['attributes']['currency'] ?? null)->toBe('USD');
});

it('hands the actual variant identity to order creation from a cart', function (): void {
    [, $variant] = createCanonicalCheckoutVariant();

    $cart = new Cart(new InMemoryStorage, 'order-handoff-cart');
    $cart->addBuyable($variant, 2);

    $customer = User::factory()->create();

    $order = (new CreateOrderFromCart(new CreateOrder))->execute($cart, $customer);
    $orderItem = $order->items()->first();

    expect($order->currency)->toBe('USD')
        ->and($orderItem)->not->toBeNull()
        ->and($orderItem->purchasable_id)->toBe($variant->getKey())
        ->and($orderItem->purchasable_type)->toBe(Variant::class)
        ->and($orderItem->name)->toBe('Checkout Hoodie - Large')
        ->and($orderItem->quantity)->toBe(2)
        ->and($orderItem->unit_price)->toBe(2500);
});
