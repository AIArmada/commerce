<?php

declare(strict_types=1);

namespace AIArmada\Orders\Support;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\Orders\Enums\PaymentStatus;
use AIArmada\Orders\Enums\RefundStatus;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\Models\OrderRefund;
use InvalidArgumentException;

/**
 * Enforce refund capacity on both dimensions of received money.
 *
 * A refund is bounded by the order's remaining refundable amount (paid minus
 * settled minus pending refunds) and, for every allocation row, by the
 * referenced payment's own remaining amount. Large refunds split FIFO across
 * the payments that actually received money instead of over-allocating a
 * single receipt.
 */
final class RefundAllocator
{
    /**
     * A payment's own remaining refundable amount: its amount minus every
     * pending/completed refund attributed to it. Failed refunds reserve
     * nothing. Only payments that actually received money (Completed or
     * Refunded) have capacity.
     */
    public static function remainingForPayment(OrderPayment $payment): int
    {
        if (! in_array($payment->status, [PaymentStatus::Completed, PaymentStatus::Refunded], true)) {
            return 0;
        }

        $order = $payment->order;

        if ($order instanceof Order
            && MoneyFormatter::normalizeCurrency($payment->currency) !== MoneyFormatter::normalizeCurrency($order->currency)) {
            return 0;
        }

        $reserved = (int) OrderRefund::query()
            ->where('payment_id', $payment->getKey())
            ->whereIn('status', [RefundStatus::Pending->value, RefundStatus::Completed->value])
            ->sum('amount');

        return max(0, (int) $payment->amount - $reserved);
    }

    /**
     * The order's remaining refundable amount: total received money minus
     * settled and pending refunds. Never falls back to the order total, so an
     * order that never received money has no refundable cash.
     */
    public static function remainingForOrder(Order $order): int
    {
        return max(
            0,
            $order->getTotalPaid() - $order->getTotalRefunded() - $order->getTotalPendingRefunded(),
        );
    }

    /**
     * Split a refund amount across the payments that received money.
     *
     * Allocations consume payments FIFO by creation order, bounded by each
     * payment's own remaining amount. When a payment is specified, the whole
     * amount is allocated to it and the request is rejected when the payment
     * cannot cover it. The supplied model is only an identity reference: the
     * authoritative row is resolved through the locked order's owner-safe
     * relationship, and its persisted amount, status, currency, and parentage
     * are the only ones used.
     *
     * @return list<array{payment: OrderPayment, amount: int}>
     */
    public static function allocate(Order $order, int $amount, ?OrderPayment $payment = null): array
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than zero.');
        }

        $remaining = self::remainingForOrder($order);

        if ($amount > $remaining) {
            throw new InvalidArgumentException(sprintf(
                'Refund amount cannot exceed the remaining refundable amount of %d.',
                $remaining,
            ));
        }

        if ($payment !== null) {
            // The supplied model is an identity reference, not a trusted
            // snapshot: a caller may hold a stale, deleted, forged, or
            // foreign-owner instance. Resolve the authoritative row through
            // the locked order's owner-safe relationship before using its
            // amount, status, currency, or parentage.
            /** @var OrderPayment|null $resolved */
            $resolved = $order->payments()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->first();

            if (! $resolved instanceof OrderPayment) {
                throw new InvalidArgumentException('A refund payment must belong to the same order.');
            }

            if (! in_array($resolved->status, [PaymentStatus::Completed, PaymentStatus::Refunded], true)) {
                throw new InvalidArgumentException('The specified payment has not received any refundable money.');
            }

            if (MoneyFormatter::normalizeCurrency($resolved->currency) !== MoneyFormatter::normalizeCurrency($order->currency)) {
                throw new InvalidArgumentException('The specified payment currency does not match the order currency.');
            }

            $paymentRemaining = self::remainingForPayment($resolved);

            if ($amount > $paymentRemaining) {
                throw new InvalidArgumentException(sprintf(
                    'Refund amount cannot exceed the remaining refundable amount of %d on the specified payment.',
                    $paymentRemaining,
                ));
            }

            return [['payment' => $resolved, 'amount' => $amount]];
        }

        $eligible = self::eligiblePayments($order);
        $capacities = self::remainingByPayment($order, $eligible);
        $available = 0;

        foreach ($capacities as $capacity) {
            // The request is already bounded by the order balance. Saturate
            // at that bound rather than overflowing an integer sum.
            $available += min($capacity, $amount - $available);
        }

        if ($amount > $available) {
            throw new InvalidArgumentException(sprintf(
                'Refund amount cannot exceed the remaining refundable amount of %d.',
                $remaining,
            ));
        }

        $allocations = [];
        $left = $amount;

        foreach ($eligible as $candidate) {
            if ($left <= 0) {
                break;
            }

            $candidateRemaining = $capacities[(string) $candidate->getKey()];

            if ($candidateRemaining <= 0) {
                continue;
            }

            $allocated = min($candidateRemaining, $left);

            $allocations[] = ['payment' => $candidate, 'amount' => $allocated];
            $left -= $allocated;
        }

        if ($left > 0) {
            throw new InvalidArgumentException('Order has no payment with remaining refundable capacity.');
        }

        return $allocations;
    }

    /**
     * Compute capacity for a locked order's eligible receipts with one
     * aggregate query instead of a query for every receipt.
     *
     * @param  list<OrderPayment>  $payments
     * @return array<string, int>
     */
    public static function remainingByPayment(Order $order, array $payments): array
    {
        if ($payments === []) {
            return [];
        }

        $reserved = $order->refunds()
            ->whereIn('payment_id', array_map(static fn (OrderPayment $payment): string => (string) $payment->getKey(), $payments))
            ->whereIn('status', [RefundStatus::Pending->value, RefundStatus::Completed->value])
            ->selectRaw('payment_id, SUM(amount) as reserved_amount')
            ->groupBy('payment_id')
            ->pluck('reserved_amount', 'payment_id');

        $capacities = [];
        foreach ($payments as $payment) {
            $id = (string) $payment->getKey();
            $capacities[$id] = max(0, (int) $payment->amount - (int) $reserved->get($id, 0));
        }

        return $capacities;
    }

    /**
     * Payments that actually received money, FIFO by creation order. A
     * specified payment is returned as the single candidate; eligibility
     * checks stay with the caller so the rejection carries its own message.
     *
     * @return list<OrderPayment>
     */
    public static function eligiblePayments(Order $order, ?OrderPayment $payment = null): array
    {
        if ($payment !== null) {
            return [$payment];
        }

        $payments = $order
            ->payments()
            ->whereIn('status', [PaymentStatus::Completed->value, PaymentStatus::Refunded->value])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->filter(static function (OrderPayment $payment) use ($order): bool {
                return MoneyFormatter::normalizeCurrency($payment->currency) === MoneyFormatter::normalizeCurrency($order->currency);
            })
            ->values();

        return array_values($payments->all());
    }
}
