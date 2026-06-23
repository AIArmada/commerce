<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\RecordHeadcountLogAction;
use AIArmada\Events\Exceptions\NotOpenDoorEventException;
use AIArmada\Events\Exceptions\WrongOpenDoorModeException;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;

beforeEach(function (): void {
    config()->set('events.features.free_only.open_door_mode', 'headcount');
    config()->set('events.features.owner.enabled', true);
});

it('records a headcount log for an open-door event', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->freeOpenDoor()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        $log = app(RecordHeadcountLogAction::class)->execute(
            target: $occurrence,
            count: 50,
            intervalLabel: '10:00-10:15',
            notes: 'Peak crowd',
        );

        expect($log->exists)->toBeTrue();
        expect($log->event_id)->toBe($event->id);
        expect($log->event_occurrence_id)->toBe($occurrence->id);
        expect($log->count)->toBe(50);
        expect($log->interval_label)->toBe('10:00-10:15');
        expect($log->notes)->toBe('Peak crowd');
    });
});

it('throws for non-open-door events', function (): void {
    expect(fn () => OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->free()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        app(RecordHeadcountLogAction::class)->execute(
            target: $occurrence,
            count: 1,
        );
    }))->toThrow(NotOpenDoorEventException::class);
});

it('throws when open door mode is not headcount', function (): void {
    config()->set('events.features.free_only.open_door_mode', 'walk_in');
    expect(fn () => OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->freeOpenDoor()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        app(RecordHeadcountLogAction::class)->execute(
            target: $occurrence,
            count: 1,
        );
    }))->toThrow(WrongOpenDoorModeException::class);
});

it('enforces minimum count of 1', function (): void {
    OwnerContext::withOwner(null, function (): void {
        $event = Event::factory()->freeOpenDoor()->published()->create();
        $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

        $log = app(RecordHeadcountLogAction::class)->execute(
            target: $occurrence,
            count: 0,
        );

        expect($log->count)->toBe(1);
    });
});
