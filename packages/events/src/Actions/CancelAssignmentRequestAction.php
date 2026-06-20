<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Enums\AssignmentRequestStatus;
use AIArmada\Events\Models\EventManagementAssignmentRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class CancelAssignmentRequestAction
{
    public function handle(
        EventManagementAssignmentRequest $request,
        Model $canceller,
    ): void {
        if ($request->status !== AssignmentRequestStatus::Pending) {
            throw new RuntimeException('Only pending requests can be cancelled.');
        }

        $request->status = AssignmentRequestStatus::Cancelled;
        $request->cancelled_at = CarbonImmutable::now();
        $request->save();
    }
}
