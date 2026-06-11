<?php

declare(strict_types=1);

namespace Tests\src\Events;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Services\DefaultEventModerationWorkflow;

it('submits an event for review', function (): void {
    $event = Event::query()->create(['name' => 'Submit Review', 'slug' => 'submit-review', 'status' => 'draft']);
    $workflow = app(DefaultEventModerationWorkflow::class);

    $submission = $workflow->submit($event);

    expect($submission->status)->toBe('pending');
    expect($event->fresh()->moderation_status->value)->toBe('pending');
});

it('approves a pending event', function (): void {
    $event = Event::query()->create(['name' => 'Approve Event', 'slug' => 'approve-event', 'moderation_status' => 'pending', 'status' => 'draft']);
    $workflow = app(DefaultEventModerationWorkflow::class);

    $review = $workflow->approve($event, context: ['reason_key' => 'approved_for_publish']);

    expect($review->decision->value)->toBe('approved');
    expect($event->fresh()->moderation_status->value)->toBe('approved');
});

it('rejects an event with a reason', function (): void {
    $event = Event::query()->create(['name' => 'Reject Event', 'slug' => 'reject-event', 'moderation_status' => 'pending', 'status' => 'draft']);
    $workflow = app(DefaultEventModerationWorkflow::class);

    $review = $workflow->reject($event, context: ['reason_key' => 'policy_violation', 'note' => 'Violates community guidelines']);

    expect($review->decision->value)->toBe('rejected');
    expect($event->fresh()->moderation_status->value)->toBe('rejected');
});

it('requests changes on an event', function (): void {
    $event = Event::query()->create(['name' => 'Request Changes', 'slug' => 'request-changes', 'moderation_status' => 'pending', 'status' => 'draft']);
    $workflow = app(DefaultEventModerationWorkflow::class);

    $review = $workflow->requestChanges($event, context: ['reason_key' => 'needs_more_information', 'note' => 'Please add more details']);

    expect($review->decision->value)->toBe('changes_requested');
    expect($event->fresh()->moderation_status->value)->toBe('changes_requested');
});
