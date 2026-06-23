<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventAttribute;
use AIArmada\Events\Models\EventAudience;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSearchDocument;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventTimeExpression;
use AIArmada\Events\Observers\EventAttributeObserver;
use AIArmada\Events\Observers\EventAudienceObserver;
use AIArmada\Events\Observers\EventObserver;
use AIArmada\Events\Observers\EventOccurrenceObserver;
use AIArmada\Events\Observers\EventSessionObserver;
use AIArmada\Events\Observers\EventTimeExpressionObserver;
use AIArmada\Events\Services\EventMetadataSyncService;
use AIArmada\Events\Services\EventSearchDocumentBuilder;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
    config()->set('events.sync.build_search_documents', true);
});

function createIndexedSearchEvent(): Event
{
    return OwnerContext::withOwner(null, fn () => Event::factory()->create([
        'title' => 'Indexed Event',
        'summary' => 'Indexed Summary',
        'description' => 'Indexed Description',
    ]));
}

function createIndexedOccurrence(?Event $event = null): EventOccurrence
{
    $event ??= createIndexedSearchEvent();

    return OwnerContext::withOwner(null, fn () => EventOccurrence::query()->create([
        'event_id' => $event->id,
        'title' => 'Indexed Occurrence',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHours(2),
        'timezone' => 'Asia/Kuala_Lumpur',
        'status' => 'scheduled',
        'visibility' => 'public',
        'delivery_mode' => 'physical',
    ]));
}

function createIndexedSession(?EventOccurrence $occurrence = null): EventSession
{
    $occurrence ??= createIndexedOccurrence();

    return OwnerContext::withOwner(null, fn () => EventSession::query()->create([
        'event_id' => $occurrence->event_id,
        'event_occurrence_id' => $occurrence->id,
        'title' => 'Indexed Session',
        'summary' => 'Indexed Session Summary',
        'description' => 'Indexed Session Description',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
        'timezone' => 'Asia/Kuala_Lumpur',
        'status' => 'scheduled',
        'visibility' => 'public',
        'delivery_mode' => 'physical',
        'sort_order' => 1,
    ]));
}

it('indexes and removes search documents when the event changes', function (): void {
    $event = createIndexedSearchEvent();
    $observer = new EventObserver(app(EventSearchDocumentBuilder::class));

    $observer->saved($event);

    expect(EventSearchDocument::where('event_id', $event->id)->exists())->toBeTrue();

    $observer->deleted($event);

    expect(EventSearchDocument::where('event_id', $event->id)->exists())->toBeFalse();
});

it('indexes and removes search documents when an occurrence changes', function (): void {
    $occurrence = createIndexedOccurrence();
    $observer = new EventOccurrenceObserver(app(EventSearchDocumentBuilder::class));

    $observer->saved($occurrence);

    expect(EventSearchDocument::where('event_occurrence_id', $occurrence->id)->exists())->toBeTrue();

    $observer->deleted($occurrence);

    expect(EventSearchDocument::where('event_occurrence_id', $occurrence->id)->exists())->toBeFalse();
});

it('indexes and removes search documents when a session changes', function (): void {
    $session = createIndexedSession();
    $observer = new EventSessionObserver(app(EventSearchDocumentBuilder::class));

    $observer->saved($session);

    expect(EventSearchDocument::where('event_session_id', $session->id)->exists())->toBeTrue();

    $observer->deleted($session);

    expect(EventSearchDocument::where('event_session_id', $session->id)->exists())->toBeFalse();
});

it('reindexes search documents when an attribute is saved', function (): void {
    $event = createIndexedSearchEvent();
    $observer = new EventAttributeObserver(
        app(EventMetadataSyncService::class),
        app(EventSearchDocumentBuilder::class),
    );

    $attribute = EventAttribute::factory()->create([
        'event_id' => $event->id,
        'attribute_key' => 'gender_restriction',
        'attribute_value' => 'male_only',
    ]);

    $observer->saved($attribute);

    $event->refresh();
    $doc = EventSearchDocument::where('event_id', $event->id)->first();

    expect($event->metadata)->toBe([
        'gender_restriction' => 'male_only',
    ]);
    expect($doc)->not->toBeNull();
    expect($doc->facets)->toHaveKey('gender_restriction', 'male_only');
});

