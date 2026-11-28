<?php

declare(strict_types=1);

namespace AIArmada\Orders\Features\Payments\Actions;

use AIArmada\Orders\Contracts\OrderOutboxWriter;
use AIArmada\Orders\Contracts\OrderPaymentLifecycle;
use AIArmada\Orders\Enums\PaymentStatus;
use AIArmada\Orders\Events\OrderFulfillmentRequired;
use AIArmada\Orders\Events\OrderPaid;
use AIArmada\Orders\Events\OrderProcessingStarted;
use AIArmada\Orders\Exceptions\OrderNotAwaitingPayment;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\Support\AssertsOrderOwnerBoundary;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;
use Spatie\ModelStates\Transition;

/**
 * Transition from PendingPayment → Processing.
 *
 * This transition is triggered when payment is confirmed. Partial payments
 * are accepted: the order stays awaiting payment until the accepted
 * completed payments cover the order total, and only the settling payment
 * transitions the order, stamps paid_at, and stages fulfillment.
 */
final class PaymentConfirmed extends Transition
{
    use AssertsOrderOwnerBoundary;

    public function __construct(
        private Order $order,
        private string $transactionId,
        private string $gateway,
        private int $amount,
        /** @var array<string, mixed> */
        private array $metadata = [],
    ) {}

    public function handle(): Order
    {
        if ($this->amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        if (mb_trim($this->transactionId) === '' || mb_trim($this->gateway) === '') {
            throw new InvalidArgumentException('Payment gateway and transaction ID are required.');
        }

        $originalOrder = $this->order;

        return $originalOrder->getConnection()->transaction(function () use ($originalOrder): Order {
            $this->order = $this->order->newQuery()
                ->lockForUpdate()
                ->findOrFail($this->order->getKey());
            $this->assertOwnerBoundaryForMutation($this->order, __METHOD__);

            $existingPayment = $this->order->payments()
                ->where('gateway', $this->gateway)
                ->where('transaction_id', $this->transactionId)
                ->first();

            if ($existingPayment !== null) {
                return $this->handleExistingPayment($existingPayment, $originalOrder);
            }

            // The identity lookup above wins over the lifecycle gate: a
            // recorded identity replays even after the order left the
            // payable states. New money is only accepted while awaiting it.
            if (! $this->order->status->canReceivePayment()) {
                throw OrderNotAwaitingPayment::forPaymentState(class_basename($this->order->status));
            }

            $balanceDue = $this->order->getBalanceDue();

            if ($this->amount > $balanceDue) {
                throw new InvalidArgumentException(
                    "Payment amount cannot exceed the outstanding balance due of {$balanceDue}."
                );
            }

            try {
                // Record payment
                $this->order->getConnection()->transaction(fn (): OrderPayment => $this->order->payments()->create([
                    'transaction_id' => $this->transactionId,
                    'gateway' => $this->gateway,
                    'amount' => $this->amount,
                    'currency' => $this->order->currency,
                    'status' => PaymentStatus::Completed,
                    'paid_at' => CarbonImmutable::now(),
                    'metadata' => $this->metadata,
                ]));
            } catch (UniqueConstraintViolationException $e) {
                // Race condition: another caller inserted the same identity.
                // Reload and validate rather than re-inserting.
                $existingPayment = $this->order->payments()
                    ->where('gateway', $this->gateway)
                    ->where('transaction_id', $this->transactionId)
                    ->first();

                if ($existingPayment === null) {
                    throw $e;
                }

                return $this->handleExistingPayment($existingPayment, $originalOrder);
            }

            // The created-payment event already synced paid_total; refresh so the
            // save below cannot clobber it with stale in-memory attributes.
            $this->order->refresh();

            // Partial payments leave the order awaiting payment. Only the
            // accepted payment that clears the outstanding balance advances
            // the lifecycle and stamps the order-level paid timestamp; the
            // earlier payments keep their own payment timestamps.
            $isSettlingPayment = $this->order->isFullyPaid();

            if ($isSettlingPayment) {
                app(OrderPaymentLifecycle::class)->markPaid((string) $this->order->getKey());
                $this->order->refresh();
            }

            $this->syncOriginalOrder($originalOrder);

            // Stage outbox rows in-transaction so a crash between commit and
            // dispatch stays recoverable. OrderPaid is not staged: invoice
            // creation and payment emails are not replayable. Partial
            // payments stage nothing so fulfillment effects stay tied to
            // full settlement.
            $outboxIds = $isSettlingPayment
                ? [
                    app(OrderOutboxWriter::class)->stage((string) $this->order->getKey(), OrderProcessingStarted::class, $this->transactionId, $this->gateway),
                    app(OrderOutboxWriter::class)->stage((string) $this->order->getKey(), OrderFulfillmentRequired::class, $this->transactionId, $this->gateway),
                ]
                : [];

            // Dispatch events only after the outer transaction commits
            $order = $this->order;
            $transactionId = $this->transactionId;
            $gateway = $this->gateway;
            $amount = $this->amount;

            $order->getConnection()->afterCommit(function () use ($order, $transactionId, $gateway, $amount, $outboxIds): void {
                // The payment really happened: its non-replayable event
                // fires per accepted payment with the actual amount, so
                // revenue recognition records every receipt exactly once.
                event(new OrderPaid($order, $transactionId, $gateway, $amount));

                if ($outboxIds === []) {
                    return;
                }

                app(OrderOutboxWriter::class)->dispatchReplayable((string) $order->getKey(), $outboxIds, $transactionId, $gateway);
            });

            return $originalOrder;
        });
    }

    private function handleExistingPayment(OrderPayment $existingPayment, Order $originalOrder): Order
    {
        if (
            (int) $existingPayment->amount !== $this->amount
            || (string) $existingPayment->currency !== (string) $this->order->currency
        ) {
            throw new InvalidArgumentException(sprintf(
                'Payment identity %s/%s was already recorded with a different amount or currency.',
                $this->gateway,
                $this->transactionId,
            ));
        }

        if (! in_array($existingPayment->status, [PaymentStatus::Completed, PaymentStatus::Refunded], true)) {
            throw new InvalidArgumentException('Payment identity exists but has not been completed.');
        }

        $this->syncOriginalOrder($originalOrder);

        return $originalOrder;
    }

    private function syncOriginalOrder(Order $originalOrder): void
    {
        $originalOrder->setRawAttributes($this->order->getAttributes());
        $originalOrder->setRelations($this->order->getRelations());
        $originalOrder->syncOriginal();
    }
}
