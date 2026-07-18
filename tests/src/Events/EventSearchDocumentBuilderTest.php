<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Jobs\BuildEventSearchDocumentJob;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventAttribute;
use AIArmada\Events\Models\EventAudience;
use AIArmada\Events\Models\EventClassification;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSearchDocument;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Services\EventSearchDocumentBuilder;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
    config()->set('events.sync.build_search_documents', true);
});

function createSearchEvent(): Event
{
    return OwnerContext::withOwner(null, fn () => Event::factory()->create([
        'title' => 'Ramadan Lecture',
        'summary' => 'A lecture on Ramadan',
        'description' => 'Full description of the Ramadan lecture event',
    ]));
}

function createSearchOccurrence(?Event $event = null, array $attributes = []): EventOccurrence
{
    $event ??= createSearchEvent();

    return OwnerContext::withOwner(null, fn () => EventOccurrence::query()->create(array_merge([
        'event_id' => $event->id,
        'title' => 'Ramadan Lecture Occurrence',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHours(2),
        'timezone' => 'Asia/Kuala_Lumpur',
        'status' => 'scheduled',
        'visibility' => 'public',
        'delivery_mode' => 'physical',
    ], $attributes)));
}

function createSearchSession(?EventOccurrence $occurrence = null, array $attributes = []): EventSession
{
    $occurrence ??= createSearchOccurrence();

    return OwnerContext::withOwner(null, fn () => EventSession::query()->create(array_merge([
        'event_id' => $occurrence->event_id,
        'event_occurrence_id' => $occurrence->id,
        'title' => 'Ramadan Lecture Session',
        'summary' => 'A focused session',
        'description' => 'Detailed session description',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
        'timezone' => 'Asia/Kuala_Lumpur',
        'status' => 'scheduled',
        'visibility' => 'public',
        'delivery_mode' => 'physical',
        'sort_order' => 1,
    ], $attributes)));
}

describe('buildPayload', function (): void {
    it('builds payload from event attributes', function (): void {
        $event = createSearchEvent();

        $builder = app(EventSearchDocumentBuilder::class);
        $payload = $builder->buildPayload($event);

        expect($payload)->toHaveKey('title', 'Ramadan Lecture');
        expect($payload)->toHaveKey('summary', 'A lecture on Ramadan');
        expect($payload)->toHaveKey('body', 'Full description of the Ramadan lecture event');
        expect($payload)->toHaveKey('document_type', 'event');
        expect($payload)->toHaveKey('status', 'active');
        expect($payload)->toHaveKey('searchable_type', $event->getMorphClass());
        expect($payload)->toHaveKey('searchable_id', $event->id);
        expect($payload)->toHaveKey('event_id', $event->id);
    });

    it('includes facets from event metadata', function (): void {
        $event = createSearchEvent();
        EventAttribute::factory()->create([
            'event_id' => $event->id,
            'attribute_key' => 'gender_restriction',
            'attribute_value' => 'male_only',
        ]);

        $builder = app(EventSearchDocumentBuilder::class);
        $payload = $builder->buildPayload($event);

        expect($payload['facets'])->toBe(['gender_restriction' => 'male_only']);
    });

    it('includes audience facets directly from the audience records', function (): void {
        config()->set('events.sync.audiences_to_facets', true);
        config()->set('events.attribute_sync.audience_types', ['age_group']);

        $event = createSearchEvent();

        EventAudience::factory()->create([
            'event_id' => $event->id,
            'audience_type' => 'age_group',
            'value' => 'adults',
        ]);

        EventAudience::factory()->create([
            'event_id' => $event->id,
            'audience_type' => 'gender',
            'value' => 'male',
        ]);

        $builder = app(EventSearchDocumentBuilder::class);
        $payload = $builder->buildPayload($event);

        expect($payload['facets']['_audiences'])->toBe([
            'age_group' => ['adults'],
        ]);
    });

    it('includes classification facets from the configured taxonomy codes', function (): void {
        config()->set('events.sync.classifications_to_facets', true);
        config()->set('events.attribute_sync.taxonomy_codes', ['topic']);

        $event = createSearchEvent();

        EventClassification::factory()->create([
            'event_id' => $event->id,
            'taxonomy_code' => 'topic',
            'term_code' => 'ai',
        ]);

        EventClassification::factory()->create([
            'event_id' => $event->id,
            'taxonomy_code' => 'format',
            'term_code' => 'workshop',
        ]);

        $builder = app(EventSearchDocumentBuilder::class);
        $payload = $builder->buildPayload($event);

        expect($payload['facets']['_classifications'])->toBe([
            'topic' => ['ai'],
        ]);
    });
});

