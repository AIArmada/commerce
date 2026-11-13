<?php

declare(strict_types=1);

use AIArmada\Cart\Cart;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryService;
use AIArmada\Orders\Actions\CreateOrder;
use AIArmada\Orders\Actions\CreateOrderFromCart;
use AIArmada\Orders\Support\PackageUnit;
use AIArmada\Products\Actions\CreateCompositeProduct;
use AIArmada\Products\Enums\ProductStatus;
use AIArmada\Products\Enums\ProductType;
use AIArmada\Products\Models\Product;
use Tests\Support\Cart\InMemoryStorage;

/**
 * @param  array<string, mixed>  $attributes
 */
function createCompositeOrderProduct(array $attributes = [], int $stock = 10): Product
{
    static $sequence = 0;

    $sequence++;

    $product = Product::query()->create([
        'name' => 'Composite Order Component ' . $sequence,
        'slug' => 'composite-order-component-' . $sequence,
        'sku' => 'COC-' . $sequence,
        'type' => ProductType::Simple,
        'status' => ProductStatus::Active,
        'price' => 500,
        'currency' => 'USD',
        'requires_shipping' => true,
        'tracks_inventory' => true,
        'is_taxable' => true,
        'weight' => 0.2,
        'weight_unit' => 'kg',
        'length' => 12,
        'width' => 8,
        'height' => 4,
        'dimension_unit' => 'cm',
        ...$attributes,
    ]);

    $location = InventoryLocation::query()->firstOrCreate(
        ['code' => 'COMPOSITE-ORDER-WH'],
        ['name' => 'Composite Order WH', 'priority' => 100],
    );

    app(InventoryService::class)->receive($product, $location->id, $stock);

    return $product;
}

function createCompositeOrderPackage(Product $mouseA, Product $mouseB): Product
{
    return app(CreateCompositeProduct::class)->handle([
        'name' => 'Mouse Duo Package',
        'slug' => 'mouse-duo-package',
        'sku' => 'MDP-1',
        'price' => 4000,
        'currency' => 'USD',
        'status' => ProductStatus::Active,
    ], [
        ['component_product_id' => (string) $mouseA->getKey(), 'quantity' => 2],
        ['component_product_id' => (string) $mouseB->getKey(), 'quantity' => 1],
    ]);
}

it('freezes the package composition on the order line when creating an order from a cart', function (): void {
    $mouseA = createCompositeOrderProduct();
    $mouseB = createCompositeOrderProduct(['price' => 700]);
    $package = createCompositeOrderPackage($mouseA, $mouseB);

    $cart = new Cart(new InMemoryStorage, 'composite-order-cart');
    $cart->addBuyable($package, 2);

    $order = (new CreateOrderFromCart(new CreateOrder))->execute($cart, User::factory()->create());

    $orderItem = $order->items()->first();

    expect($order->currency)->toBe('USD')
        ->and($order->items()->count())->toBe(1)
        ->and($orderItem->purchasable_id)->toBe($package->getKey())
        ->and($orderItem->purchasable_type)->toBe($package->getMorphClass())
        ->and($orderItem->quantity)->toBe(2)
        ->and($orderItem->unit_price)->toBe(4000);

    $components = $orderItem->metadata['components'] ?? [];

    expect($components)->toHaveCount(2)
        ->and($components[0])->toHaveKeys([
            'product_id',
            'variant_id',
            'inventoryable_type',
            'inventoryable_id',
            'quantity',
            'sku',
            'name',
            'weight',
            'dimensions',
            'requires_shipping',
            'tracks_inventory',
        ])
        ->and($components[0]['product_id'])->toBe((string) $mouseA->getKey())
        ->and($components[0]['inventoryable_type'])->toBe($mouseA->getMorphClass())
        ->and($components[0]['inventoryable_id'])->toBe((string) $mouseA->getKey())
        ->and($components[0]['quantity'])->toBe(2)
        ->and($components[0]['weight'])->toBe(200)
        ->and($components[0]['dimensions'])->toBe(['length' => 12, 'width' => 8, 'height' => 4])
        ->and($components[1]['quantity'])->toBe(1);
});

it('keeps package and standalone lines distinct when both are in the cart', function (): void {
    $mouseA = createCompositeOrderProduct();
    $mouseB = createCompositeOrderProduct(['price' => 700]);
    $standalone = createCompositeOrderProduct(['sku' => 'STANDALONE-1']);
    $package = createCompositeOrderPackage($mouseA, $mouseB);

    $cart = new Cart(new InMemoryStorage, 'composite-order-mixed-cart');
    $cart->addBuyable($package, 1);
    $cart->addBuyable($standalone, 2);

    $order = (new CreateOrderFromCart(new CreateOrder))->execute($cart, User::factory()->create());

    $items = $order->items()->orderBy('name')->get();

    expect($items)->toHaveCount(2);

    $packageItem = $items->firstWhere('purchasable_id', $package->getKey());
    $standaloneItem = $items->firstWhere('purchasable_id', $standalone->getKey());

    expect($packageItem)->not->toBeNull()
        ->and($standaloneItem)->not->toBeNull()
        ->and($packageItem->metadata['components'] ?? [])->toHaveCount(2)
        ->and($standaloneItem->metadata['components'] ?? null)->toBeNull()
        ->and($order->subtotal)->toBe(4000 + 1000);
});

it('requires cart review when a direct order would use an outdated package price', function (): void {
    $package = createCompositeOrderPackage(createCompositeOrderProduct(), createCompositeOrderProduct());
    $cart = new Cart(new InMemoryStorage, 'stale-composite-order-cart');
    $cart->addBuyable($package);
    $package->update(['price' => 3500]);

    expect(fn () => (new CreateOrderFromCart(new CreateOrder))->execute($cart, User::factory()->create()))
        ->toThrow(InvalidArgumentException::class, 'The composite package price has changed.');
});

it('records the cart discount and tax allocated to a package line for unit refunds', function (): void {
    $package = createCompositeOrderPackage(createCompositeOrderProduct(), createCompositeOrderProduct());
    $cart = new Cart(new InMemoryStorage, 'composite-order-refund-basis');
    $cart->addBuyable($package, 2);
    $cart->addDiscount('promotion', '101');
    $cart->addShipping('shipping', 500);
    $cart->addTax('tax', '99');

    $order = (new CreateOrderFromCart(new CreateOrder))->execute($cart, User::factory()->create());
    $item = $order->items()->firstOrFail();

    expect($item->discount_amount)->toBe(101)
        ->and($item->tax_amount)->toBe(99)
        ->and(new PackageUnit($item, 1)->refundAmount())->toBe(4000)
        ->and(new PackageUnit($item, 2)->refundAmount())->toBe(3998)
        ->and($order->grand_total)->toBe(8498);
});
