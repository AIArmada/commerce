<?php

declare(strict_types=1);

use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocWorkflow;
use AIArmada\Docs\Models\DocWorkflowStep;

beforeEach(function (): void {
    DocWorkflow::query()->delete();
    DocWorkflowStep::query()->delete();
});

describe('DocWorkflow', function (): void {
    test('it can create workflow', function (): void {
        $workflow = DocWorkflow::create([
            'name' => 'Invoice Approval',
            'doc_type' => DocType::Invoice,
            'is_active' => true,
            'priority' => 10,
        ]);

        expect($workflow)
            ->toBeInstanceOf(DocWorkflow::class)
            ->and($workflow->name)->toBe('Invoice Approval')
            ->and($workflow->doc_type)->toBe(DocType::Invoice)
            ->and($workflow->is_active)->toBeTrue();
    });

    test('workflow applies to matching doc type', function (): void {
        $workflow = DocWorkflow::create([
            'name' => 'Invoice Workflow',
            'doc_type' => DocType::Invoice,
            'is_active' => true,
        ]);

        $invoiceDoc = Doc::factory()->create(['doc_type' => DocType::Invoice->value]);
        $quotationDoc = Doc::factory()->create(['doc_type' => DocType::Quotation->value]);

        expect($workflow->appliesTo($invoiceDoc))->toBeTrue()
            ->and($workflow->appliesTo($quotationDoc))->toBeFalse();
    });

    test('workflow with null doc_type applies to all', function (): void {
        $workflow = DocWorkflow::create([
            'name' => 'Universal Workflow',
            'doc_type' => null,
            'is_active' => true,
        ]);

        $invoiceDoc = Doc::factory()->create(['doc_type' => DocType::Invoice->value]);
        $quotationDoc = Doc::factory()->create(['doc_type' => DocType::Quotation->value]);

        expect($workflow->appliesTo($invoiceDoc))->toBeTrue()
            ->and($workflow->appliesTo($quotationDoc))->toBeTrue();
    });

    test('inactive workflow does not apply', function (): void {
        $workflow = DocWorkflow::create([
            'name' => 'Inactive Workflow',
            'doc_type' => DocType::Invoice,
            'is_active' => false,
        ]);

        $doc = Doc::factory()->create(['doc_type' => DocType::Invoice->value]);

        expect($workflow->appliesTo($doc))->toBeFalse();
    });

    test('workflow can have rules', function (): void {
        $workflow = DocWorkflow::create([
            'name' => 'High Value Approval',
            'doc_type' => DocType::Invoice,
            'is_active' => true,
            'rules' => [
                'total' => ['operator' => '>', 'value' => 1000],
            ],
        ]);

        $lowValueDoc = Doc::factory()->create([
            'doc_type' => DocType::Invoice->value,
            'total' => 500,
        ]);

        $highValueDoc = Doc::factory()->create([
            'doc_type' => DocType::Invoice->value,
            'total' => 2000,
        ]);

        expect($workflow->appliesTo($lowValueDoc))->toBeFalse()
            ->and($workflow->appliesTo($highValueDoc))->toBeTrue();
    });

    test('workflow rules support multiple operators', function (): void {
        $workflow = DocWorkflow::create([
            'name' => 'Complex Rules',
            'doc_type' => null,
            'is_active' => true,
            'rules' => [
                'currency' => ['operator' => 'in', 'value' => ['USD', 'EUR']],
            ],
        ]);

        $usdDoc = Doc::factory()->create(['currency' => 'USD']);
        $myrDoc = Doc::factory()->create(['currency' => 'MYR']);

        expect($workflow->appliesTo($usdDoc))->toBeTrue()
            ->and($workflow->appliesTo($myrDoc))->toBeFalse();
    });

    test('workflow can have steps', function (): void {
        $workflow = DocWorkflow::create([
            'name' => 'Multi-Step Approval',
            'is_active' => true,
        ]);

        DocWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'name' => 'Manager Approval',
            'order' => 1,
            'action_type' => DocWorkflowStep::ACTION_APPROVAL,
            'is_required' => true,
        ]);

        DocWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'name' => 'Finance Approval',
            'order' => 2,
            'action_type' => DocWorkflowStep::ACTION_APPROVAL,
            'is_required' => true,
        ]);

        $workflow->refresh();

        expect($workflow->steps)->toHaveCount(2)
            ->and($workflow->steps->first()->name)->toBe('Manager Approval');
    });

    test('workflow cascade deletes steps', function (): void {
        $workflow = DocWorkflow::create([
            'name' => 'Delete Test',
            'is_active' => true,
        ]);

        DocWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'name' => 'Step 1',
            'order' => 1,
            'action_type' => DocWorkflowStep::ACTION_APPROVAL,
        ]);

        $workflowId = $workflow->id;
        $workflow->delete();

        expect(DocWorkflowStep::where('workflow_id', $workflowId)->count())->toBe(0);
    });
});

