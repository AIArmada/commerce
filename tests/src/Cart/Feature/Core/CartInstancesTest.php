<?php

declare(strict_types=1);

use AIArmada\Cart\Conditions\CartCondition;
use AIArmada\Cart\Facades\Cart;

describe('Cart Instance Management', function (): void {
    beforeEach(function (): void {
        // Use destroy() to completely remove carts, not clear() which preserves them
        Cart::setInstance('default')->destroy();
        Cart::setInstance('wishlist')->destroy();
        Cart::setInstance('comparison')->destroy();
        Cart::setInstance('default'); // Reset to default
    });

    it('can switch between cart instances', function (): void {
        // Add items to default instance
        Cart::setInstance('default')->add('item-1', 'Item 1', 10.00, 1);

        // Switch to wishlist and add items
        Cart::setInstance('wishlist')->add('item-2', 'Item 2', 20.00, 1);

        // Verify isolation
        expect(Cart::setInstance('default')->count())->toBe(1);
        expect(Cart::setInstance('wishlist')->count())->toBe(1);
        expect(Cart::setInstance('default')->get('item-2'))->toBeNull();
        expect(Cart::setInstance('wishlist')->get('item-1'))->toBeNull();
    });

    it('maintains separate totals for each instance', function (): void {
        Cart::setInstance('default')->add('item-1', 'Item 1', 10.00, 2);
        Cart::setInstance('wishlist')->add('item-2', 'Item 2', 50.00, 1);

        expect(Cart::setInstance('default')->total()->getAmount())->toBe(20.00);
        expect(Cart::setInstance('wishlist')->total()->getAmount())->toBe(50.00);
    });

    it('maintains separate conditions for each instance', function (): void {
        Cart::setInstance('default')->add('item', 'Item', 100.00, 1);
        Cart::setInstance('wishlist')->add('item', 'Item', 100.00, 1);

        Cart::setInstance('default')->addTax('VAT', '10%');

        expect(Cart::setInstance('default')->total()->getAmount())->toBe(110.00);
        expect(Cart::setInstance('wishlist')->total()->getAmount())->toBe(100.00);
    });

    it('returns current instance name', function (): void {
        Cart::setInstance('custom-instance');

        expect(Cart::instance())->toBe('custom-instance');
    });

    it('can clear specific instance without affecting others', function (): void {
        Cart::setInstance('default')->add('item-1', 'Item 1', 10.00, 1);
        Cart::setInstance('wishlist')->add('item-2', 'Item 2', 20.00, 1);

        Cart::setInstance('default')->clear();

        expect(Cart::setInstance('default')->isEmpty())->toBeTrue();
        expect(Cart::setInstance('wishlist')->isEmpty())->toBeFalse();
    });

    it('handles chaining with instance switching', function (): void {
        Cart::setInstance('default')->add('item-1', 'Item 1', 10.00, 1);
        Cart::setInstance('default')->addTax('VAT', '10%');

        expect(Cart::setInstance('default')->total()->getAmount())->toBe(11.00);
    });
});

describe('Instance Isolation', function (): void {
    beforeEach(function (): void {
        Cart::setInstance('default')->clear();
        Cart::setInstance('other')->clear();
    });

    it('isolates items between instances', function (): void {
        Cart::setInstance('default')->add('shared-id', 'Default Item', 10.00, 5);
        Cart::setInstance('other')->add('shared-id', 'Other Item', 20.00, 3);

        $defaultItem = Cart::setInstance('default')->get('shared-id');
        $otherItem = Cart::setInstance('other')->get('shared-id');

        expect($defaultItem->quantity)->toBe(5);
        expect($defaultItem->price)->toBe(10.00);
        expect($otherItem->quantity)->toBe(3);
        expect($otherItem->price)->toBe(20.00);
    });

    it('isolates metadata between instances', function (): void {
        Cart::setInstance('default')->setMetadata('customer_note', 'Default note');
        Cart::setInstance('other')->setMetadata('customer_note', 'Other note');

        expect(Cart::setInstance('default')->getMetadata('customer_note'))->toBe('Default note');
        expect(Cart::setInstance('other')->getMetadata('customer_note'))->toBe('Other note');
    });

    it('handles multiple instance operations in sequence', function (): void {
        $instances = ['cart1', 'cart2', 'cart3'];

        foreach ($instances as $index => $instance) {
            Cart::setInstance($instance)->add("item-{$index}", "Item {$index}", 10.00 * ($index + 1), $index + 1);
        }

        expect(Cart::setInstance('cart1')->getTotalQuantity())->toBe(1);
        expect(Cart::setInstance('cart2')->getTotalQuantity())->toBe(2);
        expect(Cart::setInstance('cart3')->getTotalQuantity())->toBe(3);
    });
});

