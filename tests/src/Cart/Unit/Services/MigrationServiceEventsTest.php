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
            'price' => 10.00,
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
