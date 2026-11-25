<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Events\Data\AssignmentRequestStatus;
use AIArmada\Events\Features\Governance\Actions\ApproveAssignmentRequestAction;
use AIArmada\Events\Features\Governance\Actions\SubmitAssignmentRequestAction;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
});

it('approves a pending request exactly once', function (): void {
    $manageable = User::factory()->create();
    $requestor = User::factory()->create();

    $request = app(SubmitAssignmentRequestAction::class)->handle($manageable, $requestor, 'Please approve.');

    app(ApproveAssignmentRequestAction::class)->handle($request, $requestor);

    expect($request->fresh()->status)->toBe(AssignmentRequestStatus::Approved);

    expect(fn () => app(ApproveAssignmentRequestAction::class)->handle($request->fresh(), $requestor))
        ->toThrow(RuntimeException::class, 'Only pending requests can be approved.');
});

it('rejects approvals for requests that no longer exist', function (): void {
    $manageable = User::factory()->create();
    $requestor = User::factory()->create();

    $request = app(SubmitAssignmentRequestAction::class)->handle($manageable, $requestor, 'Please approve.');
    $request->delete();

    expect(fn () => app(ApproveAssignmentRequestAction::class)->handle($request, $requestor))
        ->toThrow(RuntimeException::class, 'The assignment request no longer exists.');
});
