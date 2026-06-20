<?php

declare(strict_types=1);

namespace AIArmada\Events\Actions;

use AIArmada\Events\Enums\AssignmentRequestStatus;
use AIArmada\Events\Models\EventManagementAssignmentRequest;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class SubmitAssignmentRequestAction
{
    public function handle(
        Model $manageable,
        Model $requestor,
        string $justification,
        ?string $requestedRole = null,
    ): EventManagementAssignmentRequest {
        $existing = EventManagementAssignmentRequest::query()
            ->where('manageable_type', $manageable->getMorphClass())
            ->where('manageable_id', $manageable->getKey())
            ->where('requestor_type', $requestor->getMorphClass())
            ->where('requestor_id', $requestor->getKey())
            ->where('status', AssignmentRequestStatus::Pending)
            ->first();

        if ($existing !== null) {
            throw new RuntimeException('A pending request already exists for this manageable and requestor.');
        }

        $request = new EventManagementAssignmentRequest;
        $request->manageable_type = $manageable->getMorphClass();
        $request->manageable_id = $manageable->getKey();
        $request->requestor_type = $requestor->getMorphClass();
        $request->requestor_id = $requestor->getKey();
        $request->status = AssignmentRequestStatus::Pending;
        $request->justification = $justification;
        $request->requested_role = $requestedRole;
        $request->save();

        return $request;
    }
}
