<?php

declare(strict_types=1);

use AIArmada\Docs\Contracts\DocumentApprovalRequests;
use AIArmada\Docs\Enums\DocApprovalStatus;
use AIArmada\Docs\Features\Approvals\Models\DocApproval;
use AIArmada\Docs\Models\Doc;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

it('creates pending approval requests and removes them through the domain contract', function (): void {
    $doc = Doc::factory()->create();
    $requests = app(DocumentApprovalRequests::class);

    $id = $requests->request((string) $doc->getKey(), '1', '2', now()->addDay(), 'Review');

    $approval = $doc->approvals()->findOrFail($id);
    expect($approval->isPending())->toBeTrue()
        ->and((string) $approval->requested_by)->toBe('1')
        ->and((string) $approval->assigned_to)->toBe('2');

    $requests->remove((string) $doc->getKey(), [$id]);
    expect($doc->approvals()->whereKey($id)->exists())->toBeFalse();
});

it('rejects expired requests and preserves approval evidence atomically', function (): void {
    $doc = Doc::factory()->create();
    $requests = app(DocumentApprovalRequests::class);

    expect(fn () => $requests->request((string) $doc->getKey(), '1', expiresAt: now()->subDay()))
        ->toThrow(ValidationException::class);

    $pending = $requests->request((string) $doc->getKey(), '1');
    $settled = DocApproval::factory()->create(['doc_id' => $doc->getKey(), 'status' => DocApprovalStatus::Approved]);

    expect(fn () => $requests->remove((string) $doc->getKey(), [$pending, (string) $settled->getKey()]))
        ->toThrow(ValidationException::class);

    expect($doc->approvals()->whereKey($pending)->exists())->toBeTrue()
        ->and($settled->refresh()->isApproved())->toBeTrue();
});

it('rejects approval removal from another document', function (): void {
    $doc = Doc::factory()->create();
    $other = Doc::factory()->create();
    $requests = app(DocumentApprovalRequests::class);
    $id = $requests->request((string) $other->getKey(), '1');

    expect(fn () => $requests->remove((string) $doc->getKey(), [$id]))
        ->toThrow(ModelNotFoundException::class);

    expect($other->approvals()->whereKey($id)->exists())->toBeTrue();
});
