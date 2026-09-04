<?php

declare(strict_types=1);

use AIArmada\Events\Actions\BatchCreateOccurrencesAction;
use AIArmada\Events\Actions\CreateEventOccurrenceAction;
use AIArmada\Events\Actions\UpdateEventOccurrenceAction;
use AIArmada\Events\Events\EventOccurrenceUpdated;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
});

it('creates occurrences through the batch action using the create path', function (): void {
    $event = Event::factory()->create();

    $occurrences = app(BatchCreateOccurrencesAction::class)->handle(
        $event,
        [
            [
                'title' => 'Launch Day',
                'starts_at' => '2026-07-01 09:00:00',
                'ends_at' => '2026-07-01 11:00:00',
            ],
        ],
    );

    expect($occurrences)->toHaveCount(1);

    $occurrence = $occurrences->first();

    expect($occurrence)->toBeInstanceOf(EventOccurrence::class)
        ->and($occurrence?->event_id)->toBe($event->id)
        ->and($occurrence?->slug)->toBe('launch-day')
        ->and($occurrence?->status->getValue())->toBe(EventOccurrence::SCHEDULED)
        ->and($occurrence?->visibility)->toBe('public');
});

it('normalizes occurrence titles before saving', function (): void {
    $event = Event::factory()->create();

    $occurrence = app(CreateEventOccurrenceAction::class)->handle($event, [
        'title' => '  Launch   Day  ',
        'starts_at' => '2026-07-01 09:00:00',
        'ends_at' => '2026-07-01 11:00:00',
    ]);

    expect($occurrence->title)->toBe('Launch Day')
        ->and($occurrence->slug)->toBe('launch-day');
});

it('updates occurrence content and schedule through the package action', function (): void {
    Illuminate\Support\Facades\Event::fake([EventOccurrenceUpdated::class]);

    $event = Event::factory()->create();
    $occurrence = app(CreateEventOccurrenceAction::class)->handle($event, [
        'title' => 'Original Day',
        'starts_at' => '2026-07-01 09:00:00',
        'ends_at' => '2026-07-01 11:00:00',
    ]);

    $result = app(UpdateEventOccurrenceAction::class)->handle($occurrence, [
        'title' => 'Updated Day',
        'starts_at' => '2026-07-02 10:00:00',
        'ends_at' => '2026-07-02 12:00:00',
        'capacity' => 120,
    ]);

    expect($result['changes'])->toHaveKeys(['title', 'starts_at', 'ends_at', 'capacity'])
        ->and($result['occurrence']->title)->toBe('Updated Day')
        ->and($result['occurrence']->capacity)->toBe(120);

    Illuminate\Support\Facades\Event::assertDispatched(EventOccurrenceUpdated::class);
});

it('rejects an occurrence update with an invalid time range', function (): void {
    $event = Event::factory()->create();
    $occurrence = app(CreateEventOccurrenceAction::class)->handle($event, [
        'title' => 'Day One',
        'starts_at' => '2026-07-01 09:00:00',
        'ends_at' => '2026-07-01 11:00:00',
    ]);

    app(UpdateEventOccurrenceAction::class)->handle($occurrence, [
        'starts_at' => '2026-07-01 12:00:00',
        'ends_at' => '2026-07-01 11:00:00',
    ]);
})->throws(InvalidArgumentException::class, 'Occurrence end time must be after the start time.');
