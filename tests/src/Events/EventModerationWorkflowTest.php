<?php

declare(strict_types=1);

namespace Tests\src\Events;

use AIArmada\Events\Models\Event;
use AIArmada\Events\Services\DefaultEventModerationWorkflow;
use AIArmada\Commerce\Tests\TestCase;

it('submits an event for review', function (): void {
    $event = Event::factory()->create(['moderation_status' => 'draft', 'status' => 'draft']);
    $workflow = app(DefaultEventModerationWorkflow::class);

    $result = $workflow->submit($event);

    expect($result->moderation_status->value)->toBe('pending');
});

it('approves a pending event', function (): void {
    $event = Event::factory()->create(['moderation_status' => 'pending', 'status' => 'draft']);
    $workflow = app(DefaultEventModerationWorkflow::class);

    $result = $workflow->approve($event, reasonKey: 'approved_for_publish');

    expect($result->moderation_status->value)->toBe('approved');
});

it('rejects an event with a reason', function (): void {
    $event = Event::factory()->create(['moderation_status' => 'pending', 'status' => 'draft']);
    $workflow = app(DefaultEventModerationWorkflow::class);

    $result = $workflow->reject($event, reasonKey: 'policy_violation', note: 'Violates community guidelines');

    expect($result->moderation_status->value)->toBe('rejected');
});

it('requests changes on an event', function (): void {
    $event = Event::factory()->create(['moderation_status' => 'pending', 'status' => 'draft']);
    $workflow = app(DefaultEventModerationWorkflow::class);

    $result = $workflow->requestChanges($event, reasonKey: 'needs_more_information', note: 'Please add more details');

    expect($result->moderation_status->value)->toBe('changes_requested');
});
