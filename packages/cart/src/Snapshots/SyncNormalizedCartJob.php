<?php

declare(strict_types=1);

namespace AIArmada\Cart\Snapshots;

use AIArmada\CommerceSupport\Contracts\OwnerScopedJob;
use AIArmada\CommerceSupport\Support\OwnerJobContext;
use AIArmada\CommerceSupport\Traits\OwnerContextJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SyncNormalizedCartJob implements OwnerScopedJob, ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use OwnerContextJob;
    use Queueable;

    public function __construct(
        public readonly string $identifier,
        public readonly string $instance,
        public readonly ?string $ownerType = null,
        public readonly string | int | null $ownerId = null,
        public readonly bool $ownerIsGlobal = false,
    ) {
        $this->onQueue(config('cart.snapshots.synchronization.queue_name', 'cart-sync'));
        $this->onConnection(
            config('cart.snapshots.synchronization.queue_connection')
                ?: config('queue.default', 'sync')
        );
    }

    /**
     * Coalesce the per-mutation dispatches: at most one pending sync per cart.
     *
     * The job re-resolves the cart fresh at execution time, so a coalesced job
     * always syncs the latest state and can never stale-overwrite a snapshot.
     */
    public function uniqueId(): string
    {
        return implode('|', [
            'sync_normalized_cart',
            $this->ownerType ?? 'global',
            (string) ($this->ownerId ?? 'global'),
            $this->identifier,
            $this->instance,
        ]);
    }

    public function uniqueFor(): int
    {
        return 60;
    }

    public function ownerContext(): OwnerJobContext
    {
        return new OwnerJobContext(
            ownerType: $this->ownerType,
            ownerId: $this->ownerId,
            ownerIsGlobal: $this->ownerIsGlobal,
        );
    }

    protected function performJob(): void
    {
        $syncManager = app(CartSyncManager::class);

        try {
            $cart = app(CartInstanceManager::class)->resolve($this->instance, $this->identifier);
            $syncManager->sync($cart, force: true);
        } catch (Throwable $e) {
            Log::error('Failed to synchronize normalized cart snapshot', [
                'identifier' => $this->identifier,
                'instance' => $this->instance,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
