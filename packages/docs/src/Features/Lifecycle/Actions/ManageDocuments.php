<?php

declare(strict_types=1);

namespace AIArmada\Docs\Features\Lifecycle\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use AIArmada\Docs\Contracts\DocumentLifecycle;
use AIArmada\Docs\Models\Doc;
use AIArmada\Docs\States\Cancelled;
use AIArmada\Docs\States\DocStatus;
use AIArmada\Docs\States\Draft;
use AIArmada\Docs\States\Overdue;
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\Pending;
use AIArmada\Docs\States\Refunded;
use AIArmada\Docs\States\Sent;
use Carbon\CarbonImmutable;
use LogicException;

final readonly class ManageDocuments implements DocumentLifecycle
{
    public function changeStatus(string $docId, string $status, ?string $notes = null): bool
    {
        return (new Doc)->getConnection()->transaction(function () use ($docId, $status, $notes): bool {
            $doc = $this->lockDocument($docId);
            $target = DocStatus::resolveStateClassFor($status, $doc);

            if ($doc->status->equals($target)) {
                return false;
            }

            return OwnerContext::withOwner(OwnerContext::fromTypeAndIdOrFail($doc->owner_type, $doc->owner_id), function () use ($doc, $target, $notes): bool {
                $this->transition($doc, $target, $notes);

                return $doc->status->equals($target);
            });
        });
    }

    public function delete(array $docIds): void
    {
        $docIds = array_values(array_unique($docIds));
        sort($docIds, SORT_STRING);

        (new Doc)->getConnection()->transaction(function () use ($docIds): void {
            foreach ($docIds as $docId) {
                $doc = $this->lockDocument($docId);
                OwnerContext::withOwner(OwnerContext::fromTypeAndIdOrFail($doc->owner_type, $doc->owner_id), function () use ($doc): void {
                    if ($doc->isPaid() || $doc->paid_at !== null || $doc->payments()->withoutOwnerScope()->exists() || $doc->eInvoiceSubmission()->withoutOwnerScope()->exists()) {
                        throw new LogicException('Documents with payment or e-invoice evidence cannot be deleted.');
                    }

                    if ($doc->delete() !== true) {
                        throw new LogicException('Document deletion was rejected.');
                    }
                });
            }
        });
    }

    public function markSent(array $docIds): array
    {
        $docIds = array_values(array_unique($docIds));
        sort($docIds, SORT_STRING);

        return (new Doc)->getConnection()->transaction(function () use ($docIds): array {
            $marked = 0;
            $skipped = 0;

            foreach ($docIds as $docId) {
                $doc = $this->lockDocument($docId);

                if (! $doc->status->equals(Draft::class) && ! $doc->status->equals(Pending::class)) {
                    $skipped++;
                    continue;
                }

                OwnerContext::withOwner(OwnerContext::fromTypeAndIdOrFail($doc->owner_type, $doc->owner_id), fn () => $this->transition($doc, Sent::class, null));
                $marked++;
            }

            return ['marked' => $marked, 'skipped' => $skipped];
        });
    }

    /** @param class-string<DocStatus> $target */
    private function transition(Doc $doc, string $target, ?string $notes): void
    {
        $oldStatus = $doc->status;
        $doc->status->transitionTo($target);

        $timestamp = match ($target) {
            Sent::class => 'sent_at',
            Paid::class => 'paid_at',
            Cancelled::class => 'cancelled_at',
            Overdue::class => 'overdue_at',
            Refunded::class => 'refunded_at',
            default => null,
        };

        if ($timestamp !== null && $doc->{$timestamp} === null) {
            $doc->update([$timestamp => CarbonImmutable::now()]);
        }

        $doc->statusHistories()->create([
            'status' => $target,
            'notes' => $notes ?? "Status changed from {$oldStatus->label()} to " . DocStatus::labelFor($target, $doc),
            'created_at' => CarbonImmutable::now(),
        ]);
    }

    private function lockDocument(string $docId): Doc
    {
        if (config('docs.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(Doc::class, $docId);
        }

        return Doc::query()->forOwner()->lockForUpdate()->findOrFail($docId);
    }
}
