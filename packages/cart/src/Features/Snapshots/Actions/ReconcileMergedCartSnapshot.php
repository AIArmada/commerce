<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Snapshots\Actions;

use AIArmada\Cart\Events\CartMerged;
use AIArmada\Cart\Snapshots\CartSnapshot;
use AIArmada\Cart\Snapshots\CartSnapshotCondition;
use AIArmada\Cart\Snapshots\CartSnapshotItem;
use AIArmada\Cart\Features\Snapshots\Services\CartSyncManager;
use AIArmada\CommerceSupport\Support\OwnerContext;

final readonly class ReconcileMergedCartSnapshot
{
    public function __construct(private CartSyncManager $syncManager) {}

    public function execute(CartMerged $event): void
    {
        $sourceIdentifier = $event->originalSourceIdentifier ?? $event->sourceCart->getIdentifier();
        $targetIdentifier = $event->originalTargetIdentifier ?? $event->targetCart->getIdentifier();
        $instance = $event->sourceCart->instance();
        $owner = CartSnapshot::resolveCurrentOwner();

        if (CartSnapshot::ownerScopingEnabled()) {
            OwnerContext::assertResolvedOrExplicitGlobal(
                $owner,
                CartSnapshot::class . ' requires an owner context or explicit global context.',
            );
        }

        $changed = (new CartSnapshot)->getConnection()->transaction(function () use ($sourceIdentifier, $targetIdentifier, $instance, $owner): bool {
            $source = OwnerContext::withOwner(null, fn () => CartSnapshot::query()
                ->forOwner(null, false)
                ->where('identifier', $sourceIdentifier)
                ->where('instance', $instance)
                ->lockForUpdate()
                ->first());

            if (! $source instanceof CartSnapshot) {
                return false;
            }

            $target = CartSnapshot::query()
                ->forOwner($owner, false)
                ->where('identifier', $targetIdentifier)
                ->where('instance', $instance)
                ->lockForUpdate()
                ->first();

            if ($target instanceof CartSnapshot && $source->is($target)) {
                return false;
            }

            if ($target instanceof CartSnapshot) {
                CartSnapshotItem::query()->where('cart_id', $source->id)
                    ->update(['cart_id' => $target->id]);

                CartSnapshotCondition::query()->where('cart_id', $source->id)
                    ->update(['cart_id' => $target->id]);

                OwnerContext::withOwner(null, fn (): bool => (bool) $source->delete());

                return true;
            }

            $source->identifier = $targetIdentifier;

            if (CartSnapshot::ownerScopingEnabled() && $owner !== null) {
                $source->assignOwner($owner);
            }

            $source->save();

            return true;
        });

        if ($changed && (
            $event->targetCart->countItems() > 0
            || $event->targetCart->getStoredConditions()->isNotEmpty()
            || $event->targetCart->getAllMetadata() !== []
        )) {
            $this->syncManager->sync($event->targetCart);
        }
    }
}