describe('Cart Identifier Management', function (): void {
    beforeEach(function (): void {
        Cart::setInstance('default')->clear();
    });

    it('can set custom identifier for current cart', function (): void {
        // Add item with default identifier
        Cart::add('item-1', 'Item 1', 10.00, 1);
        $originalIdentifier = Cart::getIdentifier();

        // Set custom identifier
        Cart::setIdentifier('custom-cart-id');

        // Verify identifier changed
        expect(Cart::getIdentifier())->toBe('custom-cart-id');
        expect(Cart::getIdentifier())->not()->toBe($originalIdentifier);

        // Verify cart is now empty (different identifier = different storage)
        expect(Cart::isEmpty())->toBeTrue();
    });

    it('maintains instance name when changing identifier', function (): void {
        Cart::setInstance('wishlist');
        Cart::setIdentifier('custom-id');

        expect(Cart::instance())->toBe('wishlist');
        expect(Cart::getIdentifier())->toBe('custom-id');
    });

    it('handles chaining with identifier setting', function (): void {
        $result = Cart::setIdentifier('test-id')->add('item-1', 'Test Item', 15.00, 1);

        expect(Cart::getIdentifier())->toBe('test-id');
        expect(Cart::count())->toBe(1);
        expect($result)->toBeInstanceOf(AIArmada\Cart\Models\CartItem::class);
    });

    it('can get current identifier', function (): void {
        $identifier = Cart::getIdentifier();

        expect($identifier)->toBeString();
        expect($identifier)->not()->toBeEmpty();
    });

    it('can reset identifier to default with forgetIdentifier', function (): void {
        $originalIdentifier = Cart::getIdentifier();

        // Set custom identifier
        Cart::setIdentifier('custom-cart-id');
        expect(Cart::getIdentifier())->toBe('custom-cart-id');

        // Reset to default
        Cart::forgetIdentifier();
        expect(Cart::getIdentifier())->toBe($originalIdentifier);
        expect(Cart::getIdentifier())->not()->toBe('custom-cart-id');
    });

    it('forgetIdentifier restores session/user identifier', function (): void {
        $defaultIdentifier = Cart::getIdentifier();

        // Add item to default cart
        Cart::add('item-1', 'Item 1', 10.00, 1);
        expect(Cart::count())->toBe(1);

        // Switch to custom identifier (empty cart)
        Cart::setIdentifier('temporary-cart');
        expect(Cart::isEmpty())->toBeTrue();

        // Reset back to default - should see the original item
        Cart::forgetIdentifier();
        expect(Cart::getIdentifier())->toBe($defaultIdentifier);
        expect(Cart::count())->toBe(1);
        expect(Cart::get('item-1'))->not()->toBeNull();
    });

    it('forgetIdentifier supports method chaining', function (): void {
        Cart::setIdentifier('custom-id');
        $result = Cart::forgetIdentifier()->add('item-1', 'Item 1', 10.00, 1);

        expect($result)->toBeInstanceOf(AIArmada\Cart\Models\CartItem::class);
        expect(Cart::count())->toBe(1);
    });
});

describe('Cart Storage Operations', function (): void {
    beforeEach(function (): void {
        // Use destroy() to ensure no cart exists at the start of each test
        Cart::setInstance('default')->destroy();
    });

    it('can check if cart exists', function (): void {
        // Initially no cart exists (empty)
        expect(Cart::exists())->toBeFalse();

        // Add item to create cart
        Cart::add('item-1', 'Item 1', 10.00, 1);

        // Now cart exists
        expect(Cart::exists())->toBeTrue();
    });

    it('can check if cart exists for specific identifier and instance', function (): void {
        // Add items to different instances
        Cart::setInstance('default')->add('item-1', 'Item 1', 10.00, 1);
        Cart::setInstance('wishlist')->add('item-2', 'Item 2', 20.00, 1);

        $identifier = Cart::getIdentifier();

        // Both instances should exist
        expect(Cart::exists($identifier, 'default'))->toBeTrue();
        expect(Cart::exists($identifier, 'wishlist'))->toBeTrue();

        // Non-existent instance should not exist
        expect(Cart::exists($identifier, 'nonexistent'))->toBeFalse();
    });

    it('can destroy cart completely', function (): void {
        Cart::add('item-1', 'Item 1', 10.00, 1);
        expect(Cart::exists())->toBeTrue();

        // Destroy removes cart completely
        Cart::destroy();

        expect(Cart::exists())->toBeFalse();
        expect(Cart::isEmpty())->toBeTrue();
    });

    it('can destroy specific cart instance', function (): void {
        $identifier = Cart::getIdentifier();

        Cart::setInstance('default')->add('item-1', 'Item 1', 10.00, 1);
        Cart::setInstance('wishlist')->add('item-2', 'Item 2', 20.00, 1);

        // Destroy only default instance
        Cart::destroy($identifier, 'default');

        expect(Cart::exists($identifier, 'default'))->toBeFalse();
        expect(Cart::exists($identifier, 'wishlist'))->toBeTrue();
    });

    it('can list all instances for identifier', function (): void {
        Cart::setInstance('default')->add('item-1', 'Item 1', 10.00, 1);
        Cart::setInstance('wishlist')->add('item-2', 'Item 2', 20.00, 1);
        Cart::setInstance('compare')->add('item-3', 'Item 3', 30.00, 1);

        $instances = Cart::instances();

        expect($instances)->toBeArray();
        expect($instances)->toContain('default');
        expect($instances)->toContain('wishlist');
        expect($instances)->toContain('compare');
    });

    it('returns empty array when no instances exist', function (): void {
        $instances = Cart::instances();

        expect($instances)->toBeArray();
        expect($instances)->toBeEmpty();
    });

    it('distinguishes between clear and destroy', function (): void {
        Cart::add('item-1', 'Item 1', 10.00, 1);

        // Verify cart has content
        expect(Cart::isEmpty())->toBeFalse();
        expect(Cart::exists())->toBeTrue();

        // Clear empties the cart (items, conditions, metadata) but preserves the cart structure
        // Note: With SessionStorage, the cart won't "exist" after clear because it tracks
        // presence by content. With DatabaseStorage, the cart record remains but is empty.
        Cart::clear();
        expect(Cart::isEmpty())->toBeTrue();

        // Add item again - cart can be refilled after clear
        Cart::add('item-2', 'Item 2', 20.00, 1);
        expect(Cart::isEmpty())->toBeFalse();
        expect(Cart::exists())->toBeTrue();

        // Destroy removes the cart completely from storage
        Cart::destroy();
        expect(Cart::exists())->toBeFalse();
        expect(Cart::isEmpty())->toBeTrue();
    });
});
