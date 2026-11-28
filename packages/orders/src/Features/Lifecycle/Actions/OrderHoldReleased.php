<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Lifecycle\Actions;

use AIArmada\Orders\Support\AssertsOrderOwnerBoundary;
use AIArmada\Orders\Events\OrderHoldReleased as OrderHoldReleasedEvent;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\OnHold;
use AIArmada\Orders\States\Processing;
use RuntimeException;
use Spatie\ModelStates\Transition;

/**
 * Release an order from OnHold back to Processing.
 *
 * Clears the held_at toggle so held_at !== null always means
 * "currently on hold".
 */
final class OrderHoldReleased extends Transition
{
    use AssertsOrderOwnerBoundary;

    public function __construct(
        private Order $order,
        private ?string $reason = null,
        private ?string $releasedBy = null,
    ) {}

    public function handle(): Order
    {
        $this->assertOwnerBoundaryForMutation($this->order, __METHOD__);

        return $this->order->getConnection()->transaction(function (): Order {
            $this->order->newQuery()->lockForUpdate()->findOrFail($this->order->getKey());
            $this->order->refresh();
            $this->assertOwnerBoundaryForMutation($this->order, __METHOD__);

            if (! $this->order->status instanceof OnHold) {
                throw new RuntimeException("Order {$this->order->order_number} is not on hold.");
            }

            $this->order->status->transitionTo(Processing::class);
            $this->order->held_at = null;
            $this->order->save();

            $this->order->orderNotes()->create([
                'user_id' => $this->releasedBy,
                'content' => 'Order hold released' . ($this->reason !== null ? ": {$this->reason}" : ''),
                'visibility' => 'internal',
            ]);

            $order = $this->order;
            $reason = $this->reason;
            $releasedBy = $this->releasedBy;

            $order->getConnection()->afterCommit(function () use ($order, $reason, $releasedBy): void {
                event(new OrderHoldReleasedEvent($order, $reason, $releasedBy));
            });

            return $this->order;
        });
    }
}
