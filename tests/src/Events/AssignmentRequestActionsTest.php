<?php

declare(strict_types=1);

use AIArmada\Authz\Models\Role;
use AIArmada\Authz\Support\AuthzScopeResolver;
use AIArmada\Customers\Models\Customer;
use AIArmada\Events\Actions\ApproveAssignmentRequestAction;
use AIArmada\Events\Actions\CancelAssignmentRequestAction;
use AIArmada\Events\Actions\RejectAssignmentRequestAction;
use AIArmada\Events\Actions\SubmitAssignmentRequestAction;
use AIArmada\Events\Enums\AssignmentRequestStatus;
use AIArmada\Events\Models\EventManagementAssignment;
use AIArmada\Events\Models\EventManagementAssignmentRequest;
use AIArmada\Events\Models\EventOrganizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function makeEventOrganizer(string $suffix): EventOrganizer
{
    return EventOrganizer::create([
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
    $manageable = makeEventOrganizer('pending');
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
    $manageable = makeEventOrganizer('workflow');
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

test('approved assignments sync their configured role to the resolved authz scope', function (): void {
    config()->set('authz.scopes.enabled', true);

    $manageable = makeEventOrganizer('authz-sync');
    $requestor = makeCustomer('requestor-authz-sync');
    $reviewer = makeCustomer('reviewer-authz-sync');
    $scopeId = AuthzScopeResolver::resolveId($manageable);

    expect($scopeId)->not->toBeNull();

    $role = Role::create([
        'name' => 'lead',
        'guard_name' => 'web',
        'team_id' => $scopeId,
    ]);

    $request = (new SubmitAssignmentRequestAction)->handle(
        $manageable,
        $requestor,
        'Manage this organizer.',
    );

    (new ApproveAssignmentRequestAction)->handle($request, $reviewer, 'lead');

    expect(DB::table('model_has_roles')
        ->where('role_id', $role->getKey())
        ->where('model_id', $requestor->getKey())
        ->where('model_type', $requestor->getMorphClass())
        ->where('team_id', $scopeId)
        ->exists())->toBeTrue();
});
