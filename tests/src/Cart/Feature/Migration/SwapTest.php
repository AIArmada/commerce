<?php

declare(strict_types=1);

use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Services\CartMigrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test cart swap functionality.
 *
 * This tests the cart swap feature that transfers cart ownership from
 * old identifier to new identifier, ensuring the new identifier gets
 * an active cart to prevent abandonment.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Create database storage for testing
    $connection = app('db')->connection();
    $connection->getSchemaBuilder()->dropIfExists('carts_swap_test');
    $connection->getSchemaBuilder()->create('carts_swap_test', function ($table): void {
        $table->uuid('id')->primary();
        $table->string('identifier')->index();
        $table->string('instance')->default('default')->index();
        $table->longText('items')->nullable();
        $table->longText('conditions')->nullable();
        $table->longText('metadata')->nullable();
        $table->bigInteger('version')->default(1)->index()->comment('Version number for optimistic locking');
        $table->timestamps();
        $table->unique(['identifier', 'instance']);
    });

    $this->storage = new AIArmada\Cart\Storage\DatabaseStorage($connection, 'carts_swap_test');
    $this->cartMigration = new CartMigrationService([], $this->storage);
});

afterEach(function (): void {
    // Clean up the test table
    $connection = app('db')->connection();
    $connection->getSchemaBuilder()->dropIfExists('carts_swap_test');
});

it('transfers source cart to target identifier even when target exists', function (): void {
    $storage = $this->storage;

    // Add items to guest cart (the source cart to transfer)
    $guestItems = [
        'guest-product-1' => [
            'id' => 'guest-product-1',
            'name' => 'Guest Product 1',
            'price' => 10.00,
            'quantity' => 2,
            'attributes' => ['color' => 'red'],
            'conditions' => [],
        ],
    ];

    // Add different items to user cart (this will be overwritten)
    $userItems = [
        'user-product-1' => [
            'id' => 'user-product-1',
            'name' => 'User Product 1',
            'price' => 25.00,
            'quantity' => 1,
            'attributes' => ['size' => 'large'],
            'conditions' => [],
        ],
    ];

    $userConditions = [
        'user_discount' => [
            'name' => 'user_discount',
            'type' => 'discount',
            'target' => 'subtotal',
            'value' => '15%',
        ],
    ];

    // Store data with both identifiers
    $storage->putBoth('guest_session_123', 'default', $guestItems, []);
    $storage->putBoth('user_42', 'default', $userItems, $userConditions);

    // Verify both carts exist initially
    expect($storage->getItems('guest_session_123', 'default'))->toHaveCount(1);
    expect($storage->getItems('user_42', 'default'))->toHaveCount(1);

    // Perform swap (transfer guest cart to user identifier)
    $result = $this->cartMigration->swap('guest_session_123', 'user_42', 'default');
    expect($result)->toBeTrue();

    // Verify results after swap:
    // Guest cart should be gone (transferred)
    $guestItemsAfter = $storage->getItems('guest_session_123', 'default');
    expect($guestItemsAfter)->toBeEmpty();

    // User cart should now have the guest cart content (not the original user content)
    $userItemsAfter = $storage->getItems('user_42', 'default');
    $userConditionsAfter = $storage->getConditions('user_42', 'default');

    expect($userItemsAfter)->toHaveCount(1);
    expect($userConditionsAfter)->toBeEmpty(); // Conditions were empty in guest cart

    // Verify the guest content is now under user identifier
    expect($userItemsAfter['guest-product-1'])->toEqual($guestItems['guest-product-1']);

    // Original user content should NOT be present (it was overwritten)
    expect($userItemsAfter)->not->toHaveKey('user-product-1');
});

it('transfers source cart when target cart does not exist', function (): void {
    $storage = $this->storage;

    // Add items to guest cart only (no user cart exists)
    $guestItems = [
        'product-1' => [
            'id' => 'product-1',
            'name' => 'Test Product 1',
            'price' => 10.00,
            'quantity' => 2,
            'attributes' => ['color' => 'red'],
            'conditions' => [],
        ],
        'product-2' => [
            'id' => 'product-2',
            'name' => 'Test Product 2',
            'price' => 15.00,
            'quantity' => 1,
            'attributes' => ['size' => 'large'],
            'conditions' => [],
        ],
    ];

    $guestConditions = [
        'discount' => [
            'name' => 'discount',
            'type' => 'discount',
            'target' => 'subtotal',
            'value' => '10%',
        ],
    ];

    // Store data with guest identifier only
    $storage->putBoth('guest_session_123', 'default', $guestItems, $guestConditions);

    // Verify guest cart exists and user cart is empty
    expect($storage->getItems('guest_session_123', 'default'))->toHaveCount(2);
    expect($storage->getItems('user_42', 'default'))->toBeEmpty();

    // Perform swap
    $result = $this->cartMigration->swap('guest_session_123', 'user_42', 'default');
    expect($result)->toBeTrue();

    // Verify results after swap:
    // Guest cart should be empty (transferred)
    $guestItemsAfter = $storage->getItems('guest_session_123', 'default');
    $guestConditionsAfter = $storage->getConditions('guest_session_123', 'default');
    expect($guestItemsAfter)->toBeEmpty();
    expect($guestConditionsAfter)->toBeEmpty();

    // User cart should have all the original guest items
    $userItemsAfter = $storage->getItems('user_42', 'default');
    $userConditionsAfter = $storage->getConditions('user_42', 'default');
    expect($userItemsAfter)->toHaveCount(2);
    expect($userConditionsAfter)->toHaveCount(1);

    // Verify items are identical to original guest items
    expect($userItemsAfter['product-1'])->toEqual($guestItems['product-1']);
    expect($userItemsAfter['product-2'])->toEqual($guestItems['product-2']);
    expect($userConditionsAfter['discount'])->toEqual($guestConditions['discount']);
});

