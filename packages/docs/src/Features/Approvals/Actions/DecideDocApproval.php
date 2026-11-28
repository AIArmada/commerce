<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Approvals\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Contracts\DocumentApprovals;
use AIArmada\Docs\Enums\DocApprovalStatus;
use AIArmada\Docs\Models\Doc;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class DecideDocApproval implements DocumentApprovals
{
    public function decide(string $docId, string $approvalId, string $actorId, bool $approve, ?string $comments = null, bool $canDecideUnassigned = false): void
    {
        if (mb_trim($actorId) === '') {
            throw new AccessDeniedHttpException;
        }

        if ($comments !== null && mb_strlen($comments) > 1000) {
            throw ValidationException::withMessages(['comments' => 'Comments must not exceed 1000 characters.']);
        }

        (new Doc)->getConnection()->transaction(function () use ($docId, $approvalId, $actorId, $approve, $comments, $canDecideUnassigned): void {
            if (config('docs.owner.enabled', false)) {
                OwnerWriteGuard::findOrFailForOwner(Doc::class, $docId);
            }

            $doc = Doc::query()->forOwner()->lockForUpdate()->findOrFail($docId);

            OwnerContext::withOwner(OwnerContext::fromTypeAndIdOrFail($doc->owner_type, $doc->owner_id), function () use ($doc, $approvalId, $actorId, $approve, $comments, $canDecideUnassigned): void {
                $approval = $doc->approvals()->lockForUpdate()->findOrFail($approvalId);

                if ($approval->owner_type !== $doc->owner_type || (string) $approval->owner_id !== (string) $doc->owner_id) {
                    throw new NotFoundHttpException('Approval not found.');
                }

                if ($approval->assigned_to === null ? ! $canDecideUnassigned : (string) $approval->assigned_to !== $actorId) {
                    throw new AccessDeniedHttpException;
                }

                if (! $approval->isPending() || $approval->isExpired()) {
                    throw new AccessDeniedHttpException('Approval is no longer pending or has expired.');
                }

                $approval->update([
                    'status' => $approve ? DocApprovalStatus::Approved : DocApprovalStatus::Rejected,
                    'approved_at' => $approve ? CarbonImmutable::now() : null,
                    'rejected_at' => $approve ? null : CarbonImmutable::now(),
                    'comments' => $comments,
                ]);
            });
        });
    }
}
