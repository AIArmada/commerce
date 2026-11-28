<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Governance\Actions;

use AIArmada\Events\Contracts\EventApprovalWorkflow;
use AIArmada\Events\Contracts\EventModerationWorkflow;
use AIArmada\Events\Features\Governance\Models\EventApprovalRequest;
use AIArmada\Events\Features\Governance\Models\EventSubmission;
use AIArmada\Events\Platform\EventWriteGuard;
use AIArmada\Events\Platform\ModelResolver;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class DefaultEventApprovalWorkflow implements EventApprovalWorkflow
{
    public function __construct(private EventModerationWorkflow $moderation) {}

    public function approve(string $requestId, mixed $actor, ?string $notes = null): void
    {
        $this->mutate($requestId, $actor, function (EventApprovalRequest $request, EventSubmission $submission) use ($actor, $notes): void {
            $request->update([
                'status' => 'approved',
                'approved_at' => CarbonImmutable::now(),
                'notes' => $notes,
            ]);

            $this->moderation->approve((string) $submission->getKey(), $actor, $notes);
        });
    }

    public function reject(string $requestId, mixed $actor, string $reason, ?string $notes = null): void
    {
        if (mb_trim($reason) === '') {
            throw new InvalidArgumentException('A rejection reason is required.');
        }

        $this->mutate($requestId, $actor, function (EventApprovalRequest $request, EventSubmission $submission) use ($actor, $reason, $notes): void {
            $request->update([
                'status' => 'rejected',
                'rejected_at' => CarbonImmutable::now(),
                'reason' => $reason,
                'notes' => $notes,
            ]);

            $this->moderation->reject((string) $submission->getKey(), $actor, $reason, $notes);
        });
    }

    public function assignToActor(string $requestId, mixed $actor): void
    {
        if (! $actor instanceof Model) {
            throw new AuthorizationException('Assignment requires an authenticated user model.');
        }

        $this->mutate($requestId, $actor, function (EventApprovalRequest $request) use ($actor): void {
            $request->update([
                'assigned_to_type' => $actor->getMorphClass(),
                'assigned_to_id' => $actor->getKey(),
            ]);
        });
    }

    private function mutate(string $requestId, mixed $actor, callable $operation): void
    {
        if (! $actor instanceof Authenticatable) {
            throw new AuthorizationException('Approval requests require an authenticated actor.');
        }

        if (! Str::isUuid($requestId)) {
            throw new InvalidArgumentException('A valid approval request ID is required.');
        }

        DB::transaction(function () use ($requestId, $actor, $operation): void {
            $requestClass = ModelResolver::approvalRequestClass();
            $request = $requestClass::query()->whereKey($requestId)->lockForUpdate()->firstOrFail();

            if (! $request instanceof EventApprovalRequest || $request->status !== 'pending') {
                throw new InvalidArgumentException('Only pending event approval requests can be changed.');
            }

            $submission = $request->approvable()->lockForUpdate()->first();

            if (! $submission instanceof EventSubmission || $submission->event_id === null) {
                throw new InvalidArgumentException('Approval requests must point to a submission attached to an event.');
            }

            EventWriteGuard::findOrFail($submission->event_id);
            Gate::forUser($actor)->authorize('update', $submission);

            $operation($request, $submission);
        });
    }
}