describe('buildPayloadForOccurrence', function (): void {
    it('builds payload from occurrence attributes and scoped relations', function (): void {
        config()->set('events.sync.audiences_to_facets', true);
        config()->set('events.sync.classifications_to_facets', true);
        config()->set('events.attribute_sync.audience_types', ['age_group']);
        config()->set('events.attribute_sync.taxonomy_codes', ['topic']);

        $event = createSearchEvent();
        $occurrence = createSearchOccurrence($event, [
        ]);

        EventAudience::query()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'audience_type' => 'age_group',
            'value' => 'adults',
        ]);

        EventClassification::query()->create([
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_taxonomy_id' => fake()->uuid(),
            'event_term_id' => fake()->uuid(),
            'taxonomy_code' => 'topic',
            'term_code' => 'ai',
        ]);

        $occurrence->refresh();

        $builder = app(EventSearchDocumentBuilder::class);
        $payload = $builder->buildPayloadForOccurrence($occurrence);

        expect($payload)->toHaveKey('title', 'Ramadan Lecture Occurrence');
        expect($payload)->toHaveKey('summary', null);
        expect($payload)->toHaveKey('body', null);
        expect($payload)->toHaveKey('document_type', 'occurrence');
        expect($payload)->toHaveKey('searchable_type', $occurrence->getMorphClass());
        expect($payload)->toHaveKey('searchable_id', $occurrence->id);
        expect($payload)->toHaveKey('event_id', $occurrence->event_id);
        expect($payload)->toHaveKey('event_occurrence_id', $occurrence->id);
        expect($payload)->toHaveKey('event_session_id', null);
        expect($payload['facets'])->toMatchArray([
            '_audiences' => [
                'age_group' => ['adults'],
            ],
            '_classifications' => [
                'topic' => ['ai'],
            ],
        ]);
    });
});

describe('buildPayloadForSession', function (): void {
    it('builds payload from session attributes and scoped relations', function (): void {
        config()->set('events.sync.audiences_to_facets', true);
        config()->set('events.sync.classifications_to_facets', true);
        config()->set('events.attribute_sync.audience_types', ['age_group']);
        config()->set('events.attribute_sync.taxonomy_codes', ['topic']);

        $occurrence = createSearchOccurrence();
        $session = createSearchSession($occurrence);

        EventAttribute::factory()->create([
            'event_id' => $session->event_id,
            'event_occurrence_id' => $session->event_occurrence_id,
            'event_session_id' => $session->id,
            'attribute_key' => 'track',
            'attribute_value' => 'main-stage',
        ]);

        EventAudience::query()->create([
            'event_id' => $session->event_id,
            'event_occurrence_id' => $session->event_occurrence_id,
            'event_session_id' => $session->id,
            'audience_type' => 'age_group',
            'value' => 'adults',
        ]);

        EventClassification::query()->create([
            'event_id' => $session->event_id,
            'event_occurrence_id' => $session->event_occurrence_id,
            'event_session_id' => $session->id,
            'event_taxonomy_id' => fake()->uuid(),
            'event_term_id' => fake()->uuid(),
            'taxonomy_code' => 'topic',
            'term_code' => 'ai',
        ]);

        $session->refresh();

        $builder = app(EventSearchDocumentBuilder::class);
        $payload = $builder->buildPayloadForSession($session);

        expect($payload)->toHaveKey('title', 'Ramadan Lecture Session');
        expect($payload)->toHaveKey('summary', 'A focused session');
        expect($payload)->toHaveKey('body', 'Detailed session description');
        expect($payload)->toHaveKey('document_type', 'session');
        expect($payload)->toHaveKey('searchable_type', $session->getMorphClass());
        expect($payload)->toHaveKey('searchable_id', $session->id);
        expect($payload)->toHaveKey('event_id', $session->event_id);
        expect($payload)->toHaveKey('event_occurrence_id', $session->event_occurrence_id);
        expect($payload)->toHaveKey('event_session_id', $session->id);
        expect($payload['facets'])->toMatchArray([
            'track' => 'main-stage',
            '_audiences' => [
                'age_group' => ['adults'],
            ],
            '_classifications' => [
                'topic' => ['ai'],
            ],
        ]);
    });
});