describe('DocWorkflowStep', function (): void {
    test('it can create workflow step', function (): void {
        $workflow = DocWorkflow::create([
            'name' => 'Test Workflow',
            'is_active' => true,
        ]);

        $step = DocWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'name' => 'Manager Review',
            'order' => 1,
            'action_type' => DocWorkflowStep::ACTION_APPROVAL,
            'action_config' => [
                'approvers' => ['manager-1', 'manager-2'],
            ],
            'is_required' => true,
            'timeout_hours' => 48,
        ]);

        expect($step)
            ->toBeInstanceOf(DocWorkflowStep::class)
            ->and($step->name)->toBe('Manager Review')
            ->and($step->action_type)->toBe(DocWorkflowStep::ACTION_APPROVAL)
            ->and($step->timeout_hours)->toBe(48);
    });

    test('step can be approval type', function (): void {
        $workflow = DocWorkflow::create(['name' => 'Test', 'is_active' => true]);

        $step = DocWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'name' => 'Approval Step',
            'order' => 1,
            'action_type' => DocWorkflowStep::ACTION_APPROVAL,
        ]);

        expect($step->requiresApproval())->toBeTrue()
            ->and($step->sendsNotification())->toBeFalse();
    });

    test('step can be notification type', function (): void {
        $workflow = DocWorkflow::create(['name' => 'Test', 'is_active' => true]);

        $step = DocWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'name' => 'Notify Step',
            'order' => 1,
            'action_type' => DocWorkflowStep::ACTION_NOTIFICATION,
        ]);

        expect($step->sendsNotification())->toBeTrue()
            ->and($step->requiresApproval())->toBeFalse();
    });

    test('step can get approvers from config', function (): void {
        $workflow = DocWorkflow::create(['name' => 'Test', 'is_active' => true]);

        $step = DocWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'name' => 'Approval',
            'order' => 1,
            'action_type' => DocWorkflowStep::ACTION_APPROVAL,
            'action_config' => [
                'approvers' => ['user-1', 'user-2', 'user-3'],
            ],
        ]);

        $approvers = $step->getApprovers();

        expect($approvers)->toHaveCount(3)
            ->and($approvers)->toContain('user-1', 'user-2', 'user-3');
    });

    test('step can have conditions', function (): void {
        $workflow = DocWorkflow::create(['name' => 'Test', 'is_active' => true]);

        $step = DocWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'name' => 'Conditional',
            'order' => 1,
            'action_type' => DocWorkflowStep::ACTION_APPROVAL,
            'conditions' => [
                'total' => ['operator' => '>=', 'value' => 5000],
            ],
        ]);

        $lowValueDoc = Doc::factory()->create(['total' => 1000]);
        $highValueDoc = Doc::factory()->create(['total' => 10000]);

        expect($step->conditionsMet($lowValueDoc))->toBeFalse()
            ->and($step->conditionsMet($highValueDoc))->toBeTrue();
    });

    test('step without conditions always applies', function (): void {
        $workflow = DocWorkflow::create(['name' => 'Test', 'is_active' => true]);

        $step = DocWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'name' => 'No Conditions',
            'order' => 1,
            'action_type' => DocWorkflowStep::ACTION_NOTIFICATION,
            'conditions' => null,
        ]);

        $doc = Doc::factory()->create();

        expect($step->conditionsMet($doc))->toBeTrue();
    });

    test('steps are ordered by order field', function (): void {
        $workflow = DocWorkflow::create(['name' => 'Test', 'is_active' => true]);

        DocWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'name' => 'Third',
            'order' => 3,
            'action_type' => DocWorkflowStep::ACTION_APPROVAL,
        ]);

        DocWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'name' => 'First',
            'order' => 1,
            'action_type' => DocWorkflowStep::ACTION_APPROVAL,
        ]);

        DocWorkflowStep::create([
            'workflow_id' => $workflow->id,
            'name' => 'Second',
            'order' => 2,
            'action_type' => DocWorkflowStep::ACTION_APPROVAL,
        ]);

        $workflow->refresh();
        $steps = $workflow->steps;

        expect($steps->first()->name)->toBe('First')
            ->and($steps->get(1)->name)->toBe('Second')
            ->and($steps->last()->name)->toBe('Third');
    });
});
