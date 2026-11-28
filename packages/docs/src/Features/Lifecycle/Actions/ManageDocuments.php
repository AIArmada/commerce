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
use AIArmada\Docs\States\Paid;
use AIArmada\Docs\States\Pending;
use AIArmada\Docs\States\Sent;
use Illuminate\Support\Facades\DB;

final readonly class ManageDocuments implements DocumentLifecycle
{
    public function changeStatus(string $docId, string $status, ?string $notes = null): bool
    {
        return DB::transaction(function () use ($docId, $status, $notes): bool {
            $doc = $this->lockDocument($docId);
            $target = DocStatus::resolveStateClassFor($status, $doc);

            if ($doc->status->equals($target)) {
                return false;
            }

            return OwnerContext::withOwner(OwnerContext::fromTypeAndIdOrFail($doc->owner_type, $doc->owner_id), function () use ($doc, $target, $notes): bool {
                match ($target) {
                    Sent::class => $doc->markAsSent($notes),
                    Paid::class => $doc->markAsPaid($notes),
                    Cancelled::class => $doc->cancel($notes),
                    default => $doc->transitionStatusTo($target, $notes),
                };

                return $doc->status->equals($target);
            });
        });
    }

    public function delete(array $docIds): void
    {
        // Each deletion has its own transaction because storage cleanup is
        // external to the database and cannot be rolled back with other rows.
        foreach (array_unique($docIds) as $docId) {
            DB::transaction(function () use ($docId): void {
                $doc = $this->lockDocument($docId);
                OwnerContext::withOwner(OwnerContext::fromTypeAndIdOrFail($doc->owner_type, $doc->owner_id), fn () => $doc->delete());
            });
        }
    }

    public function markSent(array $docIds): array
    {
        $docIds = array_values(array_unique($docIds));
        sort($docIds, SORT_STRING);

        return DB::transaction(function () use ($docIds): array {
            $marked = 0;
            $skipped = 0;

            foreach ($docIds as $docId) {
                $doc = $this->lockDocument($docId);

                if (! $doc->status->equals(Draft::class) && ! $doc->status->equals(Pending::class)) {
                    $skipped++;
                    continue;
                }

                OwnerContext::withOwner(OwnerContext::fromTypeAndIdOrFail($doc->owner_type, $doc->owner_id), fn () => $doc->markAsSent());
                $marked++;
            }

            return ['marked' => $marked, 'skipped' => $skipped];
        });
    }

    private function lockDocument(string $docId): Doc
    {
        $owner = OwnerContext::resolve();
        $includeGlobal = (bool) config('docs.owner.include_global', false);

        if (config('docs.owner.enabled', false)) {
            OwnerWriteGuard::findOrFailForOwner(Doc::class, $docId, owner: $owner, includeGlobal: $includeGlobal);
        }

        return Doc::query()->forOwner($owner, $includeGlobal)->lockForUpdate()->findOrFail($docId);
    }
}
