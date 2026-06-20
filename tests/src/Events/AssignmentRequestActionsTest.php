<?php

declare(strict_types=1);

use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Actions\ApproveAssignmentRequestAction;
use AIArmada\Events\Actions\CancelAssignmentRequestAction;
use AIArmada\Events\Actions\RejectAssignmentRequestAction;
use AIArmada\Events\Actions\SubmitAssignmentRequestAction;
use AIArmada\Events\Enums\AssignmentRequestStatus;
use AIArmada\Events\Models\EventManagementAssignment;
use AIArmada\Events\Models\EventManagementAssignmentRequest;
use AIArmada\Events\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeOrganization(string $suffix): Organization
{
    return Organization::create([
        'name' => 'Organization ' . $suffix,
        'slug' => 'organization-' . $suffix,
        'status' => 'active',
        'visibility' => 'public',
        'sort_order' => 0,
    ]);
}

function makeCustomer(string $suffix): Customer
{
    return Customer::create([
        'first_name' => 'Customer',
        'last_name' => $suffix,
        'email' => 'customer-' . $suffix . '-' . uniqid() . '@example.com',
        'status' => 'active',
    ]);
}

test('assignment requests can be submitted only once while pending', function (): void {
    $manageable = makeOrganization('pending');
    $requestor = makeCustomer('requestor');

    $action = new SubmitAssignmentRequestAction;

    $request = $action->handle($manageable, $requestor, 'Please grant access.');

    expect($request)->toBeInstanceOf(EventManagementAssignmentRequest::class)
        ->and($request->status)->toBe(AssignmentRequestStatus::Pending)
        ->and($request->manageable_id)->toBe($manageable->getKey())
        ->and($request->requestor_id)->toBe($requestor->getKey());

    expect(fn (): EventManagementAssignmentRequest => $action->handle($manageable, $requestor, 'Duplicate request.'))
        ->toThrow(RuntimeException::class, 'A pending request already exists for this manageable and requestor.');
});

test('assignment requests can be approved and rejected with timestamps', function (): void {
    $manageable = makeOrganization('workflow');
    $requestor = makeCustomer('requestor-workflow');
    $reviewer = makeCustomer('reviewer-workflow');

    $submitAction = new SubmitAssignmentRequestAction;
    $approveAction = new ApproveAssignmentRequestAction;
    $cancelAction = new CancelAssignmentRequestAction;
    $rejectAction = new RejectAssignmentRequestAction;

    $approvedRequest = $submitAction->handle($manageable, $requestor, 'Approve this person.');
    $assignment = $approveAction->handle($approvedRequest, $reviewer, 'lead', 'Approved.');

    expect($assignment)->toBeInstanceOf(EventManagementAssignment::class)
        ->and($assignment->manageable_id)->toBe($manageable->getKey())
        ->and($assignment->manager_id)->toBe($requestor->getKey())
        ->and($assignment->role)->toBe('lead');

    $approvedRequest->refresh();

    expect($approvedRequest->status)->toBe(AssignmentRequestStatus::Approved)
        ->and($approvedRequest->reviewer_id)->toBe($reviewer->getKey())
        ->and($approvedRequest->reviewer_note)->toBe('Approved.')
        ->and($approvedRequest->reviewed_at)->not->toBeNull();

    $rejectedRequest = $submitAction->handle($manageable, makeCustomer('requestor-reject'), 'Reject this person.');
    $rejectAction->handle($rejectedRequest, $reviewer, 'Not a fit.');

    $rejectedRequest->refresh();

    expect($rejectedRequest->status)->toBe(AssignmentRequestStatus::Rejected)
        ->and($rejectedRequest->reviewer_id)->toBe($reviewer->getKey())
        ->and($rejectedRequest->reviewer_note)->toBe('Not a fit.')
        ->and($rejectedRequest->reviewed_at)->not->toBeNull();

    $cancelledRequest = $submitAction->handle($manageable, makeCustomer('requestor-cancel'), 'Cancel this person.');
    $cancelAction->handle($cancelledRequest, $reviewer);

    $cancelledRequest->refresh();

    expect($cancelledRequest->status)->toBe(AssignmentRequestStatus::Cancelled)
        ->and($cancelledRequest->cancelled_at)->not->toBeNull();
});
