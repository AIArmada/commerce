<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Governance\Actions;

use AIArmada\Events\Data\AssignmentRequestStatus;
use AIArmada\Events\Features\Governance\Models\EventManagementAssignment;
use AIArmada\Events\Features\Governance\Models\EventManagementAssignmentRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ApproveAssignmentRequestAction
{
    public function __construct(
        private readonly string $defaultRole = 'manager',
        private readonly ?SyncManagementAssignmentToAuthzAction $sync = null,
    ) {}

    public function handle(
        EventManagementAssignmentRequest $request,
        Model $reviewer,
        ?string $role = null,
        ?string $reviewerNote = null,
    ): EventManagementAssignment {
        return DB::transaction(function () use ($request, $reviewer, $role, $reviewerNote): EventManagementAssignment {
            $locked = EventManagementAssignmentRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof EventManagementAssignmentRequest) {
                throw new RuntimeException('The assignment request no longer exists.');
            }

            if ($locked->status !== AssignmentRequestStatus::Pending) {
                throw new RuntimeException('Only pending requests can be approved.');
            }

            $manageable = $locked->manageable;
            $requestor = $locked->requestor;

            if (! $manageable instanceof Model || ! $requestor instanceof Model) {
                throw new RuntimeException('The assignment request must resolve both manageable and requestor models before approval.');
            }

            $assignment = new EventManagementAssignment;
            $assignment->manageable_type = $manageable->getMorphClass();
            $assignment->manageable_id = $manageable->getKey();
            $assignment->manager_type = $requestor->getMorphClass();
            $assignment->manager_id = $requestor->getKey();
            $assignment->assigned_by_type = $reviewer->getMorphClass();
            $assignment->assigned_by_id = $reviewer->getKey();
            $assignment->role = $role ?? $this->defaultRole;
            $assignment->save();

            $locked->status = AssignmentRequestStatus::Approved;
            $locked->reviewer_type = $reviewer->getMorphClass();
            $locked->reviewer_id = $reviewer->getKey();
            $locked->reviewer_note = $reviewerNote;
            $locked->reviewed_at = CarbonImmutable::now();
            $locked->save();

            $this->syncAssignment($assignment);

            return $assignment;
        });
    }

    private function syncAssignment(EventManagementAssignment $assignment): void
    {
        ($this->sync ?? app(SyncManagementAssignmentToAuthzAction::class))->handle($assignment);
    }
}
