<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventAttribute;
use AIArmada\Events\Models\EventAudience;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventTimeExpression;
use AIArmada\Events\Services\EventMetadataSyncService;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
});

function createEvent(): Event
{
    return OwnerContext::withOwner(null, fn () => Event::factory()->create());
}

function createOccurrenceForMetadataSync(?Event $event = null): EventOccurrence
{
    $event ??= createEvent();

    return OwnerContext::withOwner(null, fn () => EventOccurrence::query()->create([
        'event_id' => $event->id,
        'title' => 'Metadata Sync Occurrence',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHours(2),
        'timezone' => 'Asia/Kuala_Lumpur',
        'status' => 'scheduled',
        'visibility' => 'public',
        'delivery_mode' => 'physical',
    ]));
}

function createSessionForMetadataSync(?EventOccurrence $occurrence = null): EventSession
{
    $occurrence ??= createOccurrenceForMetadataSync();

    return OwnerContext::withOwner(null, fn () => EventSession::query()->create([
        'event_id' => $occurrence->event_id,
        'event_occurrence_id' => $occurrence->id,
        'title' => 'Metadata Sync Session',
        'summary' => 'Session summary',
        'description' => 'Session description',
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
        'timezone' => 'Asia/Kuala_Lumpur',
        'status' => 'scheduled',
        'visibility' => 'public',
        'delivery_mode' => 'physical',
        'sort_order' => 1,
    ]));
}

describe('EventMetadataSyncService::syncAttribute', function (): void {
    it('writes attribute key-value pairs into metadata', function (): void {
        $event = createEvent();

        EventAttribute::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'attribute_key' => 'gender_restriction',
            'attribute_value' => 'male_only',
        ]);

        EventAttribute::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'attribute_key' => 'max_capacity',
            'attribute_value' => '500',
        ]);

        app(EventMetadataSyncService::class)->syncAttribute($event);

        $event->refresh();

        expect($event->metadata)->toBe([
            'gender_restriction' => 'male_only',
            'max_capacity' => '500',
        ]);
    });

    it('prefers attribute_value_json over attribute_value', function (): void {
        $event = createEvent();

        EventAttribute::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'attribute_key' => 'speakers',
            'attribute_value' => '["a"]',
            'attribute_value_json' => ['ali', 'abu'],
        ]);

        app(EventMetadataSyncService::class)->syncAttribute($event);

        $event->refresh();

        expect($event->metadata)->toBe([
            'speakers' => ['ali', 'abu'],
        ]);
    });

    it('removes stale non-prefixed keys when always_rebuild is true', function (): void {
        $event = createEvent();
        $event->metadata = ['gender_restriction' => 'female_only', '_internal_note' => 'keep'];
        $event->save();

        EventAttribute::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'attribute_key' => 'max_capacity',
            'attribute_value' => '200',
        ]);

        app(EventMetadataSyncService::class)->syncAttribute($event);

        $event->refresh();

        expect($event->metadata)->toHaveKey('max_capacity');
        expect($event->metadata)->not->toHaveKey('gender_restriction');
    });

    it('preserves underscored keys during rebuild', function (): void {
        $event = createEvent();
        $event->metadata = ['_audiences' => ['age_group' => ['adults']], 'color' => 'red'];
        $event->save();

        EventAttribute::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'attribute_key' => 'max_capacity',
            'attribute_value' => '200',
        ]);

        app(EventMetadataSyncService::class)->syncAttribute($event);

        $event->refresh();

        expect($event->metadata)->toHaveKey('_audiences');
        expect($event->metadata)->toHaveKey('max_capacity');
        expect($event->metadata)->not->toHaveKey('color');
    });

    it('respects attribute_keys whitelist', function (): void {
        config()->set('events.attribute_sync.attribute_keys', ['gender_restriction']);

        $event = createEvent();

        EventAttribute::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'attribute_key' => 'gender_restriction',
            'attribute_value' => 'male_only',
        ]);

        EventAttribute::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'attribute_key' => 'max_capacity',
            'attribute_value' => '500',
        ]);

        app(EventMetadataSyncService::class)->syncAttribute($event);

        $event->refresh();

        expect($event->metadata)->toBe([
            'gender_restriction' => 'male_only',
        ]);
    });

    it('writes occurrence attribute key-value pairs into occurrence metadata', function (): void {
        $event = createEvent();
        $occurrence = createOccurrenceForMetadataSync($event);

        EventAttribute::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'attribute_key' => 'gender_restriction',
            'attribute_value' => 'female_only',
        ]);

        app(EventMetadataSyncService::class)->syncAttribute($occurrence);

        $occurrence->refresh();

        expect($occurrence->metadata)->toBe([
            'gender_restriction' => 'female_only',
        ]);
    });
});

