<?php

declare(strict_types=1);

namespace Tests\src\Events;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use AIArmada\Events\Services\DefaultEventLifecycleWorkflow;
use AIArmada\Commerce\Tests\TestCase;

beforeEach(function (): void {
    config(['events.features.owner.enabled' => true]);
    config(['events.features.owner.auto_assign_on_create' => false]);
});

it('prevents reading events across owners', function (): void {
    $ownerA = (object) ['id' => 'owner-a'];
    $ownerB = (object) ['id' => 'owner-b'];

    $eventA = Event::factory()->create(['owner_type' => get_class($ownerA), 'owner_id' => $ownerA->id]);

    Event::withoutOwnerScope(fn () => expect(Event::forOwner($ownerB)->get())->not->toContain($eventA));
});

it('prevents reading occurrences across owners', function (): void {
    $ownerA = (object) ['id' => 'owner-a'];
    $ownerB = (object) ['id' => 'owner-b'];

    $occurrence = Occurrence::factory()->create(['owner_type' => get_class($ownerA), 'owner_id' => $ownerA->id]);

    Occurrence::withoutOwnerScope(fn () => expect(Occurrence::forOwner($ownerB)->get())->not->toContain($occurrence));
});

it('prevents reading registrations across owners', function (): void {
    $ownerA = (object) ['id' => 'owner-a'];
    $ownerB = (object) ['id' => 'owner-b'];

    $registration = Registration::factory()->create(['owner_type' => get_class($ownerA), 'owner_id' => $ownerA->id]);

    Registration::withoutOwnerScope(fn () => expect(Registration::forOwner($ownerB)->get())->not->toContain($registration));
});
