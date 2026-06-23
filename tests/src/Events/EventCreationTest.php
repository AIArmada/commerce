<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventLifecycleWorkflow;
use AIArmada\Events\Models\Event;
use AIArmada\Events\Models\EventOccurrence;
use AIArmada\Events\Models\EventSubmission;
use AIArmada\Events\Services\DefaultEventSubmissionConverter;

it('creates an event in draft status', function (): void {
    $event = Event::factory()->create();

    expect($event->status->getValue())->toBe(Event::DRAFT);
});

it('creates an event with an occurrence', function (): void {
    $event = Event::factory()->create();
    $occurrence = EventOccurrence::factory()->create(['event_id' => $event->id]);

    expect($event->occurrences)->toHaveCount(1);
    expect($occurrence->event_id)->toBe($event->id);
});

it('normalizes submission content when converting a submission', function (): void {
    $originalOwnerEnabled = config('events.features.owner.enabled');
    config()->set('events.features.owner.enabled', false);

    try {
        $target = User::factory()->create();
        $submission = EventSubmission::query()->create([
            'submitter_type' => $target->getMorphClass(),
            'submitter_id' => $target->getKey(),
            'target_type' => $target->getMorphClass(),
            'target_id' => $target->getKey(),
            'submission_data' => [
                'title' => '  Ramadan   Lecture  ',
                'summary' => '   ',
                'description' => '  Event description  ',
                'starts_at' => '2026-07-01 09:00:00',
                'ends_at' => '2026-07-01 10:00:00',
            ],
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $event = app(DefaultEventSubmissionConverter::class)->convert($submission);

        expect($event->title)->toBe('Ramadan Lecture')
            ->and($event->summary)->toBeNull()
            ->and($event->description)->toBe('Event description');

        $occurrence = $event->occurrences()->first();

        expect($occurrence?->title)->toBe('Ramadan Lecture');
    } finally {
        config()->set('events.features.owner.enabled', $originalOwnerEnabled);
    }
});

it('creates an event with owner polymorphic reference', function (): void {
    $owner = User::query()->create([
        'name' => 'Event Owner',
        'email' => 'event-owner-' . uniqid() . '@example.com',
        'password' => 'secret',
    ]);

    $event = OwnerContext::withOwner($owner, function (): Event {
        return Event::factory()->create();
    });

    expect($event->owner_type)->toBe($owner->getMorphClass())
        ->and($event->owner_id)->toBe((string) $owner->getKey());
});

it('publishes event using lifecycle workflow', function (): void {
    $event = Event::factory()->create(['status' => 'scheduled']);

    app(EventLifecycleWorkflow::class)->publish($event);
    $event->refresh();

    expect($event->status->getValue())->toBe(Event::PUBLISHED);
    expect($event->published_at)->not->toBeNull();
});

it('cancels event and creates change log', function (): void {
    $event = Event::factory()->create(['status' => 'published']);

    app(EventLifecycleWorkflow::class)->cancel($event, 'Test cancellation');
    $event->refresh();

    expect($event->status->getValue())->toBe('cancelled');
    expect($event->cancelled_at)->not->toBeNull();
    expect($event->changeLogs)->toHaveCount(1);
});
