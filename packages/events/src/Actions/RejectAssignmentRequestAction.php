<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Enums\AssignmentRequestStatus;
use AIArmada\Events\Models\EventManagementAssignmentRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class RejectAssignmentRequestAction
{
    public function handle(
        EventManagementAssignmentRequest $request,
        Model $reviewer,
        string $reviewerNote,
    ): void {
        if ($request->status !== AssignmentRequestStatus::Pending) {
            throw new RuntimeException('Only pending requests can be rejected.');
        }

        $request->status = AssignmentRequestStatus::Rejected;
        $request->reviewer_type = $reviewer->getMorphClass();
        $request->reviewer_id = $reviewer->getKey();
        $request->reviewer_note = $reviewerNote;
        $request->reviewed_at = CarbonImmutable::now();
        $request->save();
    }
}
