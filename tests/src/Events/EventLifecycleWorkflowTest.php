<?php

declare(strict_types=1);

use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Catalog\Models\EventOccurrence;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function (): void {
    $this->workflow = app(EventLifecycleWorkflow::class);
});

it('archives event', function (): void {
    $event = Event::factory()->published()->create();

    $this->workflow->archive('event', (string) $event->getKey());

    expect($event->fresh()->status->getValue())->toBe('archived')
        ->and($event->fresh()->archived_at)->not->toBeNull();
});

it('cancels occurrence', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

    $this->workflow->cancel('occurrence', (string) $occurrence->getKey(), 'Speaker unavailable');

    $occurrence->refresh();

    expect($occurrence->status->getValue())->toBe('cancelled')
        ->and($occurrence->cancelled_at)->not->toBeNull()
        ->and($occurrence->status_reason)->toBe('Speaker unavailable');
});

it('delays occurrence', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

    $this->workflow->delay('occurrence', (string) $occurrence->getKey(), 'Technical issues');

    $occurrence->refresh();

    expect($occurrence->status->getValue())->toBe('delayed')
        ->and($occurrence->delayed_at)->not->toBeNull();
});

it('publishes event', function (): void {
    $event = Event::factory()->create(['status' => 'scheduled']);

    $this->workflow->publish((string) $event->getKey());

    $event->refresh();

    expect($event->status->getValue())->toBe('published')
        ->and($event->published_at)->not->toBeNull();
});

it('postpones occurrence', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

    $this->workflow->postpone('occurrence', (string) $occurrence->getKey(), 'Venue unavailable');

    $occurrence->refresh();

    expect($occurrence->status->getValue())->toBe('postponed')
        ->and($occurrence->postponed_at)->not->toBeNull();
});

it('completes occurrence', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

    $this->workflow->complete('occurrence', (string) $occurrence->getKey());

    $occurrence->refresh();

    expect($occurrence->status->getValue())->toBe('completed')
        ->and($occurrence->completed_at)->not->toBeNull();
});

it('rejects unknown lifecycle target types', function (): void {
    expect(fn () => $this->workflow->cancel('unknown', 'missing-id'))
        ->toThrow(InvalidArgumentException::class, 'Unknown lifecycle target type');
});

it('rejects lifecycle use on missing occurrence ids', function (): void {
    expect(fn () => $this->workflow->cancel('occurrence', '01933f00-0000-7000-8000-000000000000'))
        ->toThrow(ModelNotFoundException::class);
});
