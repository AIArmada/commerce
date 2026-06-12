<?php

declare(strict_types=1);

use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;

it('creates an event in draft status', function () {
    $event = Event::factory()->create();

    expect($event->status)->toBe(Event::DRAFT);
});

it('creates an event with an occurrence', function () {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

    expect($event->occurrences)->toHaveCount(1);
    expect($occurrence->event_id)->toBe($event->id);
});

it('creates an event with owner polymorphic reference', function () {
    $owner = User::query()->create([
        'name' => 'Event Owner',
        'email' => 'event-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $event = OwnerContext::withOwner($owner, function (): Event {
        return Event::factory()->create();
    });

    expect($event->owner_type)->toBe($owner->getMorphClass())
        ->and($event->owner_id)->toBe((string) $owner->getKey());
});

it('publishes event using lifecycle workflow', function () {
    $event = Event::factory()->create();

    app(EventLifecycleWorkflow::class)->publish($event);
    $event->refresh();

    expect($event->status)->toBe(Event::PUBLISHED);
    expect($event->published_at)->not->toBeNull();
});

it('cancels event and creates change log', function () {
    $event = Event::factory()->published()->create();

    app(EventLifecycleWorkflow::class)->cancel($event, 'Test cancellation');
    $event->refresh();

    expect($event->status)->toBe('cancelled');
    expect($event->cancelled_at)->not->toBeNull();
    expect($event->changeLogs)->toHaveCount(1);
});
