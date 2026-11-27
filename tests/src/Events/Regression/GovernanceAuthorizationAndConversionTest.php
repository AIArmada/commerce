<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Events\Contracts\EventSubmissionConverter;
use AIArmada\Events\Data\AssignmentRequestStatus;
use AIArmada\Events\Features\Catalog\Models\EventOrganizer;
use AIArmada\Events\Features\Governance\Actions\CancelAssignmentRequestAction;
use AIArmada\Events\Features\Governance\Actions\SubmitAssignmentRequestAction;
use AIArmada\Events\Features\Governance\Models\EventManagementAssignment;
use AIArmada\Events\Features\Governance\Models\EventManagementAssignmentRequest;
use AIArmada\Events\Features\Governance\Models\EventSubmission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event as EventFacade;
use Spatie\ModelStates\Events\StateChanged;

beforeEach(function (): void {
    config()->set('events.features.owner.enabled', false);
});

it('allows the requestor to cancel a pending assignment request', function (): void {
    $manageable = EventOrganizer::create([
        'name' => 'Cancel Organizer',
        'slug' => 'cancel-organizer',
        'status' => 'active',
        'visibility' => 'public',
        'sort_order' => 0,
    ]);
    $requestor = User::factory()->create();

    $request = (new SubmitAssignmentRequestAction)->handle($manageable, $requestor, 'Please add me.');
    (new CancelAssignmentRequestAction)->handle($request, $requestor);

    expect($request->fresh()->status)->toBe(AssignmentRequestStatus::Cancelled)
        ->and($request->fresh()->cancelled_at)->not->toBeNull();
});

it('allows an assigned manager to cancel but denies an unrelated actor', function (): void {
    $manageable = EventOrganizer::create([
        'name' => 'Managed Organizer',
        'slug' => 'managed-organizer',
        'status' => 'active',
        'visibility' => 'public',
        'sort_order' => 0,
    ]);
    $requestor = User::factory()->create();
    $manager = User::factory()->create();
    $unrelated = User::factory()->create();

    EventManagementAssignment::query()->create([
        'manageable_type' => $manageable->getMorphClass(),
        'manageable_id' => $manageable->getKey(),
        'manager_type' => $manager->getMorphClass(),
        'manager_id' => $manager->getKey(),
        'role' => 'manager',
    ]);

    $first = (new SubmitAssignmentRequestAction)->handle($manageable, $requestor, 'Manager can cancel.');
    (new CancelAssignmentRequestAction)->handle($first, $manager);

    expect($first->fresh()->status)->toBe(AssignmentRequestStatus::Cancelled);

    $second = (new SubmitAssignmentRequestAction)->handle($manageable, User::factory()->create(), 'Unrelated cannot cancel.');

    expect(fn () => (new CancelAssignmentRequestAction)->handle($second, $unrelated))
        ->toThrow(AuthorizationException::class);

    expect($second->fresh()->status)->toBe(AssignmentRequestStatus::Pending);
});

it('serializes duplicate pending submission through the manageable row', function (): void {
    $manageable = EventOrganizer::create([
        'name' => 'Duplicate Organizer',
        'slug' => 'duplicate-organizer',
        'status' => 'active',
        'visibility' => 'public',
        'sort_order' => 0,
    ]);
    $requestor = User::factory()->create();
    $action = new SubmitAssignmentRequestAction;

    $request = $action->handle($manageable, $requestor, 'First request.');

    expect(fn () => $action->handle($manageable, $requestor, 'Second request.'))
        ->toThrow(RuntimeException::class, 'A pending request already exists');

    expect(EventManagementAssignmentRequest::query()
        ->where('manageable_type', $manageable->getMorphClass())
        ->where('manageable_id', $manageable->getKey())
        ->count())->toBe(1)
        ->and($request->status)->toBe(AssignmentRequestStatus::Pending);
});

it('links the converted event before state listeners observe the submission', function (): void {
    $owner = User::factory()->create();
    $submission = EventSubmission::query()->create([
        'submitter_type' => $owner->getMorphClass(),
        'submitter_id' => $owner->getKey(),
        'target_type' => $owner->getMorphClass(),
        'target_id' => $owner->getKey(),
        'submission_data' => ['title' => 'Linked Before State Change'],
        'status' => 'pending',
        'submitted_at' => now(),
    ]);

    $observedEventId = null;

    EventFacade::listen(StateChanged::class, function (StateChanged $event) use (&$observedEventId): void {
        if ($event->model instanceof EventSubmission) {
            $observedEventId = $event->model->getAttribute('event_id');
        }
    });

    $eventId = app(EventSubmissionConverter::class)->convert((string) $submission->getKey());

    expect($observedEventId)->toBe($eventId)
        ->and($submission->fresh()->event_id)->toBe($eventId)
        ->and($submission->fresh()->status->getValue())->toBe('converted');
});
