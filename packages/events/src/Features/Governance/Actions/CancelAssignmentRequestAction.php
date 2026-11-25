<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Governance\Actions;

use AIArmada\Events\Data\AssignmentRequestStatus;
use AIArmada\Events\Features\Governance\Models\EventManagementAssignmentRequest;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CancelAssignmentRequestAction
{
    public function handle(
        EventManagementAssignmentRequest $request,
        Model $canceller,
    ): void {
        DB::transaction(function () use ($request): void {
            $locked = EventManagementAssignmentRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof EventManagementAssignmentRequest) {
                throw new RuntimeException('The assignment request no longer exists.');
            }

            if ($locked->status !== AssignmentRequestStatus::Pending) {
                throw new RuntimeException('Only pending requests can be cancelled.');
            }

            $locked->status = AssignmentRequestStatus::Cancelled;
            $locked->cancelled_at = CarbonImmutable::now();
            $locked->save();
        });
    }
}
