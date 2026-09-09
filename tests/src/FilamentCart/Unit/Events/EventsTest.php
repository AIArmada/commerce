<?php

declare(strict_types=1);

use AIArmada\Cart\Events\CartAbandoned;
use AIArmada\Cart\Events\CartCheckoutStarted;
use AIArmada\Cart\Events\CartSnapshotSynced;
use AIArmada\Cart\Events\HighValueCartDetected;
use AIArmada\Cart\Snapshots\CartSnapshot as Cart;

it('creates scalar payloads for cart operational events', function (string $eventClass): void {
    $cart = Cart::query()->create([
        'identifier' => 'event-cart',
        'instance' => 'default',
        'subtotal' => 1200,
        'total' => 1500,
        'quantity' => 3,
        'items_count' => 2,
        'currency' => 'MYR',
        'checkout_started_at' => now(),
        'checkout_abandoned_at' => now(),
    ]);

    $event = $eventClass::fromCart($cart);

    expect($event->cartId)->toBe($cart->id)
        ->and($event->cartIdentifier)->toBe('event-cart')
        ->and($event->cartInstance)->toBe('default')
        ->and($event->totalMinor)->toBe(1500)
        ->and($event->currency)->toBe('MYR')
        ->and($event->sourceEventId)->toBeString();
})->with([
    CartSnapshotSynced::class,
    CartCheckoutStarted::class,
    CartAbandoned::class,
    HighValueCartDetected::class,
]);
