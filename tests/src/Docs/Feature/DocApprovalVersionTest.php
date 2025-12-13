<?php

declare(strict_types=1);

use AIArmada\Docs\Enums\DocStatus;
use AIArmada\Docs\Enums\DocType;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\Models\DocApproval;
use AIArmada\Docs\Models\DocVersion;

beforeEach(function (): void {
    Doc::query()->delete();
    DocApproval::query()->delete();
    DocVersion::query()->delete();
});

describe('DocApproval', function (): void {
    test('it can create approval request', function (): void {
        $doc = Doc::factory()->create([
            'doc_type' => DocType::Invoice->value,
            'status' => DocStatus::DRAFT,
        ]);

        $approval = DocApproval::create([
            'doc_id' => $doc->id,
            'requested_by' => 'user-1',
            'assigned_to' => 'user-2',
            'status' => 'pending',
        ]);

        expect($approval)
            ->toBeInstanceOf(DocApproval::class)
            ->and($approval->doc_id)->toBe($doc->id)
            ->and($approval->status)->toBe('pending');
    });

    test('it can approve document', function (): void {
        $doc = Doc::factory()->create();

        $approval = DocApproval::create([
            'doc_id' => $doc->id,
            'requested_by' => 'user-1',
            'assigned_to' => 'user-2',
            'status' => 'pending',
        ]);

        $approval->update([
            'status' => 'approved',
            'approved_at' => now(),
            'comments' => 'Looks good',
        ]);

        expect($approval->status)->toBe('approved')
            ->and($approval->approved_at)->not->toBeNull()
            ->and($approval->comments)->toBe('Looks good');
    });

    test('it can reject document', function (): void {
        $doc = Doc::factory()->create();

        $approval = DocApproval::create([
            'doc_id' => $doc->id,
            'requested_by' => 'user-1',
            'assigned_to' => 'user-2',
            'status' => 'pending',
        ]);

        $approval->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'comments' => 'Please revise the amounts',
        ]);

        expect($approval->status)->toBe('rejected')
            ->and($approval->comments)->toBe('Please revise the amounts');
    });

    test('approval has expiration', function (): void {
        $doc = Doc::factory()->create();

        $approval = DocApproval::create([
            'doc_id' => $doc->id,
            'requested_by' => 'user-1',
            'assigned_to' => 'user-2',
            'status' => 'pending',
            'expires_at' => now()->addDays(3),
        ]);

        expect($approval->expires_at)->not->toBeNull()
            ->and($approval->expires_at->isFuture())->toBeTrue();
    });

    test('doc can have multiple approvals', function (): void {
        $doc = Doc::factory()->create();

        DocApproval::create([
            'doc_id' => $doc->id,
            'requested_by' => 'user-1',
            'assigned_to' => 'user-2',
            'status' => 'approved',
        ]);

        DocApproval::create([
            'doc_id' => $doc->id,
            'requested_by' => 'user-1',
            'assigned_to' => 'user-3',
            'status' => 'pending',
        ]);

        $doc->refresh();

        expect($doc->approvals)->toHaveCount(2);
    });

    test('approval belongs to doc', function (): void {
        $doc = Doc::factory()->create([
            'doc_number' => 'INV-2024-TEST',
        ]);

        $approval = DocApproval::create([
            'doc_id' => $doc->id,
            'requested_by' => 'user-1',
            'assigned_to' => 'user-2',
            'status' => 'pending',
        ]);

        expect($approval->doc->doc_number)->toBe('INV-2024-TEST');
    });
});

describe('DocVersion', function (): void {
    test('it can create document version', function (): void {
        $doc = Doc::factory()->create([
            'items' => [['name' => 'Item 1', 'quantity' => 1, 'price' => 100]],
            'total' => 100,
        ]);

        $version = DocVersion::create([
            'doc_id' => $doc->id,
            'version_number' => 1,
            'snapshot' => [
                'items' => $doc->items,
                'total' => $doc->total,
                'status' => $doc->status->value,
            ],
            'changed_by' => 'user-1',
            'change_summary' => 'Initial version',
        ]);

        expect($version)
            ->toBeInstanceOf(DocVersion::class)
            ->and($version->version_number)->toBe(1)
            ->and($version->snapshot)->toBeArray()
            ->and($version->change_summary)->toBe('Initial version');
    });

    test('versions are incremental', function (): void {
        $doc = Doc::factory()->create();

        DocVersion::create([
            'doc_id' => $doc->id,
            'version_number' => 1,
            'snapshot' => ['status' => 'draft'],
            'changed_by' => 'user-1',
        ]);

        DocVersion::create([
            'doc_id' => $doc->id,
            'version_number' => 2,
            'snapshot' => ['status' => 'pending'],
            'changed_by' => 'user-1',
        ]);

        $doc->refresh();

        expect($doc->versions)->toHaveCount(2)
            ->and($doc->versions->first()->version_number)->toBe(1)
            ->and($doc->versions->last()->version_number)->toBe(2);
    });

    test('version stores document snapshot', function (): void {
        $doc = Doc::factory()->create([
            'items' => [
                ['name' => 'Product A', 'quantity' => 2, 'price' => 50],
                ['name' => 'Product B', 'quantity' => 1, 'price' => 100],
            ],
            'subtotal' => 200,
            'total' => 200,
        ]);

        $version = DocVersion::create([
            'doc_id' => $doc->id,
            'version_number' => 1,
            'snapshot' => [
                'items' => $doc->items,
                'subtotal' => $doc->subtotal,
                'total' => $doc->total,
            ],
            'changed_by' => 'user-1',
        ]);

        expect($version->snapshot['items'])->toHaveCount(2)
            ->and($version->snapshot['total'])->toBe('200.00');
    });

    test('version belongs to doc', function (): void {
        $doc = Doc::factory()->create([
            'doc_number' => 'QUO-2024-001',
        ]);

        $version = DocVersion::create([
            'doc_id' => $doc->id,
            'version_number' => 1,
            'snapshot' => [],
            'changed_by' => 'user-1',
        ]);

        expect($version->doc->doc_number)->toBe('QUO-2024-001');
    });

    test('doc cascade deletes versions', function (): void {
        $doc = Doc::factory()->create();

        DocVersion::create([
            'doc_id' => $doc->id,
            'version_number' => 1,
            'snapshot' => [],
            'changed_by' => 'user-1',
        ]);

        DocVersion::create([
            'doc_id' => $doc->id,
            'version_number' => 2,
            'snapshot' => [],
            'changed_by' => 'user-1',
        ]);

        $docId = $doc->id;
        $doc->delete();

        expect(DocVersion::where('doc_id', $docId)->count())->toBe(0);
    });
});
