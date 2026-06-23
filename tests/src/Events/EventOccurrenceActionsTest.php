<?php

declare(strict_types=1);

use AIArmada\Events\Actions\BatchCreateOccurrencesAction;
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
