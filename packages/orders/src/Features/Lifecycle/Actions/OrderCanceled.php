<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Lifecycle\Actions;

use AIArmada\Orders\Contracts\CancellationRefundReservations;
use AIArmada\Orders\Contracts\OrderOutboxWriter;
use AIArmada\Orders\Contracts\PackageShipmentVerifier;
use AIArmada\Orders\Events\OrderCanceled as OrderCanceledEvent;
use AIArmada\Orders\Events\OrderCancelInitiated;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\Support\AssertsOrderOwnerBoundary;
use Carbon\CarbonImmutable;
use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Spatie\ModelStates\Transition;

/**
 * Transition to Canceled state.
 *
 * This transition can happen from multiple states (Created, PendingPayment,
 * Processing, OnHold). It records the cancellation reason and optionally
 * releases inventory reservations and issues refunds.
 */
final class OrderCanceled extends Transition
{
    use AssertsOrderOwnerBoundary;

    public function __construct(
        private Order $order,
        private string $reason,
        private ?string $canceledBy = null,
        private bool $issueRefund = true,
    ) {}

    public function handle(): Order
    {
        $this->assertOwnerBoundaryForMutation($this->order, __METHOD__);

        return $this->order->getConnection()->transaction(function (): Order {
            $this->order->newQuery()
                ->lockForUpdate()
                ->findOrFail($this->order->getKey());
            $this->order->refresh();
            $this->assertOwnerBoundaryForMutation($this->order, __METHOD__);

            if (! $this->order->canBeCanceled()) {
                throw new RuntimeException("Order {$this->order->order_number} cannot be canceled in its current state.");
            }

            if (mb_trim($this->reason) === '') {
                throw new InvalidArgumentException('A cancellation reason is required.');
            }
            if (app()->bound(PackageShipmentVerifier::class) && app(PackageShipmentVerifier::class)->hasActivePackageShipment((string) $this->order->getKey())) {
                throw new DomainException('Cancel any unshipped package shipments before canceling the order. Shipped units must be returned.');
            }

            $this->order->status->transitionTo(Canceled::class);
            $this->order->canceled_at = CarbonImmutable::now();
            $this->order->cancellation_reason = $this->reason;
            $this->order->held_at = null;
            $this->order->save();

            $this->order->orderNotes()->create([
                'user_id' => $this->canceledBy,
                'content' => "Order canceled: {$this->reason}",
                'visibility' => 'customer',
            ]);

            // Refunds are driven by money actually received, not the paid_at
            // flag: a partially paid order still needs its receipts returned,
            // and a never-paid order must not fall back to the order total.
            if ($this->issueRefund && $this->order->getTotalPaid() > 0) {
                app(CancellationRefundReservations::class)->reserveForCancellation((string) $this->order->getKey(), $this->reason);

                // The reservation accounting moved cached totals; refresh so
                // subscribers and later reads in this request see them.
                $this->order->refresh();
            }

            // Fulfillment staged but never relayed must not dispatch
            // after cancel cleanup ran: suppress it atomically with the
            // cancellation. Rows the relay already claimed are
            // re-validated against the order lifecycle under lock before
            // dispatch (see RelayOrderOutbox).
            app(OrderOutboxWriter::class)->suppressForOrder((string) $this->order->getKey(), 'Order cancelled; fulfillment suppressed.');

            $order = $this->order;
            $reason = $this->reason;
            $canceledBy = $this->canceledBy;

            $order->getConnection()->afterCommit(function () use ($order, $reason, $canceledBy): void {
                event(new OrderCanceledEvent($order, $reason, $canceledBy));
                event(new OrderCancelInitiated($order, $reason, $canceledBy));
            });

            return $this->order;
        });
    }
}
