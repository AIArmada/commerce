<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventRegistration;
use AIArmada\FilamentEvents\Resources\EventRegistrationResource;

it('scopes the event registration resource query to the current owner', function (): void {
    $ownerA = User::query()->create([
        'name' => 'Owner A',
        'email' => 'filament-events-owner-a-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $ownerB = User::query()->create([
        'name' => 'Owner B',
        'email' => 'filament-events-owner-b-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $ownerARegistration = OwnerContext::withOwner($ownerA, function (): EventRegistration {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        return EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
        ]);
    });

    $ownerBRegistration = OwnerContext::withOwner($ownerB, function (): EventRegistration {
        $event = Event::factory()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        return EventRegistration::factory()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
        ]);
    });

    $ownerAIds = OwnerContext::withOwner($ownerA, function (): array {
        return EventRegistrationResource::getEloquentQuery()->pluck('id')->all();
    });

    $ownerBIds = OwnerContext::withOwner($ownerB, function (): array {
        return EventRegistrationResource::getEloquentQuery()->pluck('id')->all();
    });

    expect($ownerAIds)->toBe([$ownerARegistration->id])
        ->and($ownerBIds)->toBe([$ownerBRegistration->id]);
});
