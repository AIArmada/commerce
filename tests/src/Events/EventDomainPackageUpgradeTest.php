<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\EnsureOccurrenceAction;
use AIArmada\Events\Contracts\EventDisplayTimezoneResolver;
use AIArmada\Events\Contracts\EventSearchPayloadResolver;
use AIArmada\Events\Enums\EventModerationStatus;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\EventVisibility;
use AIArmada\Events\Models\Event as EventModel;
use AIArmada\Events\Models\EventSpeaker;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Venue;
use AIArmada\Events\Resolvers\DefaultEventDisplayTimezoneResolver;
use AIArmada\Events\Resolvers\DefaultEventSearchPayloadResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

it('adds reusable public event domain columns and speaker links', function (): void {
    expect(Schema::hasColumn('events', 'organizer_type'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'organizer_id'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'moderation_status'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'visibility'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'published_at'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'media_references'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'taxonomy'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'search_keywords'))->toBeTrue()
        ->and(Schema::hasColumn('event_venues', 'latitude'))->toBeTrue()
        ->and(Schema::hasColumn('event_venues', 'longitude'))->toBeTrue()
        ->and(Schema::hasTable('event_speakers'))->toBeTrue()
        ->and((new EventSpeaker)->getTable())->toBe('event_speakers');
});

it('imports organizer speakers media taxonomy moderation visibility and location data', function (): void {
    $organizer = User::query()->create([
        'name' => 'Organizer Org',
        'email' => 'organizer@example.com',
        'password' => 'secret',
    ]);

    $speaker = User::query()->create([
        'name' => 'Speaker One',
        'email' => 'speaker@example.com',
        'password' => 'secret',
    ]);

    $occurrence = app(EnsureOccurrenceAction::class)->handle(
        series: [
            'name' => 'Public Event Platform',
            'slug' => 'public-event-platform',
        ],
        event: [
            'name' => 'Reusable Event Domain',
            'slug' => 'reusable-event-domain',
            'organizer' => $organizer,
            'status' => EventStatus::Active,
            'moderation_status' => EventModerationStatus::Pending,
            'visibility' => EventVisibility::Unlisted,
            'default_timezone' => 'Asia/Kuala_Lumpur',
            'published_at' => '2026-06-01 09:00:00',
            'media_references' => [
                'cover' => 'https://example.com/cover.jpg',
            ],
            'taxonomy' => [
                'topic' => ['ai', 'events'],
            ],
            'search_keywords' => 'public events registration speakers',
            'speakers' => [
                [
                    'speaker' => $speaker,
                    'role' => 'Keynote',
                ],
                [
                    'display_name' => 'Display Only Speaker',
                    'role' => 'Panelist',
                ],
            ],
        ],
        venue: [
            'name' => 'Platform Hall',
            'slug' => 'platform-hall',
            'location_type' => 'hybrid',
            'city' => 'Kuala Lumpur',
            'country' => 'MY',
            'latitude' => 3.139,
            'longitude' => 101.6869,
            'map_url' => 'https://maps.example.com/platform-hall',
            'external_id' => 'place-platform-hall',
            'timezone' => 'Asia/Kuala_Lumpur',
        ],
        occurrence: [
            'starts_at' => '2026-06-05 08:45:00',
            'timezone' => 'Asia/Kuala_Lumpur',
        ],
    );

    [$event, $venue] = OwnerContext::withOwner(null, static fn (): array => [
        EventModel::query()
            ->with(['organizer', 'speakers.speaker'])
            ->findOrFail($occurrence->event_id),
        Venue::query()->findOrFail($occurrence->venue_id),
    ]);

    expect($event->organizer)->toBeInstanceOf(User::class)
        ->and($event->organizer?->getKey())->toBe($organizer->getKey())
        ->and($event->moderation_status)->toBe(EventModerationStatus::Pending)
        ->and($event->visibility)->toBe(EventVisibility::Unlisted)
        ->and($event->published_at?->copy()->setTimezone('UTC')->format('Y-m-d H:i:s'))->toBe('2026-06-01 01:00:00')
        ->and($event->taxonomyTerms('topic'))->toBe(['ai', 'events'])
        ->and($event->media_references['cover'] ?? null)->toBe('https://example.com/cover.jpg')
        ->and($event->speakers)->toHaveCount(2)
        ->and($event->speakers->first()?->speaker)->toBeInstanceOf(User::class)
        ->and($event->speakers->first()?->display_name)->toBe('Speaker One')
        ->and($venue->location_type)->toBe('hybrid')
        ->and($venue->latitude)->toBe('3.1390000')
        ->and($venue->longitude)->toBe('101.6869000')
        ->and($venue->map_url)->toBe('https://maps.example.com/platform-hall')
        ->and($venue->external_id)->toBe('place-platform-hall');
});

