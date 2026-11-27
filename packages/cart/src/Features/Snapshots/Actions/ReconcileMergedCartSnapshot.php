<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Snapshots\Actions;

use AIArmada\Cart\Events\CartMerged;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshotCondition;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshotItem;
use AIArmada\Cart\Features\Snapshots\Services\CartSyncManager;
use AIArmada\Cart\Runtime\CartOwnerIdentity;
use AIArmada\CommerceSupport\Support\OwnerContext;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;

final readonly class ReconcileMergedCartSnapshot
{
    public function __construct(private CartSyncManager $syncManager) {}

    public function execute(CartMerged $event): void
    {
        $sourceIdentifier = $event->originalSourceIdentifier ?? $event->sourceCart->getIdentifier();
        $targetIdentifier = $event->originalTargetIdentifier ?? $event->targetCart->getIdentifier();
        $instance = $event->sourceCart->instance();
        $owner = CartSnapshot::resolveCurrentOwner();
        $sourceOwner = null;

        if (CartSnapshot::ownerScopingEnabled()) {
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                CartSnapshot::class . ' requires an owner context or explicit global context.',
            );

            $storage = $event->sourceCart->storage();
            $source = new CartOwnerIdentity($storage->getOwnerType(), $storage->getOwnerId());

            if (! $source->isGlobal()) {
                if ($owner?->getMorphClass() !== $source->ownerType
                    || (string) $owner?->getKey() !== (string) $source->ownerId) {
                    throw new AuthorizationException('The merge source must belong to the current owner or be a global guest cart.');
                }

                $sourceOwner = $owner;
            }
        }

        $hasLiveTarget = $event->targetCart->storage()->getSnapshot($targetIdentifier, $instance) !== null;

        if ($hasLiveTarget) {
            $this->syncManager->sync($event->targetCart, force: true);
        }

        (new CartSnapshot)->getConnection()->transaction(function () use ($sourceIdentifier, $targetIdentifier, $instance, $sourceOwner, $owner, $hasLiveTarget): void {
            $source = $this->findSnapshot($sourceIdentifier, $instance, $sourceOwner);

            if (! $source instanceof CartSnapshot) {
                return;
            }

            $target = $this->findSnapshot($targetIdentifier, $instance, $owner);

            if ($target instanceof CartSnapshot && $source->is($target)) {
                return;
            }

            if ($target instanceof CartSnapshot) {
                if (! $hasLiveTarget) {
                    $this->transferChildren($source, $target);
                }

                $this->deleteSnapshot($source, $sourceOwner);

                return;
            }

            if ($hasLiveTarget) {
                return;
            }

            if ($sourceOwner === $owner) {
                $source->identifier = $targetIdentifier;
                $this->withOwnerContext($sourceOwner, fn (): bool => $source->save());

                return;
            }

            $target = $this->createOwnedTarget($source, $owner, $targetIdentifier);
            $this->transferChildren($source, $target);
            $this->deleteSnapshot($source, $sourceOwner);
        });
    }

    private function findSnapshot(string $identifier, string $instance, ?Model $owner): ?CartSnapshot
    {
        return $this->withOwnerContext($owner, fn (): ?CartSnapshot => CartSnapshot::query()
            ->forOwner($owner, false)
            ->where('identifier', $identifier)
            ->where('instance', $instance)
            ->lockForUpdate()
            ->first());
    }

    private function deleteSnapshot(CartSnapshot $source, ?Model $sourceOwner): void
    {
        $this->withOwnerContext($sourceOwner, function () use ($source): void {
            CartSnapshotCondition::query()->where('cart_id', $source->id)->delete();
            CartSnapshotItem::query()->where('cart_id', $source->id)->delete();
            $source->delete();
        });
    }

    private function transferChildren(CartSnapshot $source, CartSnapshot $target): void
    {
        $targetId = (string) $target->getKey();

        $targetItemIds = CartSnapshotItem::query()
            ->where('cart_id', $targetId)
            ->pluck('item_id')
            ->all();

        foreach (CartSnapshotItem::query()->where('cart_id', $source->id)->get() as $sourceItem) {
            if (in_array($sourceItem->item_id, $targetItemIds, true)) {
                CartSnapshotCondition::query()->where('cart_item_id', $sourceItem->id)->delete();
                $sourceItem->delete();

                continue;
            }

            $sourceItem->cart_id = $targetId;
            $sourceItem->save();
        }

        $targetConditionKeys = CartSnapshotCondition::query()
            ->where('cart_id', $targetId)
            ->get()
            ->mapWithKeys(fn (CartSnapshotCondition $condition): array => [
                $this->conditionKey($condition) => true,
            ]);

        foreach (CartSnapshotCondition::query()->where('cart_id', $source->id)->get() as $sourceCondition) {
            $key = $this->conditionKey($sourceCondition);

            if ($targetConditionKeys->has($key)) {
                $sourceCondition->delete();

                continue;
            }

            $sourceCondition->cart_id = $targetId;
            $sourceCondition->save();
        }
    }

    private function conditionKey(CartSnapshotCondition $condition): string
    {
        if ($condition->cart_item_id === null) {
            return 'cart:' . $condition->name;
        }

        return 'item:' . ($condition->item_id ?? '') . ':' . $condition->name;
    }

    private function createOwnedTarget(CartSnapshot $source, ?Model $owner, string $targetIdentifier): CartSnapshot
    {
        return $this->withOwnerContext($owner, function () use ($source, $owner, $targetIdentifier): CartSnapshot {
            $target = $source->replicate();
            $config = CartSnapshot::ownerScopeConfig();
            $target->setAttribute($config->ownerTypeColumn, null);
            $target->setAttribute($config->ownerIdColumn, null);
            $target->identifier = $targetIdentifier;

            if ($owner !== null) {
                $target->assignOwner($owner);
            }

            $target->save();

            return $target;
        });
    }

    private function withOwnerContext(?Model $owner, Closure $callback): mixed
    {
        if (! CartSnapshot::ownerScopingEnabled()) {
            return $callback();
        }

        return OwnerContext::withOwner($owner, $callback);
    }
}