describe('EventMetadataSyncService::syncAudience', function (): void {
    it('groups audiences by type under _audiences key', function (): void {
        $event = createEvent();

        EventAudience::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'audience_type' => 'age_group',
            'value' => 'adults',
        ]);

        EventAudience::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'audience_type' => 'age_group',
            'value' => 'children',
        ]);

        EventAudience::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'audience_type' => 'gender',
            'value' => 'male',
        ]);

        app(EventMetadataSyncService::class)->syncAudience($event);

        $event->refresh();

        expect($event->metadata)->toBe([
            '_audiences' => [
                'age_group' => ['adults', 'children'],
                'gender' => ['male'],
            ],
        ]);
    });

    it('removes _audiences key when no audiences exist', function (): void {
        $event = createEvent();
        $event->metadata = ['_audiences' => ['age_group' => ['adults']]];
        $event->save();

        app(EventMetadataSyncService::class)->syncAudience($event);

        $event->refresh();

        expect($event->metadata)->not->toHaveKey('_audiences');
    });

    it('respects audience_types whitelist', function (): void {
        config()->set('events.attribute_sync.audience_types', ['age_group']);

        $event = createEvent();

        EventAudience::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'audience_type' => 'age_group',
            'value' => 'adults',
        ]);

        EventAudience::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'audience_type' => 'gender',
            'value' => 'male',
        ]);

        app(EventMetadataSyncService::class)->syncAudience($event);

        $event->refresh();

        expect($event->metadata['_audiences'])->toHaveKey('age_group');
        expect($event->metadata['_audiences'])->not->toHaveKey('gender');
    });
});

describe('EventMetadataSyncService::syncTimeExpression', function (): void {
    it('stores time expressions under _time_expressions key', function (): void {
        $event = createEvent();

        EventTimeExpression::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'time_mode' => 'after_anchor',
            'anchor_type' => 'prayer',
            'anchor_code' => 'maghrib',
            'relation' => 'after',
            'offset_minutes' => 30,
            'display_label' => 'Selepas Maghrib',
        ]);

        app(EventMetadataSyncService::class)->syncTimeExpression($event);

        $event->refresh();

        expect($event->metadata)->toHaveKey('_time_expressions');
        expect($event->metadata['_time_expressions'])->toHaveCount(1);
        expect($event->metadata['_time_expressions'][0])->toMatchArray([
            'anchor_type' => 'prayer',
            'anchor_code' => 'maghrib',
            'relation' => 'after',
            'offset_minutes' => 30,
            'display_label' => 'Selepas Maghrib',
        ]);
    });

    it('removes _time_expressions key when no expressions exist', function (): void {
        $event = createEvent();
        $event->metadata = ['_time_expressions' => [['anchor_type' => 'prayer']]];
        $event->save();

        app(EventMetadataSyncService::class)->syncTimeExpression($event);

        $event->refresh();

        expect($event->metadata)->not->toHaveKey('_time_expressions');
    });

    it('stores session time expressions under _time_expressions key', function (): void {
        $event = createEvent();
        $occurrence = createOccurrenceForMetadataSync($event);
        $session = createSessionForMetadataSync($occurrence);

        EventTimeExpression::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'event_occurrence_id' => $occurrence->id,
            'event_session_id' => $session->id,
            'time_mode' => 'after_anchor',
            'anchor_type' => 'prayer',
            'anchor_code' => 'maghrib',
            'relation' => 'after',
            'offset_minutes' => 30,
            'display_label' => 'Selepas Maghrib',
        ]);

        app(EventMetadataSyncService::class)->syncTimeExpression($session);

        $session->refresh();

        expect($session->metadata)->toHaveKey('_time_expressions');
        expect($session->metadata['_time_expressions'])->toHaveCount(1);
        expect($session->metadata['_time_expressions'][0])->toMatchArray([
            'anchor_type' => 'prayer',
            'anchor_code' => 'maghrib',
            'relation' => 'after',
            'offset_minutes' => 30,
            'display_label' => 'Selepas Maghrib',
        ]);
    });
});

describe('EventMetadataSyncService::rebuild', function (): void {
    it('syncs all sources', function (): void {
        $event = createEvent();

        EventAttribute::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'attribute_key' => 'gender_restriction',
            'attribute_value' => 'male_only',
        ]);

        EventAudience::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'audience_type' => 'age_group',
            'value' => 'adults',
        ]);

        EventTimeExpression::create([
            'id' => (string) Str::uuid(),
            'event_id' => $event->id,
            'time_mode' => 'after_anchor',
            'anchor_type' => 'prayer',
            'anchor_code' => 'maghrib',
            'relation' => 'after',
            'offset_minutes' => 0,
            'display_label' => 'Selepas Maghrib',
        ]);

        app(EventMetadataSyncService::class)->rebuild($event);

        $event->refresh();

        expect($event->metadata)->toHaveKey('gender_restriction');
        expect($event->metadata)->toHaveKey('_audiences');
        expect($event->metadata)->toHaveKey('_time_expressions');
    });
});
