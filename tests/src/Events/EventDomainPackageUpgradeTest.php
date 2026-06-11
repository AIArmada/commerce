<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Actions\BackfillEventContentAction;
use AIArmada\Events\Actions\EnsureOccurrenceAction;
use AIArmada\Events\Actions\RecordEventEngagementAction;
use AIArmada\Events\Contracts\EventChangeNoticeNotificationDispatcher;
use AIArmada\Events\Contracts\EventChangeNoticeWorkflow;
use AIArmada\Events\Contracts\EventDisplayTimezoneResolver;
use AIArmada\Events\Contracts\EventModerationWorkflow;
use AIArmada\Events\Contracts\EventScheduleResolver;
use AIArmada\Events\Contracts\EventSearchPayloadResolver;
use AIArmada\Events\Data\EventAgendaItemData;
use AIArmada\Events\Data\EventChangeNoticeAudienceData;
use AIArmada\Events\Data\EventChangeNoticePayloadData;
use AIArmada\Events\Data\EventDetailData;
use AIArmada\Events\Data\EventReviewSchemaData;
use AIArmada\Events\Data\EventSearchCardData;
use AIArmada\Events\Data\EventSearchCriteria;
use AIArmada\Events\Data\EventSearchResultData;
use AIArmada\Events\Data\OccurrenceDetailData;
use AIArmada\Events\Data\RegistrationStatusData;
use AIArmada\Events\Enums\EventEngagementType;
use AIArmada\Events\Enums\EventModerationStatus;
use AIArmada\Events\Enums\EventStatus;
use AIArmada\Events\Enums\EventStructure;
use AIArmada\Events\Enums\EventVisibility;
use AIArmada\Events\Enums\RegistrationStatus;
use AIArmada\Events\Events\EventChangeNoticePublished;
use AIArmada\Events\Events\EventChangeNoticeRetracted;
use AIArmada\Events\Models\Event as EventModel;
use AIArmada\Events\Models\EventAgenda;
use AIArmada\Events\Models\EventAsset;
use AIArmada\Events\Models\EventChange;
use AIArmada\Events\Models\EventClassification;
use AIArmada\Events\Models\EventEngagement;
use AIArmada\Events\Models\EventPerson;
use AIArmada\Events\Models\EventReference;
use AIArmada\Events\Models\EventReview;
use AIArmada\Events\Models\Occurrence;
use AIArmada\Events\Models\Registration;
use AIArmada\Events\Models\Venue;
use AIArmada\Events\Resolvers\DefaultEventDisplayTimezoneResolver;
use AIArmada\Events\Resolvers\DefaultEventSearchPayloadResolver;
use AIArmada\Events\Services\EventQueryService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('adds reusable public event domain columns and people links', function (): void {
    expect(Schema::hasColumn('events', 'organizer_type'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'organizer_id'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'parent_event_id'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'moderation_status'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'visibility'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'structure'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'published_at'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'media_references'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'taxonomy'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'search_keywords'))->toBeTrue()
        ->and(Schema::hasColumn('event_venues', 'latitude'))->toBeTrue()
        ->and(Schema::hasColumn('event_venues', 'longitude'))->toBeTrue()
        ->and(Schema::hasColumn('event_occurrences', 'schedule_mode'))->toBeTrue()
        ->and(Schema::hasColumn('event_occurrences', 'schedule_reference_key'))->toBeTrue()
        ->and(Schema::hasColumn('event_occurrences', 'registration_mode'))->toBeTrue()
        ->and(Schema::hasColumn('event_occurrences', 'duplicate_strategy'))->toBeTrue()
        ->and(Schema::hasColumn('event_occurrences', 'waitlist_enabled'))->toBeTrue()
        ->and(Schema::hasColumn('event_occurrences', 'approval_required'))->toBeTrue()
        ->and(Schema::hasTable('event_speakers'))->toBeTrue()
        ->and(Schema::hasTable('event_classifications'))->toBeTrue()
        ->and(Schema::hasTable('event_assets'))->toBeTrue()
        ->and(Schema::hasTable('event_submissions'))->toBeTrue()
        ->and(Schema::hasTable('event_reviews'))->toBeTrue()
        ->and(Schema::hasTable('event_change_notices'))->toBeTrue()
        ->and(Schema::hasTable('event_attendance'))->toBeTrue()
        ->and(Schema::hasTable('event_engagements'))->toBeTrue()
        ->and(Schema::hasColumn('event_speakers', 'person_type'))->toBeTrue()
        ->and(Schema::hasColumn('event_speakers', 'person_id'))->toBeTrue()
        ->and(Schema::hasColumn('event_speakers', 'role_key'))->toBeTrue()
        ->and(Schema::hasColumn('event_speakers', 'role_label'))->toBeTrue()
        ->and(Schema::hasColumn('event_speakers', 'visibility'))->toBeTrue()
        ->and(Schema::hasColumn('event_speakers', 'speaker_type'))->toBeFalse()
        ->and(Schema::hasColumn('event_speakers', 'speaker_id'))->toBeFalse()
        ->and((new EventPerson)->getTable())->toBe('event_speakers');
});

