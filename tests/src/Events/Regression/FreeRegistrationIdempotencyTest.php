<?php

declare(strict_types=1);

use AIArmada\Events\Events\EventFreeRegistrationConfirmed;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use AIArmada\Events\Features\Registrations\Actions\RegisterForFreeAction;
use Illuminate\Support\Facades\Event as EventFacade;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
    config()->set('events.features.free_only.auto_derive_pricing_from_ticket_types', true);
});

it('dispatches one confirmation event when an idempotent retry returns the same batch', function (): void {
    EventFacade::fake([EventFreeRegistrationConfirmed::class]);

    $event = Event::factory()->free()->published()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

    $action = app(RegisterForFreeAction::class);
    $participants = [
        ['name' => 'Alice', 'is_primary' => true],
    ];

    $first = $action->execute('occurrence', (string) $occurrence->getKey(), $participants, options: [
        'idempotency_key' => 'checkout-retry-1',
    ]);
    $retry = $action->execute('occurrence', (string) $occurrence->getKey(), $participants, options: [
        'idempotency_key' => 'checkout-retry-1',
    ]);

    expect($retry)->toBe($first);

    EventFacade::assertDispatchedTimes(EventFreeRegistrationConfirmed::class, 1);
});
