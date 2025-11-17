<?php

declare(strict_types=1);

use AIArmada\Cart\Events\CartMerged;
use AIArmada\Cart\Facades\Cart;
use AIArmada\FilamentCart\Listeners\CleanupSnapshotOnCartMerged;
use AIArmada\FilamentCart\Models\Cart as CartSnapshot;
use AIArmada\FilamentCart\Models\CartCondition;
use AIArmada\FilamentCart\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

describe('CartMerged Event Updates', function (): void {
    it('updates guest cart snapshot identifier when user has no existing cart', function (): void {
        // Create guest cart snapshot
        $guestSnapshot = CartSnapshot::create([
            'identifier' => 'guest_session_123',
            'instance' => 'default',
            'items' => ['product-1' => ['id' => 'product-1', 'quantity' => 2]],
            'items_count' => 1,
            'quantity' => 2,
            'subtotal' => 1000,
            'total' => 1000,
            'currency' => 'MYR',
        ]);

        // Verify guest snapshot exists
        expect(CartSnapshot::where('identifier', 'guest_session_123')->exists())->toBeTrue();
        expect(CartSnapshot::where('identifier', '42')->exists())->toBeFalse();

        // Create cart instances for the event
        // After a real swap, both carts would have the user identifier ('42')
        // but we pass the original source identifier in the event
        $guestCart = Cart::getCartInstance('default', '42'); // After swap, uses user ID
        $userCart = Cart::getCartInstance('default', '42');

        // Dispatch CartMerged event with original source identifier
        $event = new CartMerged(
            targetCart: $userCart,
            sourceCart: $guestCart,
            totalItemsMerged: 2,
            mergeStrategy: 'add_quantities',
            hadConflicts: false,
            originalSourceIdentifier: 'guest_session_123', // The guest session before swap
            originalTargetIdentifier: '42'
        );
        $listener = new CleanupSnapshotOnCartMerged;
        $listener->handle($event);

        // Guest snapshot should be updated to user identifier
        expect(CartSnapshot::where('identifier', 'guest_session_123')->exists())->toBeFalse();
        expect(CartSnapshot::where('identifier', '42')->exists())->toBeTrue();

        // Verify the data was preserved
        $updatedSnapshot = CartSnapshot::where('identifier', '42')->first();
        expect($updatedSnapshot->id)->toBe($guestSnapshot->id);
        expect($updatedSnapshot->items_count)->toBe(1);
        expect($updatedSnapshot->quantity)->toBe(2);
    });

    it('transfers items and conditions when user has existing cart snapshot', function (): void {
        // Create guest cart snapshot with items and conditions
        $guestSnapshot = CartSnapshot::create([
            'identifier' => 'guest_session_456',
            'instance' => 'default',
            'items' => ['product-1' => ['id' => 'product-1', 'quantity' => 2]],
            'items_count' => 1,
            'quantity' => 2,
            'currency' => 'MYR',
        ]);

        $guestItem = $guestSnapshot->cartItems()->create([
            'item_id' => 'product-1',
            'name' => 'Guest Product',
            'price' => 5000,
            'quantity' => 2,
        ]);

        $guestCondition = $guestSnapshot->cartConditions()->create([
            'name' => 'guest-discount',
            'type' => 'discount',
            'target' => 'cart@cart_subtotal/aggregate',
            'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),
            'value' => '10',
            'order' => 1,
        ]);

        // Create user cart snapshot with items and conditions
        $userSnapshot = CartSnapshot::create([
            'identifier' => '42',
            'instance' => 'default',
            'items' => ['product-2' => ['id' => 'product-2', 'quantity' => 1]],
            'items_count' => 1,
            'quantity' => 1,
            'currency' => 'MYR',
        ]);

        $userItem = $userSnapshot->cartItems()->create([
            'item_id' => 'product-2',
            'name' => 'User Product',
            'price' => 3000,
            'quantity' => 1,
        ]);

        $userCondition = $userSnapshot->cartConditions()->create([
            'name' => 'user-tax',
            'type' => 'tax',
            'target' => 'cart@cart_subtotal/aggregate',
            'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),
            'value' => '6',
            'order' => 1,
        ]);

        // Verify both snapshots exist
        expect(CartSnapshot::where('identifier', 'guest_session_456')->count())->toBe(1);
        expect(CartSnapshot::where('identifier', '42')->count())->toBe(1);
        expect(CartItem::where('cart_id', $guestSnapshot->id)->count())->toBe(1);
        expect(CartItem::where('cart_id', $userSnapshot->id)->count())->toBe(1);
        expect(CartCondition::where('cart_id', $guestSnapshot->id)->count())->toBe(1);
        expect(CartCondition::where('cart_id', $userSnapshot->id)->count())->toBe(1);

        // Dispatch CartMerged event
        $guestCart = Cart::getCartInstance('default', '42');
        $userCart = Cart::getCartInstance('default', '42');

        $event = new CartMerged(
            targetCart: $userCart,
            sourceCart: $guestCart,
            totalItemsMerged: 2,
            mergeStrategy: 'add_quantities',
            hadConflicts: false,
            originalSourceIdentifier: 'guest_session_456',
            originalTargetIdentifier: '42'
        );
        $listener = new CleanupSnapshotOnCartMerged;
        $listener->handle($event);

        // Guest snapshot should be deleted
        expect(CartSnapshot::where('identifier', 'guest_session_456')->exists())->toBeFalse();

        // User snapshot should still exist
        expect(CartSnapshot::where('identifier', '42')->exists())->toBeTrue();

        // All items should now be under user snapshot
        expect(CartItem::where('cart_id', $userSnapshot->id)->count())->toBe(2);
        expect(CartItem::where('item_id', 'product-1')->first()->cart_id)->toBe($userSnapshot->id);
        expect(CartItem::where('item_id', 'product-2')->first()->cart_id)->toBe($userSnapshot->id);

        // All conditions should now be under user snapshot
        expect(CartCondition::where('cart_id', $userSnapshot->id)->count())->toBe(2);
        expect(CartCondition::where('name', 'guest-discount')->first()->cart_id)->toBe($userSnapshot->id);
        expect(CartCondition::where('name', 'user-tax')->first()->cart_id)->toBe($userSnapshot->id);
    });

    it('handles updates for multiple instances separately', function (): void {
        // Create guest snapshots for different instances
        $defaultSnapshot = CartSnapshot::create([
            'identifier' => 'guest_session_789',
            'instance' => 'default',
            'items' => ['product-1' => ['id' => 'product-1', 'quantity' => 1]],
            'items_count' => 1,
            'quantity' => 1,
            'currency' => 'MYR',
        ]);

        $wishlistSnapshot = CartSnapshot::create([
            'identifier' => 'guest_session_789',
            'instance' => 'wishlist',
            'items' => ['product-2' => ['id' => 'product-2', 'quantity' => 1]],
            'items_count' => 1,
            'quantity' => 1,
            'currency' => 'MYR',
        ]);

        // Verify both exist
        expect(CartSnapshot::where('identifier', 'guest_session_789')->count())->toBe(2);

        // Merge only the default cart (user has no existing default cart)
        $guestCart = Cart::getCartInstance('default', '99');
        $userCart = Cart::getCartInstance('default', '99');

        $event = new CartMerged(
            targetCart: $userCart,
            sourceCart: $guestCart,
            totalItemsMerged: 1,
            mergeStrategy: 'add_quantities',
            hadConflicts: false,
            originalSourceIdentifier: 'guest_session_789',
            originalTargetIdentifier: '99'
        );
        $listener = new CleanupSnapshotOnCartMerged;
        $listener->handle($event);

        // Default instance should be updated to user identifier
        expect(CartSnapshot::where('identifier', 'guest_session_789')
            ->where('instance', 'default')
            ->exists())->toBeFalse();
        expect(CartSnapshot::where('identifier', '99')
            ->where('instance', 'default')
            ->exists())->toBeTrue();

        // Wishlist should remain unchanged
        expect(CartSnapshot::where('identifier', 'guest_session_789')
            ->where('instance', 'wishlist')
            ->exists())->toBeTrue();
    });

    it('does not fail if snapshot does not exist', function (): void {
        // No snapshot exists for this identifier
        expect(CartSnapshot::where('identifier', 'non_existent_session')->exists())->toBeFalse();

        // Create cart instances
        $guestCart = Cart::getCartInstance('default', 'non_existent_session');
        $userCart = Cart::getCartInstance('default', '100');

        // Dispatch event should not throw exception
        $event = new CartMerged(
            targetCart: $userCart,
            sourceCart: $guestCart,
            totalItemsMerged: 0,
            mergeStrategy: 'add_quantities',
            hadConflicts: false,
            originalSourceIdentifier: 'non_existent_session',
            originalTargetIdentifier: '100'
        );
        $listener = new CleanupSnapshotOnCartMerged;
        $listener->handle($event);

        // Should complete without error
        expect(true)->toBeTrue();
    });
});

