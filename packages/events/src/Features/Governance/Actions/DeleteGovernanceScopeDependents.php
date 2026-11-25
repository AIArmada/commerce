<?php

declare(strict_types=1);

namespace AIArmada\Events\Features\Governance\Actions;

use AIArmada\Events\Features\Governance\Models\EventApprovalRequest;
use AIArmada\Events\Features\Governance\Models\EventEscalation;
use AIArmada\Events\Features\Governance\Models\EventManagementAssignment;
use AIArmada\Events\Features\Governance\Models\EventManagementAssignmentRequest;
use AIArmada\Events\Features\Governance\Models\EventModerationAction;
use AIArmada\Events\Features\Governance\Models\EventReport;
use AIArmada\Events\Features\Governance\Models\EventSubmission;
use AIArmada\Events\Features\Governance\Models\EventVerification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DeleteGovernanceScopeDependents
{
    public function deleteForScope(string $scopeType, string $scopeId, ?string $morphClass = null): void
    {
        if ($scopeType === 'registration') {
            $this->deleteRegistrationDependents($scopeId, $morphClass);

            return;
        }

        $column = match ($scopeType) {
            'event' => 'event_id',
            'occurrence' => 'event_occurrence_id',
            'session' => 'event_session_id',
            default => throw new InvalidArgumentException(sprintf('Unknown event scope type [%s].', $scopeType)),
        };

        DB::transaction(function () use ($scopeType, $scopeId, $column, $morphClass): void {
            if ($scopeType === 'event') {
                $this->deleteThroughModels(EventEscalation::class, 'event_id', $scopeId);
            }

            $this->deleteThroughModels(EventSubmission::class, $column, $scopeId);
            $this->deleteThroughModels(EventManagementAssignment::class, $column, $scopeId);
            $this->deleteThroughModels(EventVerification::class, $column, $scopeId);
            $this->deleteThroughModels(EventReport::class, $column, $scopeId);
            $this->deleteThroughModels(EventModerationAction::class, $column, $scopeId);

            $this->deleteMorphChildren(EventApprovalRequest::class, 'approvable', $morphClass, $scopeId);
            $this->deleteMorphChildren(EventManagementAssignment::class, 'manageable', $morphClass, $scopeId);
            $this->deleteMorphChildren(EventManagementAssignmentRequest::class, 'manageable', $morphClass, $scopeId);
            $this->deleteMorphChildren(EventVerification::class, 'verifiable', $morphClass, $scopeId);
            $this->deleteMorphChildren(EventReport::class, 'reportable', $morphClass, $scopeId);
            $this->deleteMorphChildren(EventModerationAction::class, 'actionable', $morphClass, $scopeId);
        });
    }

    private function deleteRegistrationDependents(string $scopeId, ?string $morphClass): void
    {
        if ($morphClass === null) {
            return;
        }

        DB::transaction(function () use ($scopeId, $morphClass): void {
            $this->deleteMorphChildren(EventApprovalRequest::class, 'approvable', $morphClass, $scopeId);
            $this->deleteMorphChildren(EventManagementAssignment::class, 'manageable', $morphClass, $scopeId);
            $this->deleteMorphChildren(EventManagementAssignmentRequest::class, 'manageable', $morphClass, $scopeId);
        });
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function deleteThroughModels(string $modelClass, string $column, string $scopeId): void
    {
        $modelClass::query()
            ->where($column, $scopeId)
            ->chunkById(200, function (iterable $children): void {
                foreach ($children as $child) {
                    $child->delete();
                }
            });
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function deleteMorphChildren(string $modelClass, string $prefix, ?string $morphClass, string $scopeId): void
    {
        if ($morphClass === null) {
            return;
        }

        $modelClass::query()
            ->where($prefix . '_type', $morphClass)
            ->where($prefix . '_id', $scopeId)
            ->chunkById(200, function (iterable $children): void {
                foreach ($children as $child) {
                    $child->delete();
                }
            });
    }
}
