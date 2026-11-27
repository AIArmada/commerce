<?php

declare(strict_types=1);

namespace AIArmada\Cart\Features\Snapshots\Actions;

use AIArmada\Cart\Events\CartMerged;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshot;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshotCondition;
use AIArmada\Cart\Features\Snapshots\Models\CartSnapshotItem;
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

        $hasLiveTarget = $event->targetCart->storage()->getSnapshot($targetIdentifier, $instance) !== null;

        (new CartSnapshot)->getConnection()->transaction(function () use ($sourceIdentifier, $targetIdentifier, $instance, $owner, $hasLiveTarget): void {
            $source = OwnerContext::withOwner(null, fn () => CartSnapshot::query()
                ->forOwner(null, false)
                ->where('identifier', $sourceIdentifier)
                ->where('instance', $instance)
                ->lockForUpdate()
                ->first());

            if (! $source instanceof CartSnapshot) {
                return;
            }

            $target = CartSnapshot::query()
                ->forOwner($owner, false)
                ->where('identifier', $targetIdentifier)
                ->where('instance', $instance)
                ->lockForUpdate()
                ->first();

            if ($target instanceof CartSnapshot && $source->is($target)) {
                return;
            }

            if ($target instanceof CartSnapshot) {
                if ($hasLiveTarget) {
                    CartSnapshotCondition::query()->where('cart_id', $source->id)->delete();
                    CartSnapshotItem::query()->where('cart_id', $source->id)->delete();
                } else {
                    CartSnapshotItem::query()->where('cart_id', $source->id)
                        ->update(['cart_id' => $target->id]);

                    CartSnapshotCondition::query()->where('cart_id', $source->id)
                        ->update(['cart_id' => $target->id]);
                }

                OwnerContext::withOwner(null, fn (): bool => (bool) $source->delete());

                return;
            }

            $source->identifier = $targetIdentifier;

            if (CartSnapshot::ownerScopingEnabled() && $owner !== null) {
                $source->assignOwner($owner);
            }

            $source->save();
        });

        if ($hasLiveTarget) {
            $this->syncManager->sync($event->targetCart, force: true);
        }
    }
}
