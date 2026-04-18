<?php

declare(strict_types=1);

use AIArmada\Cart\Events\CartMerged;
use AIArmada\Cart\Facades\Cart;
use AIArmada\Cart\Services\CartMigrationService;
use Illuminate\Support\Facades\Event;

it('dispatches CartMerged with correct payload when migrating empty user cart', function (): void {
    config(['cart.storage' => 'database']);
    Cart::clear();

    // Seed guest cart via storage
    $storage = Cart::storage();
    $guestIdentifier = 'guest-123';
    $userIdentifier = 'user-42';
    $instance = 'default';

    $guestItems = [
        'product-1' => [
            'id' => 'product-1',
            'name' => 'Test Product',
            'price' => 1000,
            'quantity' => 2,
            'attributes' => [],
            'conditions' => [],
        ],
    ];

    $storage->putItems($guestIdentifier, $instance, $guestItems);

    config(['cart.events' => true]);
    config(['cart.migration.merge_strategy' => 'add_quantities']);

    Event::fake();

    $service = new CartMigrationService(['merge_strategy' => 'add_quantities']);
    $result = $service->migrateGuestCartToUser($userIdentifier, $instance, $guestIdentifier);

    expect($result)->toBeTrue();

    Event::assertDispatched(CartMerged::class, function (CartMerged $event) use ($guestIdentifier, $userIdentifier): bool {
        return $event->totalItemsMerged === 2
            && $event->mergeStrategy === 'add_quantities'
            && $event->hadConflicts === false
            && $event->originalSourceIdentifier === $guestIdentifier
            && $event->originalTargetIdentifier === (string) $userIdentifier;
    });
});

it('uses the configured merge strategy and reports guest-only quantities when carts overlap', function (): void {
    config(['cart.owner.enabled' => false]);
    config(['cart.events' => true]);
    config(['cart.migration.merge_strategy' => 'keep_user_cart']);

    Cart::clear();

    $storage = Cart::storage();
    $guestIdentifier = 'guest-456';
    $userIdentifier = 'user-456';
    $instance = 'default';

    $storage->putItems($guestIdentifier, $instance, [
        'product-1' => [
            'id' => 'product-1',
            'name' => 'Guest Product',
            'price' => 1000,
            'quantity' => 2,
            'attributes' => [],
            'conditions' => [],
        ],
    ]);

    $storage->putItems($userIdentifier, $instance, [
        'product-1' => [
            'id' => 'product-1',
            'name' => 'User Product',
            'price' => 1000,
            'quantity' => 3,
            'attributes' => [],
            'conditions' => [],
        ],
    ]);

    Event::fake();

    $service = app(CartMigrationService::class);
    $result = $service->migrateGuestCartToUser($userIdentifier, $instance, $guestIdentifier);

    expect($result)->toBeTrue();

    Event::assertDispatched(CartMerged::class, function (CartMerged $event) use ($guestIdentifier, $userIdentifier): bool {
        return $event->mergeStrategy === 'keep_user_cart'
            && $event->totalItemsMerged === 2
            && $event->hadConflicts === true
            && $event->originalSourceIdentifier === $guestIdentifier
            && $event->originalTargetIdentifier === $userIdentifier;
    });
});

it('rejects empty guest session identifiers', function (): void {
    config(['cart.owner.enabled' => false]);

    $service = new CartMigrationService;
    $result = $service->migrateGuestCartForUser('user-999', 'default', '');

    expect($result->success)->toBeFalse();
    expect($result->itemsMerged)->toBe(0);
    expect($result->message)->toBe('No guest session to migrate');
});
