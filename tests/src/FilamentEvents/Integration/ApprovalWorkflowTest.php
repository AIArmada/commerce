<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Events\Contracts\EventApprovalWorkflow;
use AIArmada\Events\Contracts\EventModerationWorkflow;
use AIArmada\Events\Features\Catalog\Models\Event;
use AIArmada\Events\Features\Governance\Models\EventApprovalRequest;
use AIArmada\Events\Features\Governance\Models\EventSubmission;
use Illuminate\Auth\Access\AuthorizationException;

it('authorizes and serializes approval decisions and assignments', function (): void {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();

    OwnerContext::withOwner($owner, function () use ($owner, $outsider): void {
        $event = Event::factory()->create();
        $submission = EventSubmission::factory()->create(['event_id' => $event->id]);
        $request = EventApprovalRequest::factory()->create([
            'approvable_type' => $submission->getMorphClass(),
            'approvable_id' => $submission->id,
        ]);

        $workflow = app(EventApprovalWorkflow::class);

        expect(fn () => $workflow->assignToActor($request->id, $outsider))->toThrow(AuthorizationException::class);

        $workflow->assignToActor($request->id, $owner);

        expect($request->refresh()->assigned_to_id)->toBe((string) $owner->getKey());

        $workflow->approve($request->id, $owner, 'Approved');

        expect($request->refresh()->status)->toBe('approved')
            ->and($request->notes)->toBe('Approved')
            ->and($submission->refresh()->status->getValue())->toBe('approved');

        expect(fn () => $workflow->reject($request->id, $owner, 'Stale request'))->toThrow(InvalidArgumentException::class);
    });
});

it('rolls back approval request changes when moderation fails', function (): void {
    $owner = User::factory()->create();

    OwnerContext::withOwner($owner, function () use ($owner): void {
        $event = Event::factory()->create();
        $submission = EventSubmission::factory()->create(['event_id' => $event->id]);
        $request = EventApprovalRequest::factory()->create([
            'approvable_type' => $submission->getMorphClass(),
            'approvable_id' => $submission->id,
        ]);

        $moderation = Mockery::mock(EventModerationWorkflow::class);
        $moderation->shouldReceive('approve')->once()->andThrow(new RuntimeException('Moderation failed'));
        app()->instance(EventModerationWorkflow::class, $moderation);

        expect(fn () => app(EventApprovalWorkflow::class)->approve($request->id, $owner))
            ->toThrow(RuntimeException::class, 'Moderation failed');

        expect($request->refresh()->status)->toBe('pending')
            ->and($request->approved_at)->toBeNull()
            ->and($submission->refresh()->status->getValue())->toBe('pending');
    });
});

it('rejects approval decisions for non-submission subjects', function (): void {
    $owner = User::factory()->create();

    OwnerContext::withOwner($owner, function () use ($owner): void {
        $event = Event::factory()->create();
        $request = EventApprovalRequest::factory()->create([
            'approvable_type' => $event->getMorphClass(),
            'approvable_id' => $event->id,
            'status' => 'pending',
        ]);

        expect(fn () => app(EventApprovalWorkflow::class)->approve($request->id, $owner))
            ->toThrow(InvalidArgumentException::class, 'Approval requests must point to an event submission.');

        expect($request->refresh()->status)->toBe('pending');
    });
});
