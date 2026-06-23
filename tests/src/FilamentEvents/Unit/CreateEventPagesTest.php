<?php

declare(strict_types=1);

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\FilamentEvents\Resources\EventOccurrenceResource\Pages\CreateEventOccurrence;
use AIArmada\FilamentEvents\Resources\EventSessionResource\Pages\CreateEventSession;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
});

it('creates event occurrences through the create page', function (): void {
    $event = Event::factory()->create([
        'timezone' => 'Asia/Kuala_Lumpur',
        'visibility' => 'private',
        'delivery_mode' => 'online',
    ]);

    $page = app(CreateEventOccurrence::class);
    $method = new ReflectionMethod(CreateEventOccurrence::class, 'handleRecordCreation');

    $occurrence = $method->invoke($page, [
        'event_id' => $event->id,
        'title' => 'Launch Day',
        'starts_at' => '2026-07-01 09:00:00',
        'ends_at' => '2026-07-01 11:00:00',
        'slug' => '',
    ]);

    expect($occurrence)->toBeInstanceOf(EventOccurrence::class)
        ->and($occurrence->event_id)->toBe($event->id)
        ->and($occurrence->slug)->toBe('launch-day')
        ->and($occurrence->status->getValue())->toBe(EventOccurrence::SCHEDULED)
        ->and($occurrence->visibility)->toBe('private')
        ->and($occurrence->delivery_mode)->toBe('online')
        ->and($occurrence->timezone)->toBe('Asia/Kuala_Lumpur');
});

it('creates event sessions through the create page', function (): void {
    $event = Event::factory()->create([
        'timezone' => 'Asia/Kuala_Lumpur',
        'delivery_mode' => 'online',
    ]);

    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
        'timezone' => 'Asia/Kuala_Lumpur',
        'visibility' => 'private',
        'delivery_mode' => 'online',
    ]);

    EventSession::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'sort_order' => 5,
        'starts_at' => '2026-07-01 10:00:00',
        'ends_at' => '2026-07-01 11:00:00',
    ]);

    $page = app(CreateEventSession::class);
    $method = new ReflectionMethod(CreateEventSession::class, 'handleRecordCreation');

    $session = $method->invoke($page, [
        'event_occurrence_id' => $occurrence->id,
        'title' => 'Keynote',
        'starts_at' => '2026-07-01 12:00:00',
        'ends_at' => '2026-07-01 13:00:00',
        'slug' => '',
    ]);

    expect($session)->toBeInstanceOf(EventSession::class)
        ->and($session->event_id)->toBe($event->id)
        ->and($session->event_occurrence_id)->toBe($occurrence->id)
        ->and($session->slug)->toBe('keynote')
        ->and($session->sort_order)->toBe(6)
        ->and($session->status->getValue())->toBe('scheduled')
        ->and($session->visibility)->toBe('private')
        ->and($session->delivery_mode)->toBe('online')
        ->and($session->timezone)->toBe('Asia/Kuala_Lumpur');
});
