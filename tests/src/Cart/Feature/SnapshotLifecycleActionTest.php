<?php

declare(strict_types=1);

use AIArmada\Cart\Events\CartAbandoned;
use AIArmada\Cart\Events\CartCheckoutStarted;
use AIArmada\Cart\Features\Snapshots\Actions\StartCartCheckout;
use AIArmada\Cart\Features\Snapshots\Api\CartAbandonmentMarker;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('rechecks activity before marking a snapshot abandoned and emits only one event', function (): void {
    config(['cart.owner.enabled' => false]);
    Event::fake([CartAbandoned::class]);
    $snapshot = CartSnapshot::factory()->create([
        'items_count' => 1,
        'quantity' => 1,
        'checkout_started_at' => now()->subHour(),
        'checkout_abandoned_at' => null,
        'last_activity_at' => now(),
    ]);

    $marker = app(CartAbandonmentMarker::class);
    $cutoff = CarbonImmutable::now()->subMinutes(30);

    expect($marker->execute((string) $snapshot->getKey(), $cutoff))->toBeFalse();
    Event::assertNotDispatched(CartAbandoned::class);

    $snapshot->update(['last_activity_at' => now()->subHour()]);

    expect($marker->execute((string) $snapshot->getKey(), $cutoff))->toBeTrue();
    expect($marker->execute((string) $snapshot->getKey(), $cutoff))->toBeFalse();
    Event::assertDispatchedTimes(CartAbandoned::class, 1);
});

it('starts checkout only once even when called with a stale snapshot model', function (): void {
    config(['cart.owner.enabled' => false]);
    Event::fake([CartCheckoutStarted::class]);

    $snapshot = CartSnapshot::factory()->create(['checkout_started_at' => null]);
    $action = app(StartCartCheckout::class);
    $action->execute($snapshot);
    $action->execute($snapshot);

    expect($snapshot->fresh()->checkout_started_at)->not->toBeNull();
    Event::assertDispatchedTimes(CartCheckoutStarted::class, 1);
});
