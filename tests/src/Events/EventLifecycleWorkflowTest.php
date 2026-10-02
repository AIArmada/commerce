<?php

declare(strict_types=1);

use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;

beforeEach(function (): void {
    $this->workflow = app(EventLifecycleWorkflow::class);
});

it('archives event', function (): void {
    $event = Event::factory()->published()->create();

    $this->workflow->archive($event);

    expect($event->status->getValue())->toBe('archived')
        ->and($event->archived_at)->not->toBeNull()
        ->and($event->fresh()->status->getValue())->toBe('archived');
});

it('cancels occurrence', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

    $this->workflow->cancel($occurrence, 'Speaker unavailable');

    expect($occurrence->status->getValue())->toBe('cancelled')
        ->and($occurrence->cancelled_at)->not->toBeNull()
        ->and($occurrence->status_reason)->toBe('Speaker unavailable')
        ->and($occurrence->fresh()->status->getValue())->toBe('cancelled');
});

it('delays occurrence', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

    $this->workflow->delay($occurrence, 'Technical issues');

    expect($occurrence->status->getValue())->toBe('delayed')
        ->and($occurrence->delayed_at)->not->toBeNull()
        ->and($occurrence->fresh()->status->getValue())->toBe('delayed');
});

it('publishes event on the same instance', function (): void {
    $event = Event::factory()->create(['status' => 'scheduled']);

    $this->workflow->publish($event);

    expect($event->status->getValue())->toBe('published')
        ->and($event->published_at)->not->toBeNull()
        ->and($event->fresh()->status->getValue())->toBe('published');
});

it('postpones occurrence on the same instance', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

    $this->workflow->postpone($occurrence, 'Venue unavailable');

    expect($occurrence->status->getValue())->toBe('postponed')
        ->and($occurrence->postponed_at)->not->toBeNull()
        ->and($occurrence->fresh()->status->getValue())->toBe('postponed');
});

it('completes occurrence on the same instance', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

    $this->workflow->complete($occurrence);

    expect($occurrence->status->getValue())->toBe('completed')
        ->and($occurrence->completed_at)->not->toBeNull()
        ->and($occurrence->fresh()->status->getValue())->toBe('completed');
});

it('rejects lifecycle use on unsaved occurrence models', function (): void {
    $event = Event::factory()->create();
    $occurrence = new EventOccurrence(['event_id' => $event->id]);

    expect(fn () => $this->workflow->cancel($occurrence))->toThrow(InvalidArgumentException::class, 'persisted');
});