describe('buildForEvent', function (): void {
    it('creates a search document for the event', function (): void {
        $event = createSearchEvent();

        $builder = app(EventSearchDocumentBuilder::class);
        $doc = $builder->buildForEvent($event);

        expect($doc)->toBeInstanceOf(EventSearchDocument::class);
        expect($doc->event_id)->toBe($event->id);
        expect($doc->title)->toBe('Ramadan Lecture');
        expect($doc->document_type)->toBe('event');
        expect($doc->status)->toBe('active');
    });

    it('upserts existing search document', function (): void {
        $event = createSearchEvent();

        $builder = app(EventSearchDocumentBuilder::class);
        $first = $builder->buildForEvent($event);

        $event->title = 'Updated Title';
        $event->save();

        $second = $builder->buildForEvent($event);

        expect($second->id)->toBe($first->id);
        expect($second->title)->toBe('Updated Title');
    });
});

describe('buildForOccurrence', function (): void {
    it('creates a search document for the occurrence', function (): void {
        $occurrence = createSearchOccurrence();

        $builder = app(EventSearchDocumentBuilder::class);
        $doc = $builder->buildForOccurrence($occurrence);

        expect($doc)->toBeInstanceOf(EventSearchDocument::class);
        expect($doc->event_id)->toBe($occurrence->event_id);
        expect($doc->event_occurrence_id)->toBe($occurrence->id);
        expect($doc->event_session_id)->toBeNull();
        expect($doc->document_type)->toBe('occurrence');
        expect($doc->status)->toBe('active');
    });

    it('upserts existing occurrence search document', function (): void {
        $occurrence = createSearchOccurrence();

        $builder = app(EventSearchDocumentBuilder::class);
        $first = $builder->buildForOccurrence($occurrence);

        $occurrence->title = 'Updated Occurrence Title';
        $occurrence->save();

        $second = $builder->buildForOccurrence($occurrence);

        expect($second->id)->toBe($first->id);
        expect($second->title)->toBe('Updated Occurrence Title');
    });
});

describe('buildForSession', function (): void {
    it('creates a search document for the session', function (): void {
        $session = createSearchSession();

        $builder = app(EventSearchDocumentBuilder::class);
        $doc = $builder->buildForSession($session);

        expect($doc)->toBeInstanceOf(EventSearchDocument::class);
        expect($doc->event_id)->toBe($session->event_id);
        expect($doc->event_occurrence_id)->toBe($session->event_occurrence_id);
        expect($doc->event_session_id)->toBe($session->id);
        expect($doc->document_type)->toBe('session');
        expect($doc->status)->toBe('active');
    });

    it('upserts existing session search document', function (): void {
        $session = createSearchSession();

        $builder = app(EventSearchDocumentBuilder::class);
        $first = $builder->buildForSession($session);

        $session->title = 'Updated Session Title';
        $session->save();

        $second = $builder->buildForSession($session);

        expect($second->id)->toBe($first->id);
        expect($second->title)->toBe('Updated Session Title');
    });
});

