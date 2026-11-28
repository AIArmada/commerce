<?php

declare(strict_types=1);

use AIArmada\Docs\Contracts\DocumentApprovals;
use AIArmada\Docs\Enums\DocApprovalStatus;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use AIArmada\Docs\Models\DocApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('doc approval status helpers', function (): void {
    $approval = DocApproval::factory()->create(['status' => DocApprovalStatus::Pending]);
    expect($approval->isPending())->toBeTrue();
    expect($approval->isApproved())->toBeFalse();
    expect($approval->isRejected())->toBeFalse();

    app(DocumentApprovals::class)->decide((string) $approval->doc_id, (string) $approval->getKey(), '1', true, 'Looks good', true);
    $approval->refresh();
    expect($approval->isPending())->toBeFalse();
    expect($approval->isApproved())->toBeTrue();
    expect($approval->comments)->toBe('Looks good');
    expect($approval->approved_at)->not->toBeNull();

    $rejected = DocApproval::factory()->create(['status' => DocApprovalStatus::Pending]);
    app(DocumentApprovals::class)->decide((string) $rejected->doc_id, (string) $rejected->getKey(), '1', false, 'Bad', true);
    $rejected->refresh();
    expect($rejected->isRejected())->toBeTrue();
    expect($rejected->comments)->toBe('Bad');
    expect($rejected->rejected_at)->not->toBeNull();
});

test('doc approval expiry', function (): void {
    $approval = DocApproval::factory()->create([
        'expires_at' => now()->subDay(),
    ]);
    expect($approval->isExpired())->toBeTrue();

    $valid = DocApproval::factory()->create([
        'expires_at' => now()->addDay(),
    ]);
    expect($valid->isExpired())->toBeFalse();
});

test('approval decisions cannot overwrite a settled or expired request', function (): void {
    $approval = DocApproval::factory()->create(['assigned_to' => '2']);
    $action = app(DocumentApprovals::class);

    expect(fn () => $action->decide((string) $approval->doc_id, (string) $approval->getKey(), '3', true))
        ->toThrow(AccessDeniedHttpException::class);

    $action->decide((string) $approval->doc_id, (string) $approval->getKey(), '2', true);
    expect(fn () => $action->decide((string) $approval->doc_id, (string) $approval->getKey(), '2', false))
        ->toThrow(AccessDeniedHttpException::class);
    expect($approval->refresh()->isApproved())->toBeTrue();

    $expired = DocApproval::factory()->create(['assigned_to' => '2', 'expires_at' => now()->subDay()]);
    expect(fn () => $action->decide((string) $expired->doc_id, (string) $expired->getKey(), '2', true))
        ->toThrow(AccessDeniedHttpException::class);
    expect($expired->refresh()->isPending())->toBeTrue();
});
