<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Lifecycle\Actions;

use AIArmada\Orders\Events\OrderHeld as OrderHeldEvent;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\OnHold;
use AIArmada\Orders\Support\AssertsOrderOwnerBoundary;
use Carbon\CarbonImmutable;
use RuntimeException;
use Spatie\ModelStates\Transition;

/**
 * Transition to OnHold state.
 *
 * Records held_at as a toggle timestamp: set here, cleared by
 * OrderHoldReleased. "Currently on hold" is therefore held_at !== null.
 */
final class OrderHeld extends Transition
{
    use AssertsOrderOwnerBoundary;

    public function __construct(
        private Order $order,
        private string $reason,
        private ?string $heldBy = null,
    ) {}

    public function handle(): Order
    {
        $this->assertOwnerBoundaryForMutation($this->order, __METHOD__);

        return $this->order->getConnection()->transaction(function (): Order {
            $this->order->newQuery()->lockForUpdate()->findOrFail($this->order->getKey());
            $this->order->refresh();
            $this->assertOwnerBoundaryForMutation($this->order, __METHOD__);

            if (! $this->order->status->canTransitionTo(OnHold::class)) {
                throw new RuntimeException("Order {$this->order->order_number} cannot be placed on hold in its current state.");
            }

            $this->order->status->transitionTo(OnHold::class);
            $this->order->held_at = CarbonImmutable::now();
            $this->order->save();

            $this->order->orderNotes()->create([
                'user_id' => $this->heldBy,
                'content' => "Order placed on hold: {$this->reason}",
                'visibility' => 'internal',
            ]);

            $order = $this->order;
            $reason = $this->reason;
            $heldBy = $this->heldBy;

            $order->getConnection()->afterCommit(function () use ($order, $reason, $heldBy): void {
                event(new OrderHeldEvent($order, $reason, $heldBy));
            });

            return $this->order;
        });
    }
}
