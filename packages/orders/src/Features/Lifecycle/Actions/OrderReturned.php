<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Lifecycle\Actions;

use AIArmada\Orders\Actions\Concerns\AssertsOrderOwnerBoundary;
use AIArmada\Orders\Events\OrderReturned as OrderReturnedEvent;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Returned;
use AIArmada\Orders\Support\PackageUnit;
use Carbon\CarbonImmutable;
use DomainException;
use RuntimeException;
use Spatie\ModelStates\Transition;

/**
 * Transition to Returned state.
 *
 * Records returned_at once (historical fact, mirrors order_items.returned_at
 * at the order level). A subsequent refund transitions Returned → Refunded.
 */
final class OrderReturned extends Transition
{
    use AssertsOrderOwnerBoundary;

    public function __construct(
        private Order $order,
        private ?string $reason = null,
        private ?string $returnedBy = null,
    ) {}

    public function handle(): Order
    {
        $this->assertOwnerBoundaryForMutation($this->order, __METHOD__);

        return $this->order->getConnection()->transaction(function (): Order {
            $this->order->newQuery()->lockForUpdate()->findOrFail($this->order->getKey());
            $this->order->refresh();
            $this->assertOwnerBoundaryForMutation($this->order, __METHOD__);

            if (! $this->order->status->canTransitionTo(Returned::class)) {
                throw new RuntimeException("Order {$this->order->order_number} cannot be returned in its current state.");
            }

            if ($this->order->items()->get()->contains(fn ($item): bool => PackageUnit::hasShippableComponents($item))) {
                throw new DomainException('Use a package return authorization for each returned package unit.');
            }

            $this->order->status->transitionTo(Returned::class);
            $this->order->returned_at = CarbonImmutable::now();
            $this->order->save();

            $this->order->orderNotes()->create([
                'user_id' => $this->returnedBy,
                'content' => 'Order returned' . ($this->reason !== null ? ": {$this->reason}" : ''),
                'visibility' => 'customer',
            ]);

            $order = $this->order;
            $reason = $this->reason;
            $returnedBy = $this->returnedBy;

            $order->getConnection()->afterCommit(function () use ($order, $reason, $returnedBy): void {
                event(new OrderReturnedEvent($order, $reason, $returnedBy));
            });

            return $this->order;
        });
    }
}
