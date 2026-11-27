<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Governance\Actions;

use AIArmada\Events\Contracts\CanManageEventsFor;
use AIArmada\Events\Data\AssignmentRequestStatus;
use AIArmada\Events\Features\Governance\Models\EventManagementAssignment;
use AIArmada\Events\Features\Governance\Models\EventManagementAssignmentRequest;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CancelAssignmentRequestAction
{
    public function handle(
        EventManagementAssignmentRequest $request,
        Model $canceller,
    ): void {
        DB::transaction(function () use ($request, $canceller): void {
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

            if (! $this->canCancel($locked, $canceller)) {
                throw new AuthorizationException('Only the requestor or a manager of the manageable can cancel this assignment request.');
            }

            $locked->status = AssignmentRequestStatus::Cancelled;
            $locked->cancelled_at = CarbonImmutable::now();
            $locked->save();
        });
    }

    private function canCancel(EventManagementAssignmentRequest $request, Model $canceller): bool
    {
        if ($this->isSameModel(
            (string) $request->getAttribute('requestor_type'),
            (string) $request->getAttribute('requestor_id'),
            $canceller,
        )) {
            return true;
        }

        $manageable = $request->getRelationValue('manageable');

        if (! $manageable instanceof Model) {
            return false;
        }

        $exists = EventManagementAssignment::query()
            ->where('manageable_type', $manageable->getMorphClass())
            ->where('manageable_id', $manageable->getKey())
            ->where('manager_type', $canceller->getMorphClass())
            ->where('manager_id', $canceller->getKey())
            ->exists();

        if ($exists) {
            return true;
        }

        if ($manageable instanceof CanManageEventsFor) {
            return $manageable->canManageEventsFor($canceller, 'update', $manageable);
        }

        return $this->isSameModel(
            (string) $manageable->getAttribute('owner_type'),
            (string) $manageable->getAttribute('owner_id'),
            $canceller,
        );
    }

    private function isSameModel(string $type, string $id, Model $model): bool
    {
        return $type !== ''
            && $id !== ''
            && $type === $model->getMorphClass()
            && $id === (string) $model->getKey();
    }
}
