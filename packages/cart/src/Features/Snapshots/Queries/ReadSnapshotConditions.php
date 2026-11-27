<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Snapshots\Queries;

use AIArmada\Cart\Data\CartSnapshotData;
use AIArmada\Cart\Features\Snapshots\Api\SnapshotConditionData;
use AIArmada\Cart\Features\Snapshots\Api\SnapshotConditionLookup;
use AIArmada\Cart\Features\Snapshots\Api\SnapshotConditionMatch;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshotCondition;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\CommerceSupport\Support\OwnerScope;
use AIArmada\CommerceSupport\Support\OwnerWriteGuard;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

final readonly class ReadSnapshotConditions implements SnapshotConditionLookup
{
    public function requireOwnedSnapshot(string $snapshotId): CartSnapshotData
    {
        if (! CartSnapshot::ownerScopingEnabled()) {
            return CartSnapshot::query()->findOrFail($snapshotId)->toSnapshotData();
        }

        /** @var CartSnapshot $snapshot */
        $snapshot = OwnerWriteGuard::findOrFailForOwner(
            CartSnapshot::class,
            $snapshotId,
            includeGlobal: false,
            message: 'Cart is not accessible in the current owner scope.',
        );

        return $snapshot->toSnapshotData();
    }

    public function requireOwnedCondition(string $snapshotConditionId): SnapshotConditionMatch
    {
        $condition = CartSnapshotCondition::query()->findOrFail($snapshotConditionId);

        return new SnapshotConditionMatch(
            $this->requireOwnedSnapshot($condition->cart_id),
            [$this->conditionData($condition)],
        );
    }

    /** @return iterable<SnapshotConditionMatch> */
    public function matchingStoredCondition(string $conditionId, bool $allOwners = false): iterable
    {
        return $this->matching(
            CartSnapshotCondition::query()->whereJsonContains('attributes', ['condition_id' => $conditionId]),
            $allOwners,
        );
    }

    /** @return iterable<SnapshotConditionMatch> */
    public function matchingNamedCondition(string $name): iterable
    {
        return $this->matching(CartSnapshotCondition::query()->where('name', $name), false);
    }

    /**
     * @param  Builder<CartSnapshotCondition>  $matchingConditions
     * @return iterable<SnapshotConditionMatch>
     */
    private function matching(Builder $matchingConditions, bool $allOwners): iterable
    {
        $snapshots = $this->snapshotQuery($allOwners);

        $snapshots->whereIn('id', (clone $matchingConditions)->select('cart_id'));

        foreach ($snapshots->lazyById(200)->chunk(200) as $batch) {
            $conditions = (clone $matchingConditions)
                ->whereIn('cart_id', $batch->pluck('id')->all())
                ->get()
                ->groupBy('cart_id');

            foreach ($batch as $snapshot) {
                $matched = $conditions->get($snapshot->id);

                if ($matched === null || $matched->isEmpty()) {
                    continue;
                }

                yield new SnapshotConditionMatch(
                    $snapshot->toSnapshotData(),
                    $matched->map(fn (CartSnapshotCondition $condition): SnapshotConditionData => $this->conditionData($condition))
                        ->values()
                        ->all(),
                );
            }
        }
    }

    /** @return Builder<CartSnapshot> */
    private function snapshotQuery(bool $allOwners): Builder
    {
        if ($allOwners && CartSnapshot::ownerScopingEnabled()) {
            if (! OwnerContext::isExplicitGlobal()) {
                throw new RuntimeException('Removing shared global conditions from all carts requires explicit global owner context.');
            }

            return CartSnapshot::query()->withoutGlobalScope(OwnerScope::class);
        }

        return CartSnapshot::query()->forOwner();
    }

    private function conditionData(CartSnapshotCondition $condition): SnapshotConditionData
    {
        return new SnapshotConditionData(
            name: $condition->name,
            itemId: $condition->item_id,
            cartLevel: $condition->isCartLevel(),
            conditionId: $condition->attributes['condition_id'] ?? null,
        );
    }
}
