<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Approvals\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Contracts\DocumentWorkflows;
use AIArmada\Docs\Data\DocType;
use AIArmada\Docs\Features\Approvals\Models\DocWorkflow;
use AIArmada\Docs\Features\Approvals\Models\DocWorkflowStep;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use LogicException;

final readonly class ManageDocWorkflows implements DocumentWorkflows
{
    public function create(array $data): string
    {
        $attributes = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'doc_type' => ['sometimes', 'nullable', Rule::enum(DocType::class)],
            'is_active' => ['sometimes', 'boolean'],
            'rules' => ['sometimes', 'nullable', 'array'],
            'priority' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
        ])->validate();

        return (new DocWorkflow)->getConnection()->transaction(
            static fn (): string => (string) DocWorkflow::query()->create($attributes)->getKey(),
        );
    }

    public function addStep(string $workflowId, array $data): string
    {
        $attributes = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'order' => ['sometimes', 'integer', 'min:0', 'max:2147483647'],
            'action_type' => ['required', Rule::in([
                DocWorkflowStep::ACTION_APPROVAL,
                DocWorkflowStep::ACTION_NOTIFICATION,
                DocWorkflowStep::ACTION_STATUS_CHANGE,
                DocWorkflowStep::ACTION_WEBHOOK,
            ])],
            'action_config' => ['sometimes', 'nullable', 'array'],
            'conditions' => ['sometimes', 'nullable', 'array'],
            'is_required' => ['sometimes', 'boolean'],
            'timeout_hours' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:2147483647'],
        ])->validate();

        return (new DocWorkflow)->getConnection()->transaction(function () use ($workflowId, $attributes): string {
            $workflow = $this->lockWorkflow($workflowId);
            $owner = OwnerContext::fromTypeAndIdOrFail($workflow->owner_type, $workflow->owner_id);

            return OwnerContext::withOwner($owner, static function () use ($workflow, $attributes): string {
                $step = $workflow->steps()->make($attributes);
                $step->forceFill([
                    'owner_type' => $workflow->owner_type,
                    'owner_id' => $workflow->owner_id,
                ]);
                $step->save();

                return (string) $step->getKey();
            });
        });
    }

    public function remove(string $workflowId): void
    {
        (new DocWorkflow)->getConnection()->transaction(function () use ($workflowId): void {
            $workflow = $this->lockWorkflow($workflowId);

            if ($workflow->delete() !== true) {
                throw new LogicException('Document workflow deletion was rejected.');
            }
        });
    }

    private function lockWorkflow(string $workflowId): DocWorkflow
    {
        if (config('docs.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(DocWorkflow::class, $workflowId);
        }

        return DocWorkflow::query()->forOwner()->lockForUpdate()->findOrFail($workflowId);
    }
}
