<?php

declare(strict_types=1);

use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;

beforeEach(function (): void {
    $this->workflow = app(EventLifecycleWorkflow::class);
});

it('publishes event', function (): void {
    $event = Event::factory()->create(['status' => 'scheduled']);

    $this->workflow->publish($event);

    expect($event->fresh()->status->getValue())->toBe(Event::PUBLISHED);
});

it('archives event', function (): void {
    $event = Event::factory()->published()->create();

    $this->workflow->archive($event);

    expect($event->fresh()->status->getValue())->toBe('archived');
});

it('cancels occurrence', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

    $this->workflow->cancel($occurrence, 'Speaker unavailable');

    expect($occurrence->fresh()->status->getValue())->toBe('cancelled');
});

it('delays occurrence', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

    $this->workflow->delay($occurrence, 'Technical issues');

    expect($occurrence->fresh()->status->getValue())->toBe('delayed');
});
