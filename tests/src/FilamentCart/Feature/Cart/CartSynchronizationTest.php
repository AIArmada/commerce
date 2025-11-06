<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\CartCondition as CoreCondition;
use AIArmada\Cart\Facades\Cart as CartFacade;
use AIArmada\FilamentCart\Models\Cart as CartSnapshot;
use AIArmada\FilamentCart\Models\CartCondition;
use AIArmada\FilamentCart\Models\CartItem;

beforeEach(function (): void {
    CartFacade::clear();
});

describe('cart synchronization', function (): void {
    it('creates a normalized snapshot when the first item is added', function (): void {
        CartFacade::add('sku-001', 'First Product', 1500, 2, ['color' => 'red']);

        $snapshot = CartSnapshot::first();
        expect($snapshot)->not->toBeNull();
        expect($snapshot->items_count)->toBe(1);
        expect($snapshot->quantity)->toBe(2);
        expect($snapshot->subtotal)->toBe(3000);
        expect($snapshot->total)->toBe(3000);
        expect($snapshot->currency)->toBe(mb_strtoupper(config('cart.money.default_currency', 'USD')));

        $item = CartItem::first();
        expect($item)->not->toBeNull();
        expect($item->item_id)->toBe('sku-001');
        expect($item->price)->toBe(1500);
        expect($item->quantity)->toBe(2);
        expect($item->attributes)->toBe(['color' => 'red']);
    });

    it('updates aggregated totals when an item is updated', function (): void {
        CartFacade::add('sku-001', 'Product', 2000, 1);
        CartFacade::update('sku-001', ['quantity' => ['value' => 3]]);

        $snapshot = CartSnapshot::first();
        expect($snapshot->items_count)->toBe(1);
        expect($snapshot->quantity)->toBe(3);
        expect($snapshot->subtotal)->toBe(6000);

        $item = CartItem::first();
        expect($item->quantity)->toBe(3);
    });

    it('removes stale normalized data when items are deleted', function (): void {
        CartFacade::add('sku-001', 'Product', 5000, 1);
        expect(CartSnapshot::count())->toBe(1);

        CartFacade::remove('sku-001');

        expect(CartSnapshot::count())->toBe(0);
        expect(CartItem::count())->toBe(0);
        expect(CartCondition::count())->toBe(0);
    });

    it('stores both cart-level and item-level conditions', function (): void {
        CartFacade::add('sku-001', 'Product', 8000, 1);
        CartFacade::addDiscount('order-discount', '-10%');
        $itemCondition = new CoreCondition('bulk', 'discount', 'item', '-15%');
        CartFacade::addItemCondition('sku-001', $itemCondition);

        expect(CartCondition::count())->toBe(2);

        $cartLevel = CartCondition::whereNull('item_id')->first();
        expect($cartLevel->name)->toBe('order-discount');
        expect($cartLevel->value)->toBe('-10%');

        $itemLevel = CartCondition::where('item_id', 'sku-001')->first();
        expect($itemLevel)->not->toBeNull();
        expect($itemLevel->name)->toBe('bulk');
        expect($itemLevel->value)->toBe('-15%');
        expect($itemLevel->cart_item_id)->toBe(CartItem::first()->id);

        $snapshot = CartSnapshot::first();
        expect($snapshot->total)->toBe((int) CartFacade::total()->getAmount());
    });

    it('clears the normalized snapshot when the cart is cleared', function (): void {
        CartFacade::add('sku-001', 'Product A', 1000, 1);
        CartFacade::add('sku-002', 'Product B', 2500, 2);
        expect(CartSnapshot::count())->toBe(1);
        expect(CartItem::count())->toBe(2);

        CartFacade::clear();

        expect(CartSnapshot::count())->toBe(0);
        expect(CartItem::count())->toBe(0);
    });

    it('does not persist empty carts when only totals are inspected', function (): void {
        expect(CartSnapshot::count())->toBe(0);
        CartFacade::getTotalQuantity();
        expect(CartSnapshot::count())->toBe(0);
    });
});
