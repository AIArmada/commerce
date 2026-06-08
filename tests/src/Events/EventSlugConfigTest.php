<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\EnsureOccurrenceAction;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\OccurrenceStatus;
use AIArmada\Events\Models\Event as EventModel;
use Illuminate\Support\Facades\Config;

it('derives the slug from the name field by default', function (): void {
    Config::set('events.slug.source_field', 'name');
    Config::set('events.slug.max_length', 60);

    $occurrence = app(EnsureOccurrenceAction::class)->handle(
        series: [
            'name' => 'Slug Source Series',
            'slug' => 'slug-source-series',
        ],
        event: [
            'name' => 'Slug Source Event',
        ],
        occurrence: [
            'starts_at' => now('UTC')->addDay(),
            'timezone' => 'UTC',
            'status' => OccurrenceStatus::Scheduled,
        ],
    );

    $event = OwnerContext::withOwner(null, static fn (): EventModel => EventModel::query()->findOrFail($occurrence->event_id));

    expect($event->slug)->toBe('slug-source-event');
});

it('respects a custom slug source field', function (): void {
    Config::set('events.slug.source_field', 'title');
    Config::set('events.slug.max_length', 60);

    $occurrence = app(EnsureOccurrenceAction::class)->handle(
        series: [
            'name' => 'Title Slug Series',
            'slug' => 'title-slug-series',
        ],
        event: [
            'name' => 'Name That Is Ignored For Slug',
            'title' => 'Custom Title Used For Slug',
        ],
        occurrence: [
            'starts_at' => now('UTC')->addDay(),
            'timezone' => 'UTC',
            'status' => OccurrenceStatus::Scheduled,
        ],
    );

    $event = OwnerContext::withOwner(null, static fn (): EventModel => EventModel::query()->findOrFail($occurrence->event_id));

    expect($event->slug)->toBe('custom-title-used-for-slug');
});

it('respects a custom slug max length', function (): void {
    Config::set('events.slug.source_field', 'name');
    Config::set('events.slug.max_length', 15);

    $occurrence = app(EnsureOccurrenceAction::class)->handle(
        series: [
            'name' => 'Long Slug Series',
            'slug' => 'long-slug-series',
        ],
        event: [
            'name' => 'A Very Long Event Name That Should Be Truncated',
        ],
        occurrence: [
            'starts_at' => now('UTC')->addDay(),
            'timezone' => 'UTC',
            'status' => OccurrenceStatus::Scheduled,
        ],
    );

    $event = OwnerContext::withOwner(null, static fn (): EventModel => EventModel::query()->findOrFail($occurrence->event_id));

    expect($event->slug)->toBe('a-very-long-eve');
});

it('prefers an explicit slug when both explicit and derived are provided', function (): void {
    Config::set('events.slug.source_field', 'name');

    $occurrence = app(EnsureOccurrenceAction::class)->handle(
        series: [
            'name' => 'Explicit Slug Series',
            'slug' => 'explicit-slug-series',
        ],
        event: [
            'name' => 'A Different Name',
            'slug' => 'i-am-explicit',
        ],
        occurrence: [
            'starts_at' => now('UTC')->addDay(),
            'timezone' => 'UTC',
            'status' => OccurrenceStatus::Scheduled,
        ],
    );

    $event = OwnerContext::withOwner(null, static fn (): EventModel => EventModel::query()->findOrFail($occurrence->event_id));

    expect($event->slug)->toBe('i-am-explicit');
});

it('exposes a title attribute on the event model that mirrors the name', function (): void {
    $event = EventModel::query()->create([
        'name' => 'Friday Khutbah',
        'slug' => 'friday-khutbah-' . uniqid(),
        'status' => EventStatus::Active,
        'default_timezone' => 'UTC',
    ]);

    expect($event->title)->toBe('Friday Khutbah')
        ->and($event->name)->toBe('Friday Khutbah');
});