describe('Integration with Cart Migration', function (): void {
    it('updates guest snapshot during actual cart migration when user has no cart', function (): void {
        // Create guest cart by directly using storage
        $storage = Cart::storage();
        $guestIdentifier = 'guest_789';

        $storage->putItems($guestIdentifier, 'default', [
            'product-1' => [
                'id' => 'product-1',
                'name' => 'Test Product',
                'price' => 99.99,
                'quantity' => 2,
                'attributes' => [],
                'conditions' => [],
            ],
        ]);

        // Create guest snapshot
        $guestSnapshot = CartSnapshot::create([
            'identifier' => $guestIdentifier,
            'instance' => 'default',
            'items' => ['product-1' => ['id' => 'product-1', 'quantity' => 2]],
            'items_count' => 1,
            'quantity' => 2,
            'currency' => 'MYR',
        ]);

        // Verify guest snapshot exists
        expect(CartSnapshot::where('identifier', $guestIdentifier)->exists())->toBeTrue();

        // Perform cart migration (swap)
        $migrationService = app(AIArmada\Cart\Services\CartMigrationService::class);

        $result = $migrationService->swap($guestIdentifier, '50', 'default');
        expect($result)->toBeTrue();

        // Manually trigger the listener to simulate real behavior
        $listener = new CleanupSnapshotOnCartMerged;
        $guestCart = Cart::getCartInstance('default', $guestIdentifier);
        $userCart = Cart::getCartInstance('default', '50');
        $event = new CartMerged(
            targetCart: $userCart,
            sourceCart: $guestCart,
            totalItemsMerged: 2,
            mergeStrategy: 'add_quantities',
            hadConflicts: false,
            originalSourceIdentifier: $guestIdentifier,
            originalTargetIdentifier: '50'
        );
        $listener->handle($event);

        // Guest snapshot should be updated to user identifier
        expect(CartSnapshot::where('identifier', $guestIdentifier)->exists())->toBeFalse();
        expect(CartSnapshot::where('identifier', '50')->exists())->toBeTrue();

        // Verify data was preserved
        $updatedSnapshot = CartSnapshot::where('identifier', '50')->first();
        expect($updatedSnapshot->quantity)->toBe(2);
    });

    it('transfers items when user already has a cart snapshot on login', function (): void {
        // Create guest cart via storage
        $storage = Cart::storage();
        $guestIdentifier = 'session_abc';

        $storage->putItems($guestIdentifier, 'default', [
            'product-1' => [
                'id' => 'product-1',
                'name' => 'Product 1',
                'price' => 50.00,
                'quantity' => 1,
                'attributes' => [],
                'conditions' => [],
            ],
            'product-2' => [
                'id' => 'product-2',
                'name' => 'Product 2',
                'price' => 75.00,
                'quantity' => 2,
                'attributes' => [],
                'conditions' => [],
            ],
        ]);

        // Create guest snapshot
        $guestSnapshot = CartSnapshot::create([
            'identifier' => $guestIdentifier,
            'instance' => 'default',
            'items' => [
                'product-1' => ['id' => 'product-1', 'quantity' => 1],
                'product-2' => ['id' => 'product-2', 'quantity' => 2],
            ],
            'items_count' => 2,
            'quantity' => 3,
            'currency' => 'MYR',
        ]);

        // Add cart items to guest snapshot
        $guestSnapshot->cartItems()->create([
            'item_id' => 'product-1',
            'name' => 'Product 1',
            'price' => 5000,
            'quantity' => 1,
        ]);

        $guestSnapshot->cartItems()->create([
            'item_id' => 'product-2',
            'name' => 'Product 2',
            'price' => 7500,
            'quantity' => 2,
        ]);

        // Create existing user snapshot
        $userSnapshot = CartSnapshot::create([
            'identifier' => '75',
            'instance' => 'default',
            'items' => [
                'product-3' => ['id' => 'product-3', 'quantity' => 1],
            ],
            'items_count' => 1,
            'quantity' => 1,
            'currency' => 'MYR',
        ]);

        $userSnapshot->cartItems()->create([
            'item_id' => 'product-3',
            'name' => 'Product 3',
            'price' => 3000,
            'quantity' => 1,
        ]);

        expect($guestSnapshot)->not->toBeNull();
        expect($guestSnapshot->items_count)->toBe(2);
        expect(CartItem::where('cart_id', $guestSnapshot->id)->count())->toBe(2);
        expect(CartItem::where('cart_id', $userSnapshot->id)->count())->toBe(1);

        // User logs in and cart is migrated
        $migrationService = app(AIArmada\Cart\Services\CartMigrationService::class);

        // Do the swap
        $result = $migrationService->swap($guestIdentifier, '75', 'default');
        expect($result)->toBeTrue();

        // Manually trigger cleanup
        $listener = new CleanupSnapshotOnCartMerged;
        $guestCart = Cart::getCartInstance('default', $guestIdentifier);
        $userCart = Cart::getCartInstance('default', '75');
        $event = new CartMerged(
            targetCart: $userCart,
            sourceCart: $guestCart,
            totalItemsMerged: 3,
            mergeStrategy: 'add_quantities',
            hadConflicts: false,
            originalSourceIdentifier: $guestIdentifier,
            originalTargetIdentifier: '75'
        );
        $listener->handle($event);

        // Guest snapshot should be deleted
        expect(CartSnapshot::where('identifier', $guestIdentifier)->exists())->toBeFalse();

        // User snapshot should exist with all items
        expect(CartSnapshot::where('identifier', '75')->exists())->toBeTrue();
        expect(CartItem::where('cart_id', $userSnapshot->id)->count())->toBe(3);
    });
});

