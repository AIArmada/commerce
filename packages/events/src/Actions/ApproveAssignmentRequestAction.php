<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Enums\AssignmentRequestStatus;
use AIArmada\Events\Models\EventManagementAssignment;
use AIArmada\Events\Models\EventManagementAssignmentRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class ApproveAssignmentRequestAction
{
    public function __construct(
        private readonly string $defaultRole = 'manager',
    ) {}

    public function handle(
        EventManagementAssignmentRequest $request,
        Model $reviewer,
        ?string $role = null,
        ?string $reviewerNote = null,
    ): EventManagementAssignment {
        if ($request->status !== AssignmentRequestStatus::Pending) {
            throw new RuntimeException('Only pending requests can be approved.');
        }

        $manageable = $request->manageable;
        $requestor = $request->requestor;

        $assignment = new EventManagementAssignment;
        $assignment->manageable_type = $manageable->getMorphClass();
        $assignment->manageable_id = $manageable->getKey();
        $assignment->manager_type = $requestor->getMorphClass();
        $assignment->manager_id = $requestor->getKey();
        $assignment->assigned_by_type = $reviewer->getMorphClass();
        $assignment->assigned_by_id = $reviewer->getKey();
        $assignment->role = $role ?? $this->defaultRole;
        $assignment->save();

        $request->status = AssignmentRequestStatus::Approved;
        $request->reviewer_type = $reviewer->getMorphClass();
        $request->reviewer_id = $reviewer->getKey();
        $request->reviewer_note = $reviewerNote;
        $request->reviewed_at = CarbonImmutable::now();
        $request->save();

        return $assignment;
    }
}