it('separates public accessibility from discoverability and search visibility', function (): void {
    $now = Carbon::parse('2026-06-06 12:00:00', 'UTC');

    $publicEvent = EventModel::query()->create([
        'name' => 'Public Approved Event',
        'slug' => 'public-approved-event',
        'status' => EventStatus::Active,
        'moderation_status' => EventModerationStatus::Approved,
        'visibility' => EventVisibility::Public,
        'published_at' => $now->copy()->subDay(),
    ]);

    $unlistedEvent = EventModel::query()->create([
        'name' => 'Unlisted Approved Event',
        'slug' => 'unlisted-approved-event',
        'status' => EventStatus::Active,
        'moderation_status' => EventModerationStatus::Approved,
        'visibility' => EventVisibility::Unlisted,
        'published_at' => $now->copy()->subDay(),
    ]);

    EventModel::query()->create([
        'name' => 'Pending Public Event',
        'slug' => 'pending-public-event',
        'status' => EventStatus::Active,
        'moderation_status' => EventModerationStatus::Pending,
        'visibility' => EventVisibility::Public,
    ]);

    EventModel::query()->create([
        'name' => 'Future Public Event',
        'slug' => 'future-public-event',
        'status' => EventStatus::Active,
        'moderation_status' => EventModerationStatus::Approved,
        'visibility' => EventVisibility::Public,
        'published_at' => $now->copy()->addDay(),
    ]);

    expect($publicEvent->isPubliclyAccessible($now))->toBeTrue()
        ->and($publicEvent->isPubliclyDiscoverable($now))->toBeTrue()
        ->and($unlistedEvent->isPubliclyAccessible($now))->toBeTrue()
        ->and($unlistedEvent->isPubliclyDiscoverable($now))->toBeFalse()
        ->and(EventModel::query()->publiclyAccessible($now)->orderBy('slug')->pluck('slug')->all())->toBe([
            'public-approved-event',
            'unlisted-approved-event',
        ])
        ->and(EventModel::query()->publiclyDiscoverable($now)->orderBy('slug')->pluck('slug')->all())->toBe([
            'public-approved-event',
        ])
        ->and(EventModel::query()->searchable($now)->orderBy('slug')->pluck('slug')->all())->toBe([
            'public-approved-event',
        ]);
});

it('exposes configurable search payload and timezone display contracts', function (): void {
    $event = EventModel::query()->create([
        'name' => 'Searchable Event',
        'slug' => 'searchable-event',
        'status' => EventStatus::Active,
        'moderation_status' => EventModerationStatus::Approved,
        'visibility' => EventVisibility::Public,
        'default_timezone' => 'Asia/Kuala_Lumpur',
        'taxonomy' => [
            'topic' => ['search'],
        ],
        'media_references' => [
            'cover' => 'cover.jpg',
        ],
        'search_keywords' => 'search speakers timezone',
    ]);

    EventSpeaker::query()->create([
        'event_id' => $event->id,
        'display_name' => 'Search Speaker',
        'order_column' => 1,
    ]);

    $occurrence = Occurrence::query()->create([
        'event_id' => $event->id,
        'status' => 'scheduled',
        'starts_at' => Carbon::parse('2026-06-05 00:45:00', 'UTC'),
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $viewer = new User;
    $viewer->setAttribute('timezone', 'Asia/Tokyo');

    $payload = $event->fresh(['speakers'])?->toSearchableArray();

    expect(app(EventSearchPayloadResolver::class))->toBeInstanceOf(DefaultEventSearchPayloadResolver::class)
        ->and(app(EventDisplayTimezoneResolver::class))->toBeInstanceOf(DefaultEventDisplayTimezoneResolver::class)
        ->and($event->mediaCollections())->toMatchArray([
            'cover' => 'cover',
            'poster' => 'poster',
            'gallery' => 'gallery',
        ])
        ->and($payload)->toMatchArray([
            'id' => $event->id,
            'name' => 'Searchable Event',
            'visibility' => 'public',
            'moderation_status' => 'approved',
            'taxonomy' => [
                'topic' => ['search'],
            ],
            'speaker_names' => ['Search Speaker'],
        ])
        ->and($event->displayTimezone())->toBe('Asia/Kuala_Lumpur')
        ->and($occurrence->displayTimezone($viewer))->toBe('Asia/Tokyo')
        ->and($occurrence->startsAtForDisplay($viewer)->format('Y-m-d H:i'))->toBe('2026-06-05 09:45');
});
