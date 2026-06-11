<?php

declare(strict_types=1);

namespace Tests\src\Events;

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;

beforeEach(function (): void {
    config(['events.features.owner.enabled' => true]);
    config(['events.features.owner.auto_assign_on_create' => false]);
});

it('prevents reading events across owners', function (): void {
    $ownerA = User::query()->create(['name' => 'Owner A', 'email' => 'owner-a@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'Owner B', 'email' => 'owner-b@example.com', 'password' => 'secret']);

    $eventA = Event::query()->create(['name' => 'Owner A Event', 'slug' => 'owner-a-event', 'status' => 'active', 'owner_type' => $ownerA->getMorphClass(), 'owner_id' => $ownerA->getKey()]);

    expect(Event::query()->withoutOwnerScope()->forOwner($ownerB)->get())->not->toContain($eventA);
});

it('prevents reading occurrences across owners', function (): void {
    $ownerA = User::query()->create(['name' => 'Owner A', 'email' => 'occ-owner-a@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'Owner B', 'email' => 'occ-owner-b@example.com', 'password' => 'secret']);

    $event = Event::query()->create(['name' => 'Occurrence Owner A', 'slug' => 'occurrence-owner-a', 'status' => 'active', 'owner_type' => $ownerA->getMorphClass(), 'owner_id' => $ownerA->getKey()]);
    $occurrence = Occurrence::query()->create(['event_id' => $event->id, 'starts_at' => now('UTC')->addDay(), 'timezone' => 'UTC', 'owner_type' => $ownerA->getMorphClass(), 'owner_id' => $ownerA->getKey()]);

    expect(Occurrence::query()->withoutOwnerScope()->forOwner($ownerB)->get())->not->toContain($occurrence);
});

it('prevents reading registrations across owners', function (): void {
    $ownerA = User::query()->create(['name' => 'Owner A', 'email' => 'reg-owner-a@example.com', 'password' => 'secret']);
    $ownerB = User::query()->create(['name' => 'Owner B', 'email' => 'reg-owner-b@example.com', 'password' => 'secret']);

    $event = Event::query()->create(['name' => 'Registration Owner', 'slug' => 'registration-owner', 'status' => 'active', 'owner_type' => $ownerA->getMorphClass(), 'owner_id' => $ownerA->getKey()]);
    $occurrence = Occurrence::query()->create(['event_id' => $event->id, 'starts_at' => now('UTC')->addDay(), 'timezone' => 'UTC', 'owner_type' => $ownerA->getMorphClass(), 'owner_id' => $ownerA->getKey()]);
    $registration = Registration::query()->create(['occurrence_id' => $occurrence->id, 'first_name' => 'Test', 'last_name' => 'User', 'owner_type' => $ownerA->getMorphClass(), 'owner_id' => $ownerA->getKey()]);

    expect(Registration::query()->withoutOwnerScope()->forOwner($ownerB)->get())->not->toContain($registration);
});