it('reindexes the event and occurrence when an occurrence-scoped attribute is saved', function (): void {
    $event = createIndexedSearchEvent();
    $occurrence = createIndexedOccurrence($event);
    $observer = new EventAttributeObserver(
        app(EventMetadataSyncService::class),
        app(EventSearchDocumentBuilder::class),
    );

    $attribute = EventAttribute::query()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'attribute_key' => 'gender_restriction',
        'attribute_value' => 'female_only',
    ]);

    $observer->saved($attribute);

    $event->refresh();
    $occurrence->refresh();
    $doc = EventSearchDocument::where('event_occurrence_id', $occurrence->id)->first();

    expect($event->metadata)->toBe([
        'gender_restriction' => 'female_only',
    ]);
    expect($occurrence->metadata)->toBe([
        'gender_restriction' => 'female_only',
    ]);
    expect($doc)->not->toBeNull();
    expect($doc->facets)->toHaveKey('gender_restriction', 'female_only');
});

it('reindexes search documents when a time expression is saved', function (): void {
    $event = createIndexedSearchEvent();
    $observer = new EventTimeExpressionObserver(
        app(EventMetadataSyncService::class),
        app(EventSearchDocumentBuilder::class),
    );

    $expression = EventTimeExpression::factory()->create([
        'event_id' => $event->id,
        'time_mode' => 'after_anchor',
        'anchor_type' => 'prayer',
        'anchor_code' => 'maghrib',
        'relation' => 'after',
        'offset_minutes' => 30,
        'display_label' => 'After Maghrib',
    ]);

    $observer->saved($expression);

    $event->refresh();
    $doc = EventSearchDocument::where('event_id', $event->id)->first();

    expect($event->metadata)->toHaveKey('_time_expressions');
    expect($doc)->not->toBeNull();
    expect($doc->facets)->toHaveKey('_time_expressions');
});

it('reindexes the event and session when a session-scoped time expression is saved', function (): void {
    $event = createIndexedSearchEvent();
    $occurrence = createIndexedOccurrence($event);
    $session = createIndexedSession($occurrence);
    $observer = new EventTimeExpressionObserver(
        app(EventMetadataSyncService::class),
        app(EventSearchDocumentBuilder::class),
    );

    $expression = EventTimeExpression::query()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'event_session_id' => $session->id,
        'time_mode' => 'after_anchor',
        'anchor_type' => 'prayer',
        'anchor_code' => 'maghrib',
        'relation' => 'after',
        'offset_minutes' => 30,
        'display_label' => 'After Maghrib',
    ]);

    $observer->saved($expression);

    $event->refresh();
    $session->refresh();
    $doc = EventSearchDocument::where('event_session_id', $session->id)->first();

    expect($event->metadata)->toHaveKey('_time_expressions');
    expect($session->metadata)->toHaveKey('_time_expressions');
    expect($doc)->not->toBeNull();
    expect($doc->facets)->toHaveKey('_time_expressions');
});

it('reindexes search documents when an audience is saved', function (): void {
    config()->set('events.sync.audiences_to_metadata', false);
    config()->set('events.sync.audiences_to_facets', true);
    config()->set('events.attribute_sync.audience_types', ['age_group']);

    $event = createIndexedSearchEvent();
    $observer = new EventAudienceObserver(
        app(EventMetadataSyncService::class),
        app(EventSearchDocumentBuilder::class),
    );

    $audience = EventAudience::factory()->create([
        'event_id' => $event->id,
        'audience_type' => 'age_group',
        'value' => 'adults',
    ]);

    $observer->saved($audience);

    $event->refresh();
    $doc = EventSearchDocument::where('event_id', $event->id)->first();

    expect($event->metadata)->toBeNull();
    expect($doc)->not->toBeNull();
    expect($doc->facets)->toHaveKey('_audiences');
    expect($doc->facets['_audiences'])->toHaveKey('age_group');
    expect($doc->facets['_audiences']['age_group'])->toBe(['adults']);
});

it('reindexes the event and session when a session-scoped audience is saved', function (): void {
    config()->set('events.sync.audiences_to_metadata', true);
    config()->set('events.sync.audiences_to_facets', true);
    config()->set('events.attribute_sync.audience_types', ['age_group']);

    $event = createIndexedSearchEvent();
    $occurrence = createIndexedOccurrence($event);
    $session = createIndexedSession($occurrence);
    $observer = new EventAudienceObserver(
        app(EventMetadataSyncService::class),
        app(EventSearchDocumentBuilder::class),
    );

    $audience = EventAudience::query()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'event_session_id' => $session->id,
        'audience_type' => 'age_group',
        'value' => 'adults',
    ]);

    $observer->saved($audience);

    $event->refresh();
    $session->refresh();
    $doc = EventSearchDocument::where('event_session_id', $session->id)->first();

    expect($event->metadata)->toHaveKey('_audiences');
    expect($session->metadata)->toHaveKey('_audiences');
    expect($doc)->not->toBeNull();
    expect($doc->facets)->toHaveKey('_audiences');
    expect($doc->facets['_audiences'])->toHaveKey('age_group');
    expect($doc->facets['_audiences']['age_group'])->toBe(['adults']);
});