it('supports parent child hierarchies and generic people assignments', function (): void {
    $occurrence = app(EnsureOccurrenceAction::class)->handle(
        series: [
            'name' => 'Hierarchy Event Platform',
            'slug' => 'hierarchy-event-platform',
        ],
        event: [
            'name' => 'Child Session',
            'slug' => 'child-session',
            'structure' => EventStructure::Session,
            'parent_event' => [
                'name' => 'Parent Program',
                'slug' => 'parent-program',
                'structure' => EventStructure::Program,
            ],
            'people' => [
                [
                    'display_name' => 'Host Speaker',
                    'role_key' => 'host',
                    'role_label' => 'Host',
                    'visibility' => 'public',
                ],
            ],
        ],
        occurrence: [
            'starts_at' => '2026-06-05 08:45:00',
            'timezone' => 'Asia/Kuala_Lumpur',
        ],
    );

    OwnerContext::withOwner(null, static function () use ($occurrence): void {
        $standalone = EventModel::query()->create([
            'name' => 'Standalone Event',
            'slug' => 'standalone-event',
            'status' => EventStatus::Active,

            'registration_required' => true,
            'moderation_status' => EventModerationStatus::Approved,
            'visibility' => EventVisibility::Public,
            'structure' => EventStructure::Standalone,
        ]);

        $childEvent = EventModel::query()
            ->with(['parentEvent.childEvents', 'people'])
            ->findOrFail($occurrence->event_id);

        expect($childEvent->structure)->toBe(EventStructure::Session)
            ->and($childEvent->isChild())->toBeTrue()
            ->and($childEvent->parentEvent?->structure)->toBe(EventStructure::Program)
            ->and($childEvent->parentEvent?->childEvents)->toHaveCount(1)
            ->and($childEvent->people)->toHaveCount(1)
            ->and($childEvent->people->first()?->role_key)->toBe('host')
            ->and($childEvent->people->first()?->role_label)->toBe('Host')
            ->and($childEvent->people->first()?->visibility)->toBe(EventVisibility::Public)
            ->and($childEvent->people->first()?->person)->toBeNull()
            ->and($standalone->isStandalone())->toBeTrue()
            ->and(EventModel::query()->rootEvents()->orderBy('slug')->pluck('slug')->all())->toBe([
                'parent-program',
                'standalone-event',
            ])
            ->and(EventModel::query()->programs()->pluck('slug')->all())->toContain('parent-program')
            ->and(EventModel::query()->sessions()->pluck('slug')->all())->toContain('child-session')
            ->and(EventModel::query()->standalone()->pluck('slug')->all())->toContain('standalone-event');
    });
});

it('imports organizer people media taxonomy moderation visibility and location data', function (): void {
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

    $venue = Venue::query()->create([
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

            'registration_required' => true,
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
            'search_keywords' => 'public events registration people',
            'people' => [
                [
                    'person' => $speaker,
                    'role' => 'Keynote',
                ],
                [
                    'display_name' => 'Display Only Speaker',
                    'role' => 'Panelist',
                ],
            ],
        ],
        occurrence: [
            'starts_at' => '2026-06-05 08:45:00',
            'timezone' => 'Asia/Kuala_Lumpur',
        ],
        address: $venue,
    );

    $event = OwnerContext::withOwner(null, static fn (): EventModel => EventModel::query()
        ->with(['organizer', 'people.person', 'classifications', 'assets'])
        ->findOrFail($occurrence->event_id));
    $address = Venue::query()->findOrFail($occurrence->address_id);

    expect($event->organizer)->toBeInstanceOf(User::class)
        ->and($event->organizer?->getKey())->toBe($organizer->getKey())
        ->and($event->moderation_status)->toBe(EventModerationStatus::Pending)
        ->and($event->visibility)->toBe(EventVisibility::Unlisted)
        ->and($event->published_at?->copy()->setTimezone('UTC')->format('Y-m-d H:i:s'))->toBe('2026-06-01 01:00:00')
        ->and($event->taxonomyTerms('topic'))->toBe(['ai', 'events'])
        ->and($event->classifications)->toHaveCount(2)
        ->and($event->classifications->pluck('term_key')->all())->toBe(['ai', 'events'])
        ->and($event->media_references['cover'] ?? null)->toBe('https://example.com/cover.jpg')
        ->and($event->assetReferences('cover'))->toBe(['https://example.com/cover.jpg'])
        ->and($event->assets)->toHaveCount(1)
        ->and($event->assets->first()?->role_key)->toBe('cover')
        ->and($event->people)->toHaveCount(2)
        ->and($event->people->first()?->person)->toBeInstanceOf(User::class)
        ->and($event->people->first()?->display_name)->toBe('Speaker One')
        ->and($address->location_type)->toBe('hybrid')
        ->and($address->latitude)->toBe('3.1390000')
        ->and($address->longitude)->toBe('101.6869000')
        ->and($address->map_url)->toBe('https://maps.example.com/platform-hall')
        ->and($address->external_id)->toBe('place-platform-hall');
});

