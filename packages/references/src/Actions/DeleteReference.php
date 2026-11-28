<?php

declare(strict_types=1);

namespace AIArmada\References\Actions;

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\References\Models\Reference;
use Closure;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final readonly class DeleteReference
{
    public function __construct(private LockReferenceForWrite $lock = new LockReferenceForWrite) {}

    /**
     * @param  Closure(Reference): bool  $deleting
     * @param  Closure(Reference): mixed  $deleted
     */
    public function handle(Reference $reference, Closure $deleting, Closure $deleted): ?bool
    {
        if (! $reference->exists) {
            return null;
        }

        return $reference->getConnection()->transaction(function () use ($reference, $deleting, $deleted): bool {
            $this->lock->handle($reference);

            if (! $deleting($reference)) {
                return false;
            }

            $seen = [(string) $reference->getKey() => true];
            $frontier = array_keys($seen);
            $descendants = [];

            while ($frontier !== []) {
                $next = [];

                foreach (array_chunk($frontier, 500) as $ids) {
                    $children = $reference->newQuery()->withoutOwnerScope()
                        ->whereIn('parent_id', $ids)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    foreach ($children as $child) {
                        $id = (string) $child->getKey();

                        if (isset($seen[$id])) {
                            throw new RuntimeException('Cannot delete a cyclic reference hierarchy.');
                        }

                        $seen[$id] = true;
                        $next[] = $id;
                        $descendants[] = $child;
                    }
                }

                $frontier = $next;
            }

            foreach (array_reverse($descendants) as $child) {
                $owner = OwnerContext::fromTypeAndIdOrFail($child->owner_type, $child->owner_id);

                OwnerContext::withOwner($owner, function () use ($child, $deleting, $deleted): void {
                    if (! $deleting($child)) {
                        throw new RuntimeException('Reference descendant deletion was cancelled.');
                    }

                    $this->deleteRow($child, $deleted);
                });
            }

            $this->deleteRow($reference, $deleted);

            return true;
        });
    }

    /**
     * @param  Closure(Reference): mixed  $deleted
     */
    private function deleteRow(Reference $reference, Closure $deleted): void
    {
        $media = new Media;
        $media->setConnection($reference->getConnectionName());
        $media->newQuery()
            ->where('model_type', $reference->getMorphClass())
            ->where('model_id', $reference->getKey())
            ->chunkById(100, static function ($items): void {
                $items->each(static fn (Media $item): ?bool => $item->delete());
            });

        $reference->contributors()->delete();

        if ($reference->newQueryWithoutScopes()->whereKey($reference->getKey())->delete() !== 1) {
            throw new RuntimeException('Reference deletion did not remove exactly one row.');
        }

        $reference->exists = false;
        $deleted($reference);
    }
}
