<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Approvals\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Contracts\DocumentApprovalRequests;
use AIArmada\Docs\Enums\DocApprovalStatus;
use AIArmada\Docs\Models\Doc;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final readonly class ManageDocApprovalRequests implements DocumentApprovalRequests
{
    public function request(string $docId, string $actorId, ?string $assigneeId = null, DateTimeInterface | string | null $expiresAt = null, ?string $comments = null): string
    {
        if (mb_trim($actorId) === '') {
            throw new AccessDeniedHttpException;
        }

        if ($assigneeId !== null && mb_trim($assigneeId) === '') {
            throw ValidationException::withMessages(['assigned_to' => 'Invalid assignee selection.']);
        }

        if ($comments !== null && mb_strlen($comments) > 1000) {
            throw ValidationException::withMessages(['comments' => 'Comments must not exceed 1000 characters.']);
        }

        try {
            $expiry = match (true) {
                $expiresAt instanceof DateTimeInterface => CarbonImmutable::instance($expiresAt),
                is_string($expiresAt) && mb_trim($expiresAt) !== '' => CarbonImmutable::parse($expiresAt),
                default => null,
            };
        } catch (Throwable) {
            throw ValidationException::withMessages(['expires_at' => 'A valid expiry date is required.']);
        }

        if ($expiry !== null && ! $expiry->isFuture()) {
            throw ValidationException::withMessages(['expires_at' => 'Approval expiry must be in the future.']);
        }

        return (new Doc)->getConnection()->transaction(function () use ($docId, $actorId, $assigneeId, $expiry, $comments): string {
            $doc = $this->lockDocument($docId);

            return OwnerContext::withOwner(OwnerContext::fromTypeAndIdOrFail($doc->owner_type, $doc->owner_id), function () use ($doc, $actorId, $assigneeId, $expiry, $comments): string {
                $approval = $doc->approvals()->create([
                    'requested_by' => $actorId,
                    'assigned_to' => $assigneeId,
                    'expires_at' => $expiry,
                    'comments' => $comments,
                    'status' => DocApprovalStatus::Pending,
                ]);

                return (string) $approval->getKey();
            });
        });
    }

    public function remove(string $docId, array $approvalIds): void
    {
        $approvalIds = array_values(array_unique($approvalIds));
        sort($approvalIds, SORT_STRING);

        (new Doc)->getConnection()->transaction(function () use ($docId, $approvalIds): void {
            $doc = $this->lockDocument($docId);

            OwnerContext::withOwner(OwnerContext::fromTypeAndIdOrFail($doc->owner_type, $doc->owner_id), function () use ($doc, $approvalIds): void {
                foreach ($approvalIds as $approvalId) {
                    $approval = $doc->approvals()->lockForUpdate()->findOrFail($approvalId);

                    if ($approval->owner_type !== $doc->owner_type || (string) $approval->owner_id !== (string) $doc->owner_id) {
                        throw new NotFoundHttpException('Approval not found.');
                    }

                    if (! $approval->isPending()) {
                        throw ValidationException::withMessages(['approvals' => 'Decided approvals cannot be deleted.']);
                    }

                    $approval->delete();
                }
            });
        });
    }

    private function lockDocument(string $docId): Doc
    {
        if (config('docs.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(Doc::class, $docId);
        }

        return Doc::query()->forOwner()->lockForUpdate()->findOrFail($docId);
    }
}
