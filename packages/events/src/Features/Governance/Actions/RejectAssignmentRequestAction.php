<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Governance\Actions;

use AIArmada\Events\Data\AssignmentRequestStatus;
use AIArmada\Events\Features\Governance\Models\EventManagementAssignmentRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RejectAssignmentRequestAction
{
    public function handle(
        EventManagementAssignmentRequest $request,
        Model $reviewer,
        string $reviewerNote,
    ): void {
        DB::transaction(function () use ($request, $reviewer, $reviewerNote): void {
            $locked = EventManagementAssignmentRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof EventManagementAssignmentRequest) {
                throw new RuntimeException('The assignment request no longer exists.');
            }

            if ($locked->status !== AssignmentRequestStatus::Pending) {
                throw new RuntimeException('Only pending requests can be rejected.');
            }

            $locked->status = AssignmentRequestStatus::Rejected;
            $locked->reviewer_type = $reviewer->getMorphClass();
            $locked->reviewer_id = $reviewer->getKey();
            $locked->reviewer_note = $reviewerNote;
            $locked->reviewed_at = CarbonImmutable::now();
            $locked->save();
        });
    }
}