it('persists classification sources and supports multi-key filtering', function (): void {
    $source = User::query()->create([
        'name' => 'Classification Source',
        'email' => 'classification-source@example.com',
        'password' => 'secret',
    ]);

    $event = EventModel::query()->create([
        'name' => 'Classified Event',
        'slug' => 'classified-event',
        'status' => EventStatus::Active,

        'registration_required' => true,
        'moderation_status' => EventModerationStatus::Approved,
        'visibility' => EventVisibility::Public,
        'taxonomy' => [
            'topic' => [
                [
                    'term_key' => 'ai',
                    'term_label' => 'AI',
                    'source_type' => $source->getMorphClass(),
                    'source_id' => (string) $source->getKey(),
                ],
                'events',
            ],
            'audience' => ['builders'],
        ],
    ]);

    $event->load(['classifications.source']);

    expect($event->classifications)->toHaveCount(3)
        ->and($event->classifications->firstWhere('term_key', 'ai')?->source)->toBeInstanceOf(User::class)
        ->and($event->classifications->firstWhere('term_key', 'events')?->source)->toBeNull()
        ->and(EventClassification::query()->withGroupKey(['topic', 'audience'])->count())->toBe(3)
        ->and(EventClassification::query()->withGroupKey('topic')->withTermKey(['ai', 'events'])->count())->toBe(2);
});

it('backfills legacy json content into relational tables', function (): void {
    $event = OwnerContext::withOwner(null, function (): EventModel {
        return EventModel::withoutEvents(function (): EventModel {
            return EventModel::query()->create([
                'name' => 'Legacy Content Event',
                'slug' => 'legacy-content-event',
                'status' => EventStatus::Active,

                'registration_required' => true,
                'moderation_status' => EventModerationStatus::Approved,
                'visibility' => EventVisibility::Public,
                'taxonomy' => [
                    'topic' => ['ai', 'events'],
                ],
                'media_references' => [
                    'cover' => 'https://example.com/cover.jpg',
                ],
            ]);
        });
    });

    $beforeCounts = OwnerContext::withOwner(null, function () use ($event): array {
        return [
            EventClassification::query()->where('assignable_id', $event->id)->count(),
            EventAsset::query()->where('assignable_id', $event->id)->count(),
        ];
    });

    expect($beforeCounts)->toBe([0, 0]);

    $synced = app(BackfillEventContentAction::class)->handle();

    $backfilledEvent = OwnerContext::withOwner(null, function () use ($event): EventModel {
        return $event->refresh()->load(['classifications', 'assets']);
    });

    expect($synced)->toBe(1)
        ->and($backfilledEvent->classifications)->toHaveCount(2)
        ->and($backfilledEvent->assets)->toHaveCount(1)
        ->and($backfilledEvent->classifications->pluck('term_key')->all())->toBe(['ai', 'events'])
        ->and($backfilledEvent->assets->first()?->role_key)->toBe('cover');
});