describe('index', function (): void {
    it('builds document when build_search_documents is enabled', function (): void {
        $event = createSearchEvent();

        $builder = app(EventSearchDocumentBuilder::class);
        $builder->index($event);

        $doc = EventSearchDocument::where('event_id', $event->id)->first();

        expect($doc)->not->toBeNull();
        expect($doc->title)->toBe('Ramadan Lecture');
    });

    it('does nothing when build_search_documents is disabled', function (): void {
        config()->set('events.sync.build_search_documents', false);

        $event = createSearchEvent();

        $builder = app(EventSearchDocumentBuilder::class);
        $builder->index($event);

        $doc = EventSearchDocument::where('event_id', $event->id)->first();

        expect($doc)->toBeNull();
    });

    it('builds occurrence documents when build_search_documents is enabled', function (): void {
        $occurrence = createSearchOccurrence();

        $builder = app(EventSearchDocumentBuilder::class);
        $builder->index($occurrence);

        $doc = EventSearchDocument::where('event_occurrence_id', $occurrence->id)->first();

        expect($doc)->not->toBeNull();
        expect($doc->document_type)->toBe('occurrence');
    });

    it('builds session documents when build_search_documents is enabled', function (): void {
        $session = createSearchSession();

        $builder = app(EventSearchDocumentBuilder::class);
        $builder->index($session);

        $doc = EventSearchDocument::where('event_session_id', $session->id)->first();

        expect($doc)->not->toBeNull();
        expect($doc->document_type)->toBe('session');
    });
});

describe('remove', function (): void {
    it('deletes search documents for the event', function (): void {
        $event = createSearchEvent();

        $builder = app(EventSearchDocumentBuilder::class);
        $builder->buildForEvent($event);

        expect(EventSearchDocument::where('event_id', $event->id)->exists())->toBeTrue();

        $builder->remove($event);

        expect(EventSearchDocument::where('event_id', $event->id)->exists())->toBeFalse();
    });

    it('deletes search documents for an occurrence and its sessions', function (): void {
        $occurrence = createSearchOccurrence();
        $session = createSearchSession($occurrence);

        $builder = app(EventSearchDocumentBuilder::class);
        $builder->buildForOccurrence($occurrence);
        $builder->buildForSession($session);

        expect(EventSearchDocument::where('event_occurrence_id', $occurrence->id)->exists())->toBeTrue();
        expect(EventSearchDocument::where('event_session_id', $session->id)->exists())->toBeTrue();

        $builder->remove($occurrence);

        expect(EventSearchDocument::where('event_occurrence_id', $occurrence->id)->exists())->toBeFalse();
        expect(EventSearchDocument::where('event_session_id', $session->id)->exists())->toBeFalse();
    });

    it('deletes search documents for the session', function (): void {
        $session = createSearchSession();

        $builder = app(EventSearchDocumentBuilder::class);
        $builder->buildForSession($session);

        expect(EventSearchDocument::where('event_session_id', $session->id)->exists())->toBeTrue();

        $builder->remove($session);

        expect(EventSearchDocument::where('event_session_id', $session->id)->exists())->toBeFalse();
    });
});

describe('queued job serialization', function (): void {
    it('restores the latest event state after serialization', function (): void {
        $event = createSearchEvent();
        $job = new BuildEventSearchDocumentJob($event);

        $event->title = 'Updated title';
        $event->save();

        /** @var BuildEventSearchDocumentJob $restoredJob */
        $restoredJob = unserialize(serialize($job));
        $restoredJob->handle();

        expect(EventSearchDocument::where('event_id', $event->id)->first()?->title)
            ->toBe('Updated title');
    });
});
