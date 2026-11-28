<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Payments\Actions;

use AIArmada\CommerceSupport\Support\MoneyFormatter;
use AIArmada\Orders\Actions\Concerns\AssertsOrderOwnerBoundary;
use AIArmada\Orders\Contracts\CancellationRefundReservations;
use AIArmada\Orders\Contracts\PackageReturnVerifier;
use AIArmada\Orders\Enums\RefundStatus;
use AIArmada\Orders\Events\OrderRefundFailed;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\Models\OrderRefund;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\Shipped;
use AIArmada\Orders\Support\PackageUnit;
use AIArmada\Orders\Support\RefundAllocationValidator;
use AIArmada\Orders\Support\RefundAllocator;
use Carbon\CarbonImmutable;
use DomainException;
use InvalidArgumentException;
use RuntimeException;

final readonly class RegisterOrderRefund implements CancellationRefundReservations
{
    use AssertsOrderOwnerBoundary;

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function execute(
        Order $order,
        int $amount,
        string $transactionId,
        string $reason,
        array $metadata = [],
        ?OrderPayment $payment = null,
    ): Order {
        $this->assertOwnerBoundaryForMutation($order, __METHOD__);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than zero.');
        }

        if (mb_trim($transactionId) === '') {
            throw new InvalidArgumentException('A refund transaction ID is required.');
        }

        RefundAllocationValidator::assertAmount($metadata, $amount);

        return $order->getConnection()->transaction(function () use ($order, $amount, $transactionId, $reason, $metadata, $payment): Order {
            // Keep the caller's model instance while locking the database row,
            // so retries cannot create duplicate refunds or overshoot the
            // remaining refundable amount.
            $order->newQuery()
                ->lockForUpdate()
                ->findOrFail($order->getKey());
            $order->refresh();
            $this->assertOwnerBoundaryForMutation($order, __METHOD__);

            $metadata = $this->packageRefundMetadata($order, $metadata);

            // The allocation key survives provider settlement, while the
            // transaction ID can change. Replay the original request first.
            $existingRows = $order->refunds()->where('allocation_key', $transactionId)->get();

            if ($existingRows->isEmpty()) {
                $matched = $order->refunds()->where('transaction_id', $transactionId)->first();

                if ($matched instanceof OrderRefund) {
                    $existingRows = $order->refunds()->where('allocation_key', $matched->allocation_key)->get();
                }
            }

            if ($existingRows->isNotEmpty()) {
                /** @var OrderRefund $existingRefund */
                $existingRefund = $existingRows->first();
                $this->assertSamePackageReturn($existingRefund, $metadata);

                if ($amount !== (int) $existingRows->sum('amount')) {
                    throw new DomainException(isset($metadata['package_unit']) ? 'The amount does not match the existing package refund.' : 'The amount does not match the existing refund.');
                }

                if ($payment !== null && ($existingRows->count() !== 1 || (string) $existingRefund->payment_id !== (string) $payment->getKey())) {
                    throw new InvalidArgumentException('The specified payment does not match the existing refund allocations.');
                }

                if ($existingRows->contains(fn (OrderRefund $refund): bool => $refund->isPending())) {
                    throw new RuntimeException("Refund transaction {$transactionId} is already pending.");
                }

                if ($existingRows->contains(fn (OrderRefund $refund): bool => $refund->isFailed())) {
                    throw new RuntimeException("Refund transaction {$transactionId} has already failed.");
                }

                return $order;
            }

            if (! $this->canRefund($order, $metadata)) {
                throw new RuntimeException("Order {$order->order_number} cannot be refunded in its current state.");
            }

            $this->assertRefundScope($order, $metadata, $amount);

            return (new RefundProcessed($order, $amount, $transactionId, $reason, $metadata, $payment))->handle();
        });
    }

    /**
     * Create the pending reservations for a refund request across the
     * payments that received money.
     *
     * The request identity (transactionId) is the idempotency key: a retry
     * with the same key returns the already-reserved allocations and a failed
     * request stays failed. When a payment is specified, the whole amount is
     * reserved against it and must fit that payment's own remaining amount;
     * otherwise the amount is allocated FIFO across the eligible payments.
     *
     * @param  array<string, mixed>  $metadata
     * @return list<OrderRefund>
     */
    public function createPending(
        Order $order,
        int $amount,
        string $transactionId,
        string $reason,
        array $metadata = [],
        ?OrderPayment $payment = null,
    ): array {
        $this->assertOwnerBoundaryForMutation($order, __METHOD__);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Refund amount must be greater than zero.');
        }

        if (mb_trim($transactionId) === '') {
            throw new InvalidArgumentException('A refund transaction ID is required.');
        }

        RefundAllocationValidator::assertAmount($metadata, $amount);

        return $order->getConnection()->transaction(function () use ($order, $amount, $transactionId, $reason, $metadata, $payment): array {
            $lockedOrder = $order->newQuery()->lockForUpdate()->findOrFail($order->getKey());
            $this->assertOwnerBoundaryForMutation($lockedOrder, __METHOD__);
            $metadata = $this->packageRefundMetadata($lockedOrder, $metadata);

            // The allocation key is the caller's idempotency key. Check it
            // after locking the order so concurrent retries cannot create two
            // reservation sets, even when the first refund makes the order
            // unrefundable before the retry arrives.
            $existing = $lockedOrder->refunds()
                ->where('allocation_key', $transactionId)
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            if ($existing->isNotEmpty()) {
                /** @var OrderRefund $existingRefund */
                $existingRefund = $existing->first();
                $this->assertSamePackageReturn($existingRefund, $metadata);
                if ($amount !== (int) $existing->sum('amount')) {
                    throw new DomainException(isset($metadata['package_unit']) ? 'The amount does not match the existing package refund.' : 'The amount does not match the existing refund.');
                }

                if ($existing->contains(fn (OrderRefund $refund): bool => $refund->isFailed())) {
                    throw new RuntimeException("Refund transaction {$transactionId} has already failed.");
                }

                if ($payment !== null && $existing->count() !== 1) {
                    throw new InvalidArgumentException('The specified payment does not match the reserved refund allocations.');
                }

                if ($payment !== null && (string) $existingRefund->payment_id !== (string) $payment->getKey()) {
                    throw new InvalidArgumentException('The specified payment does not match the reserved refund allocations.');
                }

                return array_values($existing->all());
            }

            if (! $this->canRefund($lockedOrder, $metadata)) {
                throw new RuntimeException("Order {$lockedOrder->order_number} cannot be refunded in its current state.");
            }

            $this->assertRefundScope($lockedOrder, $metadata, $amount);
            $allocations = RefundAllocator::allocate($lockedOrder, $amount, $payment);

            $refunds = [];

            foreach ($allocations as $allocation) {
                $refunds[] = $this->reservePendingRefund(
                    order: $lockedOrder,
                    payment: $allocation['payment'],
                    amount: $allocation['amount'],
                    allocationKey: $transactionId,
                    reason: $reason,
                    metadata: $metadata,
                );
            }

            return $refunds;
        });
    }

    public function reserveForCancellation(string $orderId, string $reason): void
    {
        $this->createCancellationReservations(Order::query()->findOrFail($orderId), $reason);
    }

    /**
     * Reserve the remaining refundable balance as pending refunds when an
     * order is canceled.
     *
     * The remainder is total paid minus settled refunds minus existing
     * pending reservations, allocated FIFO across the actual completed
     * payments so no single payment is reserved beyond its own remaining
     * balance. Reservation identities are stable per payment, so retries
     * cannot reserve twice, and a never-paid order never falls back to the
     * order total as refundable cash.
     *
     * @return list<OrderRefund>
     */
    public function createCancellationReservations(Order $order, string $reason): array
    {
        $this->assertOwnerBoundaryForMutation($order, __METHOD__);

        return $order->getConnection()->transaction(function () use ($order, $reason): array {
            $lockedOrder = $order->newQuery()->lockForUpdate()->findOrFail($order->getKey());
            $this->assertOwnerBoundaryForMutation($lockedOrder, __METHOD__);

            if (! $lockedOrder->status instanceof Canceled) {
                throw new RuntimeException(sprintf(
                    'Order %s must be canceled before cancellation refunds can be reserved.',
                    $lockedOrder->order_number,
                ));
            }

            // Only money actually received is refundable: the shared
            // allocator never falls back to the order total, so an order that
            // was never paid reserves nothing.
            $remaining = RefundAllocator::remainingForOrder($lockedOrder);

            if ($remaining <= 0) {
                return [];
            }

            $reservations = [];
            $payments = RefundAllocator::eligiblePayments($lockedOrder);
            $capacities = RefundAllocator::remainingByPayment($lockedOrder, $payments);
            $allocationKeys = array_map(
                static fn (OrderPayment $payment): string => sprintf('cancel:%s:%s', $lockedOrder->getKey(), $payment->getKey()),
                $payments,
            );
            $existingKeys = $lockedOrder->refunds()->whereIn('allocation_key', $allocationKeys)->pluck('allocation_key')->flip();

            foreach ($payments as $payment) {
                if ($remaining <= 0) {
                    break;
                }

                // Stable per-payment identity: retries (including after a
                // recorded failure) never reserve the same payment twice.
                $allocationKey = sprintf('cancel:%s:%s', $lockedOrder->getKey(), $payment->getKey());

                if ($existingKeys->has($allocationKey)) {
                    continue;
                }

                $amount = min($capacities[(string) $payment->getKey()], $remaining);

                if ($amount <= 0) {
                    continue;
                }

                $reservations[] = $this->reservePendingRefund(
                    order: $lockedOrder,
                    payment: $payment,
                    amount: $amount,
                    allocationKey: $allocationKey,
                    reason: 'Order canceled: ' . $reason,
                    metadata: [],
                );

                $remaining -= $amount;
            }

            return $reservations;
        });
    }

    /**
     * Create a pending reservation against a specific payment.
     *
     * The created-refund event syncs pending_refunded_total; callers own
     * the ceiling and idempotency checks. The allocation key and the
     * transaction id start as the caller's request identity; completing the
     * refund replaces the transaction id with the provider identity.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function reservePendingRefund(
        Order $order,
        OrderPayment $payment,
        int $amount,
        string $allocationKey,
        string $reason,
        array $metadata,
    ): OrderRefund {
        return $order->refunds()->create([
            'payment_id' => $payment->getKey(),
            'gateway' => $payment->gateway,
            'transaction_id' => $allocationKey,
            'allocation_key' => $allocationKey,
            'amount' => $amount,
            'currency' => $payment->currency !== '' ? MoneyFormatter::normalizeCurrency($payment->currency) : MoneyFormatter::normalizeCurrency($order->currency),
            'status' => RefundStatus::Pending,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }

    public function claimPendingSubmission(OrderRefund $refund): bool
    {
        $order = $refund->order;

        if (! $order instanceof Order) {
            throw new RuntimeException('The refund must belong to an order.');
        }

        $this->assertOwnerBoundaryForMutation($order, __METHOD__);

        return $order->getConnection()->transaction(function () use ($refund): bool {
            // Lock order → refund everywhere, like every other refund path.
            $lockedOrder = $this->lockOrderRow($refund);

            /** @var OrderRefund|null $lockedRefund */
            $lockedRefund = $lockedOrder->refunds()
                ->lockForUpdate()
                ->find($refund->getKey());

            if (! $lockedRefund instanceof OrderRefund
                || ! $lockedRefund->isPending()
                || $lockedRefund->hasProviderSubmissionStarted()) {
                return false;
            }

            $lockedRefund->provider_submission_started_at = CarbonImmutable::now();
            $lockedRefund->save();

            return true;
        });
    }

    public function correlateProviderReference(OrderRefund $refund, ?string $providerRefundId, ?string $relatedPaymentId = null): bool
    {
        $order = $refund->order;

        if (! $order instanceof Order) {
            throw new RuntimeException('The refund must belong to an order.');
        }

        $this->assertOwnerBoundaryForMutation($order, __METHOD__);

        if ($providerRefundId === null || mb_trim($providerRefundId) === '' || $providerRefundId === $relatedPaymentId) {
            return true;
        }

        $providerRefundId = mb_trim($providerRefundId);

        return $order->getConnection()->transaction(function () use ($refund, $providerRefundId): bool {
            $lockedOrder = $this->lockOrderRow($refund);
            $lockedRefund = $lockedOrder->refunds()->lockForUpdate()->findOrFail($refund->getKey());
            $metadata = $lockedRefund->metadata ?? [];
            $existingId = $metadata['provider_refund_id'] ?? null;

            if (is_scalar($existingId) && mb_trim((string) $existingId) !== '') {
                return mb_trim((string) $existingId) === $providerRefundId;
            }

            if (! $lockedRefund->isPending()) {
                return false;
            }

            $metadata['provider_refund_id'] = $providerRefundId;
            $lockedRefund->metadata = $metadata;
            $lockedRefund->save();

            return true;
        });
    }

    public function completePending(OrderRefund $refund, ?string $transactionId = null): Order
    {
        $order = $refund->order;
        $this->assertOwnerBoundaryForMutation($order, __METHOD__);

        return (new RefundCompleted($refund, $transactionId))->handle();
    }

    public function failPending(OrderRefund $refund, string $reason): OrderRefund
    {
        $order = $refund->order;
        $this->assertOwnerBoundaryForMutation($order, __METHOD__);

        [$failed, $changed] = $order->getConnection()->transaction(function () use ($refund, $reason): array {
            // Lock order → refund everywhere, like every other refund path.
            $lockedOrder = $this->lockOrderRow($refund);
            $lockedOrder->unsetRelation('refunds');

            /** @var OrderRefund $lockedRefund */
            $lockedRefund = $lockedOrder->refunds()
                ->whereKey($refund->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $lockedRefund->setRelation('order', $lockedOrder);

            $changed = $lockedRefund->isPending();

            if ($changed) {
                $lockedRefund->status = RefundStatus::Failed;
                $lockedRefund->failed_at = CarbonImmutable::now();
                $lockedRefund->notes = $reason;
                $lockedRefund->save();

                // The status-change event already moved the totals; refresh so
                // any later reads in this request see fresh values.
                $lockedOrder->refresh();
            }

            // Report the persisted row: the caller's instance predates the
            // lock and would still read as pending to listeners.
            return [$lockedRefund, $changed];
        });

        if ($changed) {
            event(new OrderRefundFailed($failed->order, $failed, $reason, $failed->metadata ?? []));
        }

        return $failed;
    }

    /** @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function packageRefundMetadata(Order $order, array $metadata): array
    {
        if (array_key_exists('package_unit', $metadata)) {
            throw new InvalidArgumentException('Package refund identity is derived from the return authorization.');
        }

        $authorizationId = $metadata['return_authorization_id'] ?? null;
        if ($authorizationId === null) {
            return $metadata;
        }

        if (! is_string($authorizationId) || ! app()->bound(PackageReturnVerifier::class)) {
            throw new DomainException('A valid package return authorization is required.');
        }

        $unit = app(PackageReturnVerifier::class)->receivedUnit((string) $order->getKey(), $authorizationId);
        $metadata['package_unit'] = [
            'order_item_id' => $unit->orderItemId,
            'ordinal' => $unit->ordinal,
        ];

        return $metadata;
    }

    /** @param array<string, mixed> $metadata */
    private function canRefund(Order $order, array $metadata): bool
    {
        return $order->canBeRefunded() || ($order->status instanceof Shipped && isset($metadata['package_unit']));
    }

    /** @param array<string, mixed> $metadata */
    private function assertSamePackageReturn(OrderRefund $refund, array $metadata): void
    {
        if (($refund->metadata['return_authorization_id'] ?? null) !== ($metadata['return_authorization_id'] ?? null)) {
            throw new DomainException('The refund transaction already belongs to another refund request.');
        }
    }

    /** @param array<string, mixed> $metadata */
    private function assertRefundScope(Order $order, array $metadata, int $amount): void
    {
        $reserved = $order->refunds()->whereIn('status', [RefundStatus::Pending, RefundStatus::Completed]);
        $unit = $metadata['package_unit'] ?? null;

        if (is_array($unit)) {
            $refunds = $reserved->where('metadata->package_unit->order_item_id', $unit['order_item_id'])
                ->where('metadata->package_unit->ordinal', $unit['ordinal']);
            $paidUnit = new PackageUnit($order->items()->findOrFail($unit['order_item_id']), $unit['ordinal']);
            $settled = (int) $refunds->clone()->where('status', RefundStatus::Completed)->sum('amount');

            if ($settled >= $paidUnit->refundAmount() || $refunds->where('status', RefundStatus::Pending)->exists()) {
                throw new DomainException('The package unit already has a pending or completed refund.');
            }

            if ($amount !== $paidUnit->refundAmount() - $settled) {
                throw new DomainException('Refund the remaining net paid amount and allocated tax for the package unit.');
            }

            return;
        }

        $packageTotal = $order->items()->get()->sum(fn ($item): int => ! PackageUnit::hasShippableComponents($item) ? 0 : $item->calculateTotal());
        if ($packageTotal > 0 && $amount + (int) $reserved->whereNull('metadata->package_unit')->sum('amount')
            > max(0, $order->getTotalPaid() - $packageTotal)) {
            throw new DomainException('A package unit refund requires a received and inspected return authorization.');
        }
    }

    /**
     * Lock the parent order row for a refund, preserving the shared
     * order → refund → payment lock order across every refund path.
     */
    private function lockOrderRow(OrderRefund $refund): Order
    {
        $order = $refund->order;

        if (! $order instanceof Order) {
            throw new RuntimeException('The refund must belong to an order.');
        }

        /** @var Order $lockedOrder */
        $lockedOrder = $order->newQuery()
            ->lockForUpdate()
            ->findOrFail($order->getKey());

        $this->assertOwnerBoundaryForMutation($lockedOrder, __METHOD__);

        return $lockedOrder;
    }
}