it('separates public accessibility from discoverability and search visibility', function (): void {
    $now = Carbon::parse('2026-06-06 12:00:00', 'UTC');

    $publicEvent = EventModel::query()->create([
        'name' => 'Public Approved Event',
        'slug' => 'public-approved-event',
        'status' => EventStatus::Active,

        'registration_required' => true,
        'moderation_status' => EventModerationStatus::Approved,
        'visibility' => EventVisibility::Public,
        'published_at' => $now->copy()->subDay(),
    ]);

    $unlistedEvent = EventModel::query()->create([
        'name' => 'Unlisted Approved Event',
        'slug' => 'unlisted-approved-event',
        'status' => EventStatus::Active,

        'registration_required' => true,
        'moderation_status' => EventModerationStatus::Approved,
        'visibility' => EventVisibility::Unlisted,
        'published_at' => $now->copy()->subDay(),
    ]);

    EventModel::query()->create([
        'name' => 'Pending Public Event',
        'slug' => 'pending-public-event',
        'status' => EventStatus::Active,

        'registration_required' => true,
        'moderation_status' => EventModerationStatus::Pending,
        'visibility' => EventVisibility::Public,
    ]);

    EventModel::query()->create([
        'name' => 'Future Public Event',
        'slug' => 'future-public-event',
        'status' => EventStatus::Active,

        'registration_required' => true,
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
    $speaker = User::query()->create([
        'name' => 'Search Speaker User',
        'email' => 'search-speaker@example.com',
        'password' => 'secret',
    ]);

    $event = EventModel::query()->create([
        'name' => 'Searchable Event',
        'slug' => 'searchable-event',
        'status' => EventStatus::Active,

        'registration_required' => true,
        'moderation_status' => EventModerationStatus::Approved,
        'visibility' => EventVisibility::Public,
        'structure' => EventStructure::Program,
        'default_timezone' => 'Asia/Kuala_Lumpur',
        'taxonomy' => [
            'topic' => ['search'],
        ],
        'media_references' => [
            'cover' => 'cover.jpg',
        ],
        'search_keywords' => 'search people timezone',
    ]);

    EventPerson::query()->create([
        'event_id' => $event->id,
        'assignable_type' => $event->getMorphClass(),
        'assignable_id' => $event->id,
        'person_type' => $speaker->getMorphClass(),
        'person_id' => (string) $speaker->getKey(),
        'display_name' => 'Search Speaker',
        'role_key' => 'speaker',
        'role_label' => 'Speaker',
        'visibility' => EventVisibility::Public,
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

    $payload = $event->fresh(['people'])?->toSearchableArray();

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
            'structure' => 'program',
            'media' => [
                'cover' => 'cover.jpg',
            ],
            'people_names' => ['Search Speaker'],
        ])
        ->and($event->displayTimezone())->toBe('Asia/Kuala_Lumpur')
        ->and($occurrence->displayTimezone($viewer))->toBe('Asia/Tokyo')
        ->and($occurrence->startsAtForDisplay($viewer)->format('Y-m-d H:i'))->toBe('2026-06-05 09:45');
});

it('persists reference assignments agenda items and relational search payloads', function (): void {
    $event = EventModel::query()->create([
        'name' => 'Reference Rich Event',
        'slug' => 'reference-rich-event',
        'status' => EventStatus::Active,

        'registration_required' => true,
        'moderation_status' => EventModerationStatus::Approved,
        'visibility' => EventVisibility::Public,
        'structure' => EventStructure::Standalone,
    ]);

    $occurrence = Occurrence::query()->create([
        'event_id' => $event->id,
        'status' => 'scheduled',
        'starts_at' => Carbon::parse('2026-06-05 08:45:00', 'UTC'),
        'timezone' => 'UTC',
    ]);

    $source = User::query()->create([
        'name' => 'Reference Source',
        'email' => 'reference-source@example.com',
        'password' => 'secret',
    ]);

    EventReference::query()->create([
        'assignable_type' => $event->getMorphClass(),
        'assignable_id' => $event->getKey(),
        'reference_type' => $source->getMorphClass(),
        'reference_id' => (string) $source->getKey(),
        'reference_kind' => 'source_material',
        'display_label' => 'First deck',
        'source_label' => 'Deck',
        'url' => 'https://example.com/deck-one.pdf',
        'order_column' => 2,
    ]);

    EventReference::query()->create([
        'assignable_type' => $event->getMorphClass(),
        'assignable_id' => $event->getKey(),
        'reference_type' => $source->getMorphClass(),
        'reference_id' => (string) $source->getKey(),
        'reference_kind' => 'source_material',
        'display_label' => 'Second deck',
        'source_label' => 'Deck',
        'url' => 'https://example.com/deck-two.pdf',
        'order_column' => 1,
    ]);

    EventReference::query()->create([
        'assignable_type' => $occurrence->getMorphClass(),
        'assignable_id' => $occurrence->getKey(),
        'reference_kind' => 'itinerary_source',
        'display_label' => 'Run sheet',
        'url' => 'https://example.com/run-sheet.pdf',
        'order_column' => 1,
    ]);

    EventAgenda::query()->create([
        'occurrence_id' => $occurrence->getKey(),
        'segment_key' => 'closing',
        'segment_type' => 'talk',
        'title' => 'Closing',
        'starts_at' => Carbon::parse('2026-06-05 11:00:00', 'UTC'),
        'duration_minutes' => 15,
        'order_column' => 2,
    ]);

    EventAgenda::query()->create([
        'occurrence_id' => $occurrence->getKey(),
        'segment_key' => 'opening',
        'segment_type' => 'talk',
        'title' => 'Opening',
        'starts_at' => Carbon::parse('2026-06-05 08:45:00', 'UTC'),
        'duration_minutes' => 30,
        'order_column' => 1,
    ]);

    $event->load(['references']);
    $occurrence->load(['references', 'agendaItems']);
    $payload = $event->toSearchableArray();

    expect($event->referenceMaterials('source_material'))->toHaveCount(2)
        ->and($event->referenceMaterials('source_material')[0]['display_label'])->toBe('Second deck')
        ->and(data_get($payload, 'references.source_material.0.display_label'))->toBe('Second deck')
        ->and($occurrence->referenceMaterials('itinerary_source'))->toHaveCount(1)
        ->and($occurrence->agendaItems)->toHaveCount(2)
        ->and($occurrence->agendaItems->pluck('segment_key')->all())->toBe(['opening', 'closing'])
        ->and($occurrence->agendaItems->first()?->isTimed())->toBeTrue()
        ->and(EventReference::query()
            ->withReferenceKind('source_material')
            ->withReferenceType($source->getMorphClass())
            ->count())->toBe(2);
});

it('resolves schedule metadata through the schedule resolver contract', function (): void {
    $occurrence = (new EnsureOccurrenceAction(new class implements EventScheduleResolver
    {
        /**
         * @param  array<string, mixed>  $series
         * @param  array<string, mixed>  $event
         * @param  array<string, mixed>|null  $address
         * @param  array<string, mixed>  $occurrence
         * @return array<string, mixed>|null
         */
        public function resolve(array $series, array $event, ?array $address, array $occurrence): ?array
        {
            return [
                'timezone' => 'Asia/Tokyo',
                'starts_at' => '2026-06-05 10:00:00',
                'ends_at' => '2026-06-05 12:00:00',
                'schedule_mode' => 'host_defined',
                'schedule_reference_key' => 'jumuah-window',
                'schedule_reference_payload' => [
                    'source' => 'host-rule',
                ],
                'schedule_label' => 'Host Defined',
            ];
        }
    }))->handle(
        series: [
            'name' => 'Schedule Resolver Series',
            'slug' => 'schedule-resolver-series',
        ],
        event: [
            'name' => 'Schedule Resolver Event',
            'slug' => 'schedule-resolver-event',
            'default_timezone' => 'UTC',
        ],
        occurrence: [
            'starts_at' => '2026-06-05 08:45:00',
            'ends_at' => '2026-06-05 09:30:00',
            'timezone' => 'Asia/Kuala_Lumpur',
        ],
    );

    expect($occurrence->timezone)->toBe('Asia/Tokyo')
        ->and($occurrence->starts_at->copy()->setTimezone('UTC')->format('Y-m-d H:i:s'))->toBe('2026-06-05 01:00:00')
        ->and($occurrence->ends_at?->copy()->setTimezone('UTC')->format('Y-m-d H:i:s'))->toBe('2026-06-05 03:00:00')
        ->and($occurrence->schedule_mode)->toBe('host_defined')
        ->and($occurrence->schedule_reference_key)->toBe('jumuah-window')
        ->and($occurrence->schedule_reference_payload)->toBe([
            'source' => 'host-rule',
        ])
        ->and($occurrence->schedule_label)->toBe('Host Defined')
        ->and($occurrence->usesScheduleReference())->toBeTrue();
});

it('records unique engagements and exposes engagement type helpers', function (): void {
    $actor = User::query()->create([
        'name' => 'Engagement Actor',
        'email' => 'engagement-actor@example.com',
        'password' => 'secret',
    ]);

    $event = EventModel::query()->create([
        'name' => 'Engagement Event',
        'slug' => 'engagement-event',
        'status' => EventStatus::Active,

        'registration_required' => true,
        'moderation_status' => EventModerationStatus::Approved,
        'visibility' => EventVisibility::Public,
    ]);

    $occurrence = Occurrence::query()->create([
        'event_id' => $event->id,
        'status' => 'scheduled',
        'starts_at' => Carbon::parse('2026-06-05 08:45:00', 'UTC'),
        'timezone' => 'UTC',
    ]);

    $recordEngagement = app(RecordEventEngagementAction::class);

    $first = $recordEngagement->handle(
        $event,
        EventEngagementType::Going,
        $actor,
        1,
        ['source' => 'profile'],
    );

    $second = $recordEngagement->handle(
        $event,
        EventEngagementType::Going,
        $actor,
        4,
        ['source' => 'detail'],
    );

    $third = $recordEngagement->handle(
        $occurrence,
        EventEngagementType::Interested,
        $actor,
        2,
    );

    expect($first->getKey())->toBe($second->getKey())
        ->and($second->weight)->toBe(4)
        ->and($second->metadata)->toBe([
            'source' => 'detail',
        ])
        ->and($second->type)->toBe(EventEngagementType::Going)
        ->and($second->isGoing())->toBeTrue()
        ->and($second->typeLabel())->toBe('Going')
        ->and($second->typeColor())->toBe('success')
        ->and($third->type)->toBe(EventEngagementType::Interested)
        ->and($third->isInterested())->toBeTrue()
        ->and(EventEngagement::query()->withType(EventEngagementType::Going)->count())->toBe(1)
        ->and(EventEngagement::query()->withType([EventEngagementType::Going, EventEngagementType::Interested])->count())->toBe(2);
});

it('returns stable query and read models from the canonical discovery service', function (): void {
    $speaker = User::query()->create([
        'name' => 'Discovery Speaker',
        'email' => 'discovery-speaker@example.com',
        'password' => 'secret',
    ]);

    $event = EventModel::query()->create([
        'name' => 'Discovery Event',
        'slug' => 'discovery-event',
        'status' => EventStatus::Active,

        'registration_required' => true,
        'moderation_status' => EventModerationStatus::Approved,
        'visibility' => EventVisibility::Public,
        'structure' => EventStructure::Program,
        'default_timezone' => 'UTC',
        'published_at' => '2026-06-01 00:00:00',
    ]);

    EventPerson::query()->create([
        'event_id' => $event->id,
        'person_type' => $speaker->getMorphClass(),
        'person_id' => (string) $speaker->getKey(),
        'display_name' => 'Discovery Speaker',
        'role_key' => 'speaker',
        'role_label' => 'Speaker',
        'visibility' => EventVisibility::Public,
        'order_column' => 1,
    ]);

    $occurrence = Occurrence::query()->create([
        'event_id' => $event->id,
        'status' => 'scheduled',
        'starts_at' => Carbon::parse('2026-06-05 08:45:00', 'UTC'),
        'timezone' => 'UTC',
    ]);

    EventAgenda::query()->create([
        'occurrence_id' => $occurrence->id,
        'segment_key' => 'opening',
        'segment_type' => 'talk',
        'title' => 'Opening',
        'starts_at' => Carbon::parse('2026-06-05 08:45:00', 'UTC'),
        'duration_minutes' => 30,
        'order_column' => 1,
    ]);

    $registration = Registration::query()->create([
        'occurrence_id' => $occurrence->id,
        'status' => 'confirmed',
        'first_name' => 'Discovery',
        'last_name' => 'Guest',
        'email' => 'discovery@example.com',
    ]);

    $notice = EventChange::query()->create([
        'event_id' => $event->id,
        'change_key' => 'title_changed',
        'severity' => 'info',
        'state' => 'draft',
        'changed_sections' => [
            'title' => true,
        ],
    ]);

    $service = app(EventQueryService::class);
    $search = $service->search(new EventSearchCriteria(
        term: 'Discovery',
        page: 1,
        perPage: 10,
    ));
    $card = $service->card($event->fresh(['people', 'references']) ?? $event);
    $detail = $service->detail($event->fresh(['people', 'references', 'occurrences.references', 'occurrences.agendaItems']) ?? $event);
    $occurrenceReadModel = $service->occurrence($occurrence->fresh(['references', 'agendaItems']) ?? $occurrence);
    $reviewSchema = $service->reviewSchema($event->fresh() ?? $event);
    $registrationStatus = $service->registrationStatus($registration->fresh(['occurrence']) ?? $registration);
    $noticePayload = $service->changeNoticePayload($notice->fresh() ?? $notice);

    expect($search)->toBeInstanceOf(EventSearchResultData::class)
        ->and($search->items)->toHaveCount(1)
        ->and($search->items[0])->toBeInstanceOf(EventSearchCardData::class)
        ->and($card)->toBeInstanceOf(EventSearchCardData::class)
        ->and($detail)->toBeInstanceOf(EventDetailData::class)
        ->and($detail->occurrences[0] ?? null)->toBeInstanceOf(OccurrenceDetailData::class)
        ->and($detail->occurrences[0]->agendaItems[0] ?? null)->toBeInstanceOf(EventAgendaItemData::class)
        ->and($occurrenceReadModel)->toBeInstanceOf(OccurrenceDetailData::class)
        ->and($reviewSchema)->toBeInstanceOf(EventReviewSchemaData::class)
        ->and($registrationStatus)->toBeInstanceOf(RegistrationStatusData::class)
        ->and($registrationStatus->status)->toBe('confirmed')
        ->and($registrationStatus->canCheckIn)->toBeTrue()
        ->and($noticePayload)->toBeInstanceOf(EventChangeNoticePayloadData::class)
        ->and($noticePayload->changeKey)->toBe('title_changed');
});

it('records moderation submissions reviews and reasoned transitions', function (): void {
    $reviewer = User::query()->create([
        'name' => 'Reviewer',
        'email' => 'reviewer@example.com',
        'password' => 'secret',
    ]);

    $event = EventModel::query()->create([
        'name' => 'Moderated Event',
        'slug' => 'moderated-event',
        'status' => EventStatus::Active,

        'registration_required' => true,
        'moderation_status' => EventModerationStatus::Pending,
        'visibility' => EventVisibility::Public,
    ]);

    $submission = app(EventModerationWorkflow::class)->submit($event, $reviewer, [
        'note' => 'Needs review',
    ]);

    expect($submission->submittedBy)->toBeInstanceOf(User::class)
        ->and($submission->notes)->toBe('Needs review')
        ->and(data_get($submission->metadata, 'action'))->toBe('submit')
        ->and(data_get($submission->metadata, 'note'))->toBe('Needs review')
        ->and(data_get($submission->metadata, 'to_status'))->toBe('pending')
        ->and($event->fresh()?->moderation_status)->toBe(EventModerationStatus::Pending);

    expect(fn (): EventReview => app(EventModerationWorkflow::class)->approve($event->fresh() ?? $event, $reviewer, [
        'reason' => 'not-configured',
    ]))->toThrow(InvalidArgumentException::class);

    expect(fn (): EventReview => app(EventModerationWorkflow::class)->requestChanges($event->fresh() ?? $event, $reviewer, [
        'reason' => 'needs_more_information',
    ]))->toThrow(InvalidArgumentException::class);

    $review = app(EventModerationWorkflow::class)->requestChanges($event->fresh() ?? $event, $reviewer, [
        'note' => 'Add speaker biography and updated cover.',
        'reason' => 'needs_more_information',
    ]);

    expect($review->reviewedBy)->toBeInstanceOf(User::class)
        ->and($review->reason_key)->toBe('needs_more_information')
        ->and($review->notes)->toBe('Add speaker biography and updated cover.')
        ->and(data_get($review->metadata, 'action'))->toBe('request_changes')
        ->and(data_get($review->metadata, 'from_status'))->toBe('pending')
        ->and(data_get($review->metadata, 'to_status'))->toBe('changes_requested')
        ->and(data_get($review->before_snapshot, 'moderation_status'))->toBe('pending')
        ->and(data_get($review->after_snapshot, 'moderation_status'))->toBe('changes_requested')
        ->and($event->fresh()?->moderation_status)->toBe(EventModerationStatus::ChangesRequested);

    $approval = app(EventModerationWorkflow::class)->approve($event->fresh() ?? $event, $reviewer, [
        'reason' => 'approved_for_publish',
    ]);

    expect($approval->decision)->toBe(EventModerationStatus::Approved)
        ->and($approval->reason_key)->toBe('approved_for_publish')
        ->and(data_get($approval->metadata, 'action'))->toBe('approve')
        ->and(data_get($approval->metadata, 'reason_key'))->toBe('approved_for_publish')
        ->and($event->fresh()?->moderation_status)->toBe(EventModerationStatus::Approved);
});

it('releases change notices through lifecycle events and blocks replacement cycles', function (): void {
    EventFacade::fake([
        EventChangeNoticePublished::class,
        EventChangeNoticeRetracted::class,
    ]);

    $firstEvent = EventModel::query()->create([
        'name' => 'First Notice Event',
        'slug' => 'first-notice-event',
        'status' => EventStatus::Active,

        'registration_required' => true,
        'moderation_status' => EventModerationStatus::Approved,
        'visibility' => EventVisibility::Public,
    ]);

    $secondEvent = EventModel::query()->create([
        'name' => 'Second Notice Event',
        'slug' => 'second-notice-event',
        'status' => EventStatus::Active,

        'registration_required' => true,
        'moderation_status' => EventModerationStatus::Approved,
        'visibility' => EventVisibility::Public,
    ]);

    $notice = EventChange::query()->create([
        'event_id' => $firstEvent->id,
        'replacement_event_id' => $secondEvent->id,
        'change_key' => 'speaker_changed',
        'severity' => 'high',
        'state' => 'draft',
        'changed_sections' => [
            'people' => true,
        ],
    ]);

    $notice->publish();
    $notice->save();

    EventFacade::assertDispatched(EventChangeNoticePublished::class, static function (EventChangeNoticePublished $event): bool {
        return $event->notice->isPublished();
    });

    $notice->retract();
    $notice->save();

    EventFacade::assertDispatched(EventChangeNoticeRetracted::class, static function (EventChangeNoticeRetracted $event): bool {
        return $event->notice->status === 'retracted';
    });

    EventChange::query()->create([
        'event_id' => $firstEvent->id,
        'replacement_event_id' => $secondEvent->id,
        'change_key' => 'title_changed',
        'severity' => 'info',
        'state' => 'draft',
    ]);

    expect(fn (): EventChange => EventChange::query()->create([
        'event_id' => $secondEvent->id,
        'replacement_event_id' => $firstEvent->id,
        'change_key' => 'topic_changed',
        'severity' => 'info',
        'state' => 'draft',
    ]))->toThrow(InvalidArgumentException::class);
});

it('creates first-class change notices for speaker title topic schedule and bundles', function (): void {
    $event = EventModel::query()->create([
        'name' => 'Notice Workflow Event',
        'slug' => 'notice-workflow-event',
        'status' => EventStatus::Active,

        'registration_required' => true,
        'moderation_status' => EventModerationStatus::Approved,
        'visibility' => EventVisibility::Public,
    ]);

    $replacementEvent = EventModel::query()->create([
        'name' => 'Replacement Workflow Event',
        'slug' => 'replacement-workflow-event',
        'status' => EventStatus::Active,

        'registration_required' => true,
        'moderation_status' => EventModerationStatus::Approved,
        'visibility' => EventVisibility::Public,
    ]);

    $workflow = app(EventChangeNoticeWorkflow::class);

    $speakerNotice = $workflow->speakerChanged(
        $event,
        beforeSnapshot: ['people' => ['speaker' => 'Old Speaker']],
        afterSnapshot: ['people' => ['speaker' => 'New Speaker']],
    );

    $titleNotice = $workflow->titleChanged(
        $event,
        beforeSnapshot: ['title' => 'Old Title'],
        afterSnapshot: ['title' => 'New Title'],
    );

    $topicNotice = $workflow->topicChanged(
        $event,
        beforeSnapshot: ['topic' => 'old-topic'],
        afterSnapshot: ['topic' => 'new-topic'],
    );

    $scheduleNotice = $workflow->scheduleChanged(
        $event,
        beforeSnapshot: ['starts_at' => '2026-06-05 08:00:00'],
        afterSnapshot: ['starts_at' => '2026-06-05 09:00:00'],
    );

    $bundleNotice = $workflow->bundle(
        $event,
        changedSections: [
            'people' => ['speaker' => true],
            'title' => true,
            'topic' => true,
            'classifications' => ['topic' => true],
            'references' => ['source_material' => true],
            'assets' => ['cover' => true],
        ],
        beforeSnapshot: [
            'name' => 'Old Title',
            'taxonomy' => ['topic' => ['old-topic']],
        ],
        afterSnapshot: [
            'name' => 'New Title',
            'taxonomy' => ['topic' => ['new-topic']],
        ],
        metadata: [
            'source' => 'editorial-review',
        ],
        changeKey: 'content_changed',
        severity: 'high',
        replacementEvent: $replacementEvent,
    );

    $cancelledNotice = $workflow->cancelled(
        $event,
        beforeSnapshot: ['status' => 'active'],
        afterSnapshot: ['status' => 'cancelled'],
    );

    $postponedNotice = $workflow->postponed(
        $event,
        beforeSnapshot: ['starts_at' => '2026-06-05 08:00:00'],
        afterSnapshot: ['starts_at' => '2026-06-05 10:00:00'],
    );

    $replacementNotice = $workflow->replacementLinked(
        $event,
        beforeSnapshot: ['replacement_event_id' => null],
        afterSnapshot: ['replacement_event_id' => $replacementEvent->id],
        replacementEvent: $replacementEvent,
    );

    expect($speakerNotice->change_key)->toBe('speaker_changed')
        ->and($speakerNotice->severity)->toBe('high')
        ->and($speakerNotice->isSpeakerChange())->toBeTrue()
        ->and($speakerNotice->hasCompoundChanges())->toBeFalse()
        ->and($titleNotice->isTitleChange())->toBeTrue()
        ->and($topicNotice->isTopicChange())->toBeTrue()
        ->and($scheduleNotice->isScheduleChange())->toBeTrue()
        ->and($bundleNotice->change_key)->toBe('content_changed')
        ->and($bundleNotice->severity)->toBe('high')
        ->and($bundleNotice->hasCompoundChanges())->toBeTrue()
        ->and($bundleNotice->replacement_event_id)->toBe($replacementEvent->id)
        ->and($cancelledNotice->isCancellation())->toBeTrue()
        ->and($cancelledNotice->severity)->toBe('urgent')
        ->and($postponedNotice->isPostponement())->toBeTrue()
        ->and($postponedNotice->severity)->toBe('high')
        ->and($replacementNotice->isReplacementLink())->toBeTrue();
});

it('dispatches change notice notifications through the adapter seam after publication', function (): void {
    $actor = User::query()->create([
        'name' => 'Audience Actor',
        'email' => 'audience-actor@example.com',
        'password' => 'secret',
    ]);

    $event = EventModel::query()->create([
        'name' => 'Audience Event',
        'slug' => 'audience-event',
        'status' => EventStatus::Active,

        'registration_required' => true,
        'moderation_status' => EventModerationStatus::Approved,
        'visibility' => EventVisibility::Public,
    ]);

    $occurrence = Occurrence::query()->create([
        'event_id' => $event->id,
        'status' => 'scheduled',
        'starts_at' => Carbon::parse('2026-06-05 08:45:00', 'UTC'),
        'timezone' => 'UTC',
    ]);

    $confirmed = Registration::query()->create([
        'occurrence_id' => $occurrence->id,
        'status' => RegistrationStatus::Confirmed,
        'first_name' => 'Confirmed',
        'last_name' => 'Guest',
        'email' => 'confirmed@example.com',
    ]);

    $waitlisted = Registration::query()->create([
        'occurrence_id' => $occurrence->id,
        'status' => RegistrationStatus::Waitlisted,
        'first_name' => 'Waitlisted',
        'last_name' => 'Guest',
        'email' => 'waitlisted@example.com',
    ]);

    $paid = Registration::query()->create([
        'occurrence_id' => $occurrence->id,
        'order_id' => (string) Str::uuid(),
        'order_item_id' => (string) Str::uuid(),
        'status' => RegistrationStatus::Confirmed,
        'first_name' => 'Paid',
        'last_name' => 'Guest',
        'email' => 'paid@example.com',
    ]);

    $recordEngagement = app(RecordEventEngagementAction::class);
    $saved = $recordEngagement->handle($event, EventEngagementType::Saved, $actor);
    $going = $recordEngagement->handle($event, EventEngagementType::Going, $actor);
    $interested = $recordEngagement->handle($event, EventEngagementType::Interested, $actor);

    $dispatcher = new class implements EventChangeNoticeNotificationDispatcher
    {
        /**
         * @var array<int, array{notice: EventChangeNoticePayloadData, audiences: EventChangeNoticeAudienceData}>
         */
        public array $calls = [];

        public function dispatch(EventChange $notice, EventChangeNoticeAudienceData $audiences): void
        {
            $this->calls[] = [
                'notice' => EventChangeNoticePayloadData::fromNotice($notice),
                'audiences' => $audiences,
            ];
        }
    };

    app()->instance(EventChangeNoticeNotificationDispatcher::class, $dispatcher);

    $workflow = app(EventChangeNoticeWorkflow::class);
    $notice = $workflow->speakerChanged(
        $event,
        beforeSnapshot: ['people' => ['speaker' => 'Old Speaker']],
        afterSnapshot: ['people' => ['speaker' => 'New Speaker']],
    );

    $workflow->publish($notice);

    expect($dispatcher->calls)->toHaveCount(1)
        ->and($dispatcher->calls[0]['notice']->changeKey)->toBe('speaker_changed')
        ->and($dispatcher->calls[0]['audiences']->registered)->toContain($confirmed->id)
        ->and($dispatcher->calls[0]['audiences']->waitlisted)->toContain($waitlisted->id)
        ->and($dispatcher->calls[0]['audiences']->paid)->toContain($paid->id)
        ->and($dispatcher->calls[0]['audiences']->saved)->toContain($saved->id)
        ->and($dispatcher->calls[0]['audiences']->going)->toContain($going->id)
        ->and($dispatcher->calls[0]['audiences']->interested)->toContain($interested->id);
});

it('exposes the requested moderation state', function (): void {
    expect(EventModerationStatus::ChangesRequested->label())->toBe('Changes Requested')
        ->and(EventModerationStatus::ChangesRequested->color())->toBe('warning')
        ->and(EventModerationStatus::ChangesRequested->isPubliclyVisible())->toBeFalse();
});