describe('Normalized Data Transfer', function (): void {
    it('transfers cart items and conditions to existing user snapshot', function (): void {
        $guestIdentifier = 'guest_with_normalized_data';

        // Create guest snapshot with items and conditions
        $guestSnapshot = CartSnapshot::create([
            'identifier' => $guestIdentifier,
            'instance' => 'default',
            'items' => ['product-1' => ['id' => 'product-1', 'quantity' => 2]],
            'items_count' => 1,
            'quantity' => 2,
            'currency' => 'MYR',
        ]);

        // Create normalized item records for guest
        $item1 = $guestSnapshot->cartItems()->create([
            'item_id' => 'product-1',
            'name' => 'Test Product',
            'price' => 9999, // 99.99 in cents
            'quantity' => 2,
            'attributes' => ['color' => 'red'],
        ]);

        $item2 = $guestSnapshot->cartItems()->create([
            'item_id' => 'product-2',
            'name' => 'Another Product',
            'price' => 4999, // 49.99 in cents
            'quantity' => 1,
        ]);

        // Create cart-level condition for guest
        $guestSnapshot->cartConditions()->create([
            'name' => 'tax',
            'type' => 'tax',
            'target' => 'cart@cart_subtotal/aggregate',
            'target_definition' => conditionTargetDefinition('cart@cart_subtotal/aggregate'),
            'value' => '10%',
            'is_percentage' => true,
            'order' => 1,
        ]);

        // Create item-level condition for guest
        $guestSnapshot->cartConditions()->create([
            'cart_item_id' => $item1->id,
            'item_id' => 'product-1',
            'name' => 'discount',
            'type' => 'discount',
            'target' => 'items@item_discount/per-item',
            'target_definition' => conditionTargetDefinition('items@item_discount/per-item'),
            'value' => '5%',
            'is_discount' => true,
            'is_percentage' => true,
            'order' => 1,
        ]);

        // Create existing user snapshot
        $userSnapshot = CartSnapshot::create([
            'identifier' => '500',
            'instance' => 'default',
            'items' => ['product-3' => ['id' => 'product-3', 'quantity' => 1]],
            'items_count' => 1,
            'quantity' => 1,
            'currency' => 'MYR',
        ]);

        $userSnapshot->cartItems()->create([
            'item_id' => 'product-3',
            'name' => 'User Product',
            'price' => 2000,
            'quantity' => 1,
        ]);

        // Verify initial state
        expect(CartSnapshot::where('identifier', $guestIdentifier)->count())->toBe(1);
        expect(CartSnapshot::where('identifier', '500')->count())->toBe(1);
        expect(CartItem::where('cart_id', $guestSnapshot->id)->count())->toBe(2);
        expect(CartItem::where('cart_id', $userSnapshot->id)->count())->toBe(1);
        expect(CartCondition::where('cart_id', $guestSnapshot->id)->count())->toBe(2);
        expect(CartCondition::where('cart_id', $userSnapshot->id)->count())->toBe(0);

        // Trigger transfer
        $guestCart = Cart::getCartInstance('default', $guestIdentifier);
        $userCart = Cart::getCartInstance('default', '500');

        $event = new CartMerged(
            targetCart: $userCart,
            sourceCart: $guestCart,
            totalItemsMerged: 2,
            mergeStrategy: 'add_quantities',
            hadConflicts: false,
            originalSourceIdentifier: $guestIdentifier,
            originalTargetIdentifier: '500'
        );
        $listener = new CleanupSnapshotOnCartMerged;
        $listener->handle($event);

        // Verify all data was transferred to user snapshot
        expect(CartSnapshot::where('identifier', $guestIdentifier)->exists())->toBeFalse();
        expect(CartSnapshot::where('identifier', '500')->exists())->toBeTrue();
        expect(CartItem::where('cart_id', $userSnapshot->id)->count())->toBe(3);
        expect(CartCondition::where('cart_id', $userSnapshot->id)->count())->toBe(2);

        // Verify guest snapshot was deleted
        expect(CartItem::where('cart_id', $guestSnapshot->id)->count())->toBe(0);
        expect(CartCondition::where('cart_id', $guestSnapshot->id)->count())->toBe(0);
    });
});

