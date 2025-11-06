<?php

declare(strict_types=1);

namespace AIArmada\Cart\Events;

use AIArmada\Cart\Cart;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when two carts are merged together.
 *
 * This event is dispatched during cart migration, typically when a guest cart
 * is merged with a user's existing cart upon authentication.
 *
 * @example
 * ```php
 * CartMerged::dispatch($targetCart, $sourceCart, $mergedItems, 'add_quantities', true);
 *
 * // Listen for cart merges
 * Event::listen(CartMerged::class, function (CartMerged $event) {
 *     logger('Carts merged', [
 *         'target_identifier' => $event->targetCart->getIdentifier(),
 *         'source_identifier' => $event->sourceCart->getIdentifier(),
 *         'items_merged' => $event->totalItemsMerged,
 *         'had_conflicts' => $event->hadConflicts,
 *     ]);
 * });
 * ```
 */
final class CartMerged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new cart merged event instance.
     *
     * @param  Cart  $targetCart  The cart that received the merged items
     * @param  Cart  $sourceCart  The cart whose items were merged from
     * @param  int  $totalItemsMerged  Total number of items that were merged
     * @param  string  $mergeStrategy  The strategy used for merging (e.g., 'add_quantities', 'keep_highest')
     * @param  bool  $hadConflicts  Whether there were conflicting items during merge
     * @param  string|null  $originalSourceIdentifier  The original identifier of the source cart before swap (for cleanup)
     * @param  string|null  $originalTargetIdentifier  The original identifier of the target cart before swap (for tracking)
     */
    public function __construct(
        public readonly Cart $targetCart,
        public readonly Cart $sourceCart,
        public readonly int $totalItemsMerged,
        public readonly string $mergeStrategy,
        public readonly bool $hadConflicts = false,
        public readonly ?string $originalSourceIdentifier = null,
        public readonly ?string $originalTargetIdentifier = null,
    ) {
        //
    }

    /**
     * Get the event data as an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'target_cart' => [
                'identifier' => $this->targetCart->getIdentifier(),
                'instance_name' => $this->targetCart->instance(),
                'items_count' => $this->targetCart->countItems(),
                'total' => $this->targetCart->getRawTotal(),
            ],
            'source_cart' => [
                'identifier' => $this->sourceCart->getIdentifier(),
                'instance_name' => $this->sourceCart->instance(),
                'items_count' => $this->sourceCart->countItems(),
                'total' => $this->sourceCart->getRawTotal(),
            ],
            'merge_details' => [
                'strategy' => $this->mergeStrategy,
                'items_merged' => $this->totalItemsMerged,
                'had_conflicts' => $this->hadConflicts,
            ],
            'timestamp' => now()->toISOString(),
        ];
    }
}