it('swaps even when source cart is empty', function (): void {
    $storage = $this->storage;

    // Create an empty guest cart (cart exists but has no items)
    $storage->putBoth('guest_session_empty', 'default', [], []);

    // Verify guest cart exists but is empty
    expect($storage->has('guest_session_empty', 'default'))->toBeTrue();
    expect($storage->getItems('guest_session_empty', 'default'))->toBeEmpty();

    // Perform swap - should succeed even though cart is empty
    $result = $this->cartMigration->swap('guest_session_empty', 'user_empty', 'default');
    expect($result)->toBeTrue();

    // Verify the empty cart was transferred to ensure cart ownership and prevent abandonment
    expect($storage->has('guest_session_empty', 'default'))->toBeFalse();
    expect($storage->has('user_empty', 'default'))->toBeTrue();
    expect($storage->getItems('user_empty', 'default'))->toBeEmpty();
});

it('returns false when swapping non-existent cart', function (): void {
    // Create database storage for testing
    $connection = app('db')->connection();
    $connection->getSchemaBuilder()->dropIfExists('carts_swap_test_3');
    $connection->getSchemaBuilder()->create('carts_swap_test_3', function ($table): void {
        $table->uuid('id')->primary();
        $table->string('identifier')->index();
        $table->string('instance')->default('default')->index();
        $table->longText('items')->nullable();
        $table->longText('conditions')->nullable();
        $table->longText('metadata')->nullable();
        $table->timestamps();
        $table->unique(['identifier', 'instance']);
    });

    $storage = new AIArmada\Cart\Storage\DatabaseStorage($connection, 'carts_swap_test_3');
    $migration = new CartMigrationService;

    // Try to swap a non-existent cart
    $result = $migration->swap('non_existent_session', 'user_42', 'default');
    expect($result)->toBeFalse();
});

it('can swap cart ownership for specific instances', function (): void {
    $storage = $this->storage;

    // Add items to guest wishlist cart
    $wishlistItems = [
        'product-1' => [
            'id' => 'product-1',
            'name' => 'Wishlist Product',
            'price' => 20.00,
            'quantity' => 1,
            'attributes' => [],
            'conditions' => [],
        ],
    ];

    // Store data with guest identifier in wishlist instance
    $storage->putBoth('guest_session_456', 'wishlist', $wishlistItems, []);

    // Verify guest wishlist exists
    $guestWishlistBefore = $storage->getItems('guest_session_456', 'wishlist');
    expect($guestWishlistBefore)->toHaveCount(1);

    // Perform swap for wishlist instance only
    $result = $this->cartMigration->swap('guest_session_456', 'user_99', 'wishlist');
    expect($result)->toBeTrue();

    // Verify results after swap
    // Guest wishlist should be empty
    $guestWishlistAfter = $storage->getItems('guest_session_456', 'wishlist');
    expect($guestWishlistAfter)->toBeEmpty();

    // User wishlist should have the items
    $userWishlistAfter = $storage->getItems('user_99', 'wishlist');
    expect($userWishlistAfter)->toHaveCount(1);
    expect($userWishlistAfter['product-1'])->toEqual($wishlistItems['product-1']);
});

it('can swap through cart facade', function (): void {
    $storage = $this->storage;

    // Add items to guest cart
    $guestItems = [
        'product-1' => [
            'id' => 'product-1',
            'name' => 'Facade Test Product',
            'price' => 25.00,
            'quantity' => 3,
            'attributes' => [],
            'conditions' => [],
        ],
    ];

    // Store data with guest identifier
    $storage->putBoth('guest_session_facade', 'default', $guestItems, []);

    // Create a cart manager with our test storage
    $cartManager = new AIArmada\Cart\CartManager($storage);

    // Verify guest cart exists
    expect($storage->getItems('guest_session_facade', 'default'))->toHaveCount(1);

    // Swap using the cart manager (simulating Cart facade)
    $result = $cartManager->swap('guest_session_facade', 'user_facade', 'default');
    expect($result)->toBeTrue();

    // Verify results
    expect($storage->getItems('guest_session_facade', 'default'))->toBeEmpty();
    expect($storage->getItems('user_facade', 'default'))->toHaveCount(1);
});

it('can swap guest cart using convenience method', function (): void {
    $storage = $this->storage;

    // Add items to guest cart
    $guestItems = [
        'product-1' => [
            'id' => 'product-1',
            'name' => 'Convenience Test Product',
            'price' => 12.50,
            'quantity' => 2,
            'attributes' => [],
            'conditions' => [],
        ],
    ];

    // Store data with guest identifier
    $storage->putBoth('guest_session_conv', 'default', $guestItems, []);

    // Verify guest cart exists
    expect($storage->getItems('guest_session_conv', 'default'))->toHaveCount(1);

    // Swap using convenience method
    $result = $this->cartMigration->swapGuestCartToUser(999, 'default', 'guest_session_conv');
    expect($result)->toBeTrue();

    // Verify results
    expect($storage->getItems('guest_session_conv', 'default'))->toBeEmpty();
    expect($storage->getItems('999', 'default'))->toHaveCount(1);

    $userItems = $storage->getItems('999', 'default');
    expect($userItems['product-1']['name'])->toBe('Convenience Test Product');
});