describe('Edge Cases', function (): void {
    it('updates empty guest cart snapshot identifier', function (): void {
        // Create an empty guest snapshot
        $guestSnapshot = CartSnapshot::create([
            'identifier' => 'empty_session',
            'instance' => 'default',
            'items' => [],
            'items_count' => 0,
            'quantity' => 0,
            'currency' => 'MYR',
        ]);

        expect(CartSnapshot::where('identifier', 'empty_session')->exists())->toBeTrue();

        $guestCart = Cart::getCartInstance('default', 'empty_session');
        $userCart = Cart::getCartInstance('default', '200');

        $event = new CartMerged(
            targetCart: $userCart,
            sourceCart: $guestCart,
            totalItemsMerged: 0,
            mergeStrategy: 'add_quantities',
            hadConflicts: false,
            originalSourceIdentifier: 'empty_session',
            originalTargetIdentifier: '200'
        );
        $listener = new CleanupSnapshotOnCartMerged;
        $listener->handle($event);

        // Empty snapshot should be updated to user identifier
        expect(CartSnapshot::where('identifier', 'empty_session')->exists())->toBeFalse();
        expect(CartSnapshot::where('identifier', '200')->exists())->toBeTrue();

        $updatedSnapshot = CartSnapshot::where('identifier', '200')->first();
        expect($updatedSnapshot->id)->toBe($guestSnapshot->id);
    });

    it('preserves other user snapshots during updates', function (): void {
        // Create multiple user snapshots
        CartSnapshot::create(['identifier' => '100', 'instance' => 'default', 'currency' => 'MYR']);
        CartSnapshot::create(['identifier' => '101', 'instance' => 'default', 'currency' => 'MYR']);
        $guestSnapshot = CartSnapshot::create(['identifier' => 'guest_xyz', 'instance' => 'default', 'currency' => 'MYR']);

        expect(CartSnapshot::count())->toBe(3);

        // Update only the guest snapshot (user 100 has no existing cart)
        $guestCart = Cart::getCartInstance('default', 'guest_xyz');
        $userCart = Cart::getCartInstance('default', '100');

        $event = new CartMerged(
            targetCart: $userCart,
            sourceCart: $guestCart,
            totalItemsMerged: 0,
            mergeStrategy: 'add_quantities',
            hadConflicts: false,
            originalSourceIdentifier: 'guest_xyz',
            originalTargetIdentifier: '100'
        );
        $listener = new CleanupSnapshotOnCartMerged;
        $listener->handle($event);

        // Should still have 3 snapshots (guest updated to 100, 100 original still there, 101 untouched)
        // Wait, there's already a snapshot for '100', so it should transfer and delete
        expect(CartSnapshot::count())->toBe(2);
        expect(CartSnapshot::where('identifier', '101')->exists())->toBeTrue();
        expect(CartSnapshot::where('identifier', 'guest_xyz')->exists())->toBeFalse();
    });
});
