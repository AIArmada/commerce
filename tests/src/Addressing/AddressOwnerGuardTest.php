<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventLocation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', true);
    config()->set('events.integrations.addressing_enabled', true);
});

it('rejects attaching an address to an event-owned record from another owner', function (): void {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();

    $addressA = OwnerContext::withOwner($ownerA, fn (): Address => Address::query()->create([
        'line1' => 'Owner A address',
        'country_code' => 'MY',
    ]));

    [$eventB, $locationB] = OwnerContext::withOwner($ownerB, function (): array {
        $event = Event::factory()->create();

        return [
            $event,
            EventLocation::factory()->create(['event_id' => $event->getKey()]),
        ];
    });

    expect(fn () => OwnerContext::withOwner($ownerA, function () use ($locationB, $addressA): void {
        $locationB->addresses()->attach($addressA->getKey(), [
            'id' => (string) Str::orderedUuid(),
            'type' => 'primary',
            'is_primary' => true,
        ]);
    }))->toThrow(AuthorizationException::class);
});

it('allows attaching an address to an event-owned record for the matching owner', function (): void {
    $owner = User::factory()->create();

    [$event, $location, $address] = OwnerContext::withOwner($owner, function (): array {
        $event = Event::factory()->create();
        $location = EventLocation::factory()->create(['event_id' => $event->getKey()]);
        $address = Address::query()->create([
            'line1' => 'Matching owner address',
            'country_code' => 'MY',
        ]);

        return [$event, $location, $address];
    });

    OwnerContext::withOwner($owner, function () use ($location, $address): void {
        $location->addresses()->attach($address->getKey(), [
            'id' => (string) Str::orderedUuid(),
            'type' => 'primary',
            'is_primary' => true,
        ]);
    });

    expect(OwnerContext::withOwner($owner, fn (): int => $location->addresses()->count()))->toBe(1);
});

it('allows an explicit-global context to attach an address to an owned event record', function (): void {
    $owner = User::factory()->create();

    [$event, $location] = OwnerContext::withOwner($owner, function (): array {
        $event = Event::factory()->create();

        return [
            $event,
            EventLocation::factory()->create(['event_id' => $event->getKey()]),
        ];
    });

    $globalAddress = OwnerContext::withOwner(null, fn (): Address => Address::query()->create([
        'line1' => 'Global address',
        'country_code' => 'MY',
    ]));

    OwnerContext::withOwner(null, function () use ($location, $globalAddress): void {
        $location->addresses()->attach($globalAddress->getKey(), [
            'id' => (string) Str::orderedUuid(),
            'type' => 'primary',
            'is_primary' => true,
        ]);
    });

    expect(OwnerContext::withOwner(null, fn (): int => $location->addresses()->count()))->toBe(1);
});
