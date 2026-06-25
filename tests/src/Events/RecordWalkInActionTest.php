<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\RecordWalkInAction;
use AIArmada\Events\Events\WalkInRecorded;
use AIArmada\Events\Exceptions\NotOpenDoorEventException;
use AIArmada\Events\Exceptions\WrongOpenDoorModeException;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use Illuminate\Support\Facades\Event as EventFacade;

beforeEach(function (): void {
    config()->set('events.features.free_only.open_door_mode', 'walk_in');
    config()->set('events.features.owner.enabled', true);
});

it('records a walk-in for an open-door event', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->freeOpenDoor()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        EventFacade::fake([WalkInRecorded::class]);

        $walkIn = app(RecordWalkInAction::class)->execute(
            target: $occurrence,
            count: 3,
            notes: 'Walk-in group',
        );

        expect($walkIn->exists)->toBeTrue();
        expect($walkIn->event_id)->toBe($event->id);
        expect($walkIn->event_occurrence_id)->toBe($occurrence->id);
        expect($walkIn->count)->toBe(3);
        expect($walkIn->notes)->toBe('Walk-in group');

        EventFacade::assertDispatched(
            WalkInRecorded::class,
            fn (WalkInRecorded $recorded): bool => $recorded->walkIn->is($walkIn),
        );
    });
});

it('throws for non-open-door events', function (): void {
    expect(fn () => OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        app(RecordWalkInAction::class)->execute(
            target: $occurrence,
            count: 1,
        );
    }))->toThrow(NotOpenDoorEventException::class);
});

it('throws when open door mode is not walk_in', function (): void {
    config()->set('events.features.free_only.open_door_mode', 'headcount');
    expect(fn () => OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->freeOpenDoor()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        app(RecordWalkInAction::class)->execute(
            target: $occurrence,
            count: 1,
        );
    }))->toThrow(WrongOpenDoorModeException::class);
});

it('enforces minimum count of 1', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->freeOpenDoor()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        $walkIn = app(RecordWalkInAction::class)->execute(
            target: $occurrence,
            count: 0,
        );

        expect($walkIn->count)->toBe(1);
    });
});
