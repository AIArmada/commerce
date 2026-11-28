<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Payments\Actions;

use AIArmada\Orders\Contracts\OrderPaymentLifecycle;
use AIArmada\Orders\Enums\PaymentStatus;
use AIArmada\Orders\Enums\RefundStatus;
use AIArmada\Orders\Events\OrderRefunded;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderRefund;
use AIArmada\Orders\Support\AssertsOrderOwnerBoundary;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use RuntimeException;
use Spatie\ModelStates\Transition;

/**
 * Complete a provider-confirmed pending refund.
 *
 * The refund row already carries its own per-payment allocation (created by
 * RegisterOrderRefund), so completion only settles that reservation; no
 * additional allocation or request-level metadata validation runs here.
 */
final class RefundCompleted extends Transition
{
    use AssertsOrderOwnerBoundary;

    public function __construct(
        private OrderRefund $refund,
        private ?string $transactionId = null,
    ) {}

    public function handle(): Order
    {
        return $this->refund->getConnection()->transaction(function (): Order {
            $refundId = $this->refund->getKey();
            $orderId = $this->refund->order_id;

            if ($orderId === null) {
                throw new RuntimeException('The refund must belong to an order.');
            }

            // Lock order → refund → payment everywhere, so completion cannot
            // interleave with allocation or failure in a way that leaves the
            // cached totals or payment terminal state inconsistent. The initial
            // read above identifies the immutable parent only; every decision
            // below is made from the locked refund row.
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->lockForUpdate()
                ->findOrFail($orderId);
            $this->assertOwnerBoundaryForMutation($lockedOrder, __METHOD__);
            $lockedOrder->unsetRelation('refunds');

            /** @var OrderRefund|null $lockedRefund */
            $lockedRefund = $lockedOrder->refunds()
                ->whereKey($refundId)
                ->lockForUpdate()
                ->first();

            if (! $lockedRefund instanceof OrderRefund) {
                throw new RuntimeException('The refund must belong to an order.');
            }

            $lockedRefund->setRelation('order', $lockedOrder);

            // A completed replay preserves provider identity and terminal
            // timestamp and emits no event and no accounting change.
            if ($lockedRefund->isCompleted()) {
                return $lockedOrder;
            }

            if ($lockedRefund->isFailed()) {
                throw new RuntimeException('Only pending refunds can be completed.');
            }

            if (! $lockedRefund->isPending()) {
                throw new RuntimeException('Only pending refunds can be completed.');
            }

            $amount = (int) $lockedRefund->amount;

            if ($amount <= 0) {
                throw new InvalidArgumentException('Refund amount must be greater than zero.');
            }

            $lockedRefund->status = RefundStatus::Completed;
            $lockedRefund->refunded_at = CarbonImmutable::now();

            if ($this->transactionId !== null) {
                $lockedRefund->transaction_id = $this->transactionId;
            }

            $lockedRefund->save();
            $lockedOrder->unsetRelation('refunds');
            // The status-change event already moved the totals; refresh so
            // reads and the save below use fresh totals.
            $lockedOrder->refresh();

            // A payment reaches its own terminal state as soon as its whole
            // amount is returned, independent of the order-level remainder.
            $paidTotal = $lockedOrder->getTotalPaid();
            $refundedTotal = $lockedOrder->getTotalRefunded();

            $completedByPayment = $lockedOrder->refunds()
                ->where('status', RefundStatus::Completed->value)
                ->whereNotNull('payment_id')
                ->selectRaw('payment_id, SUM(amount) as refunded_amount')
                ->groupBy('payment_id')
                ->pluck('refunded_amount', 'payment_id');

            foreach ($lockedOrder->payments()->where('status', PaymentStatus::Completed->value)->get() as $payment) {
                $completedAmount = (int) $completedByPayment->get((string) $payment->getKey(), 0);

                if ($completedAmount >= (int) $payment->amount && $completedAmount > 0) {
                    $payment->status = PaymentStatus::Refunded;
                    $payment->refunded_at = CarbonImmutable::now();
                    $payment->save();
                }
            }

            if ($paidTotal > 0 && $refundedTotal >= $paidTotal) {
                app(OrderPaymentLifecycle::class)->markRefunded((string) $lockedOrder->getKey());
                $lockedOrder->refresh();
            }

            $event = new OrderRefunded(
                $lockedOrder,
                $amount,
                $lockedRefund->reason,
                (string) $lockedRefund->getKey(),
                $lockedRefund->metadata ?? [],
            );
            $lockedOrder->getConnection()->afterCommit(static fn () => event($event));

            return $lockedOrder;
        });
    }
}
