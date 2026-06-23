<?php

declare(strict_types=1);

use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventAccessPolicy;
use AIArmada\Events\Models\EventAudienceProfile;
use AIArmada\Events\Models\EventClassification;
use AIArmada\Events\Models\EventNotificationBatch;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSession;
use AIArmada\Events\Models\EventTaxonomy;
use AIArmada\Events\Models\EventTerm;
use AIArmada\Events\Models\EventTimeExpression;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
});

it('exposes session-scoped relations for inherited event features', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create([
        'event_id' => $event->id,
    ]);
    $session = EventSession::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
    ]);

    EventAccessPolicy::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'event_session_id' => $session->id,
    ]);
    EventAudienceProfile::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'event_session_id' => $session->id,
    ]);
    $taxonomy = EventTaxonomy::factory()->create();
    $term = EventTerm::factory()->create([
        'event_taxonomy_id' => $taxonomy->id,
    ]);
    EventClassification::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'event_session_id' => $session->id,
        'event_taxonomy_id' => $taxonomy->id,
        'event_term_id' => $term->id,
    ]);
    EventTimeExpression::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'event_session_id' => $session->id,
    ]);
    EventNotificationBatch::factory()->create([
        'event_id' => $event->id,
        'event_occurrence_id' => $occurrence->id,
        'event_session_id' => $session->id,
    ]);

    expect($session->accessPolicies)->toHaveCount(1)
        ->and($session->audienceProfiles)->toHaveCount(1)
        ->and($session->classifications)->toHaveCount(1)
        ->and($session->timeExpressions)->toHaveCount(1)
        ->and($session->notificationBatches)->toHaveCount(1);
});
