<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Orders\OrdersEngineTestCase;
use AIArmada\Orders\Actions\CancelOrder;
use AIArmada\Orders\Actions\RegisterOrderPayment;
use AIArmada\Orders\Actions\RegisterOrderRefund;
use AIArmada\Orders\Enums\RefundStatus;
use AIArmada\Orders\Events\OrderFulfillmentRequired;
use AIArmada\Orders\Events\OrderProcessingStarted;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderRefund;
use AIArmada\Orders\States\Canceled;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\ModelStates\Exceptions\TransitionNotFound;
use Tests\Support\EngineRace;

uses(OrdersEngineTestCase::class);

function ordersEngineRepeats(): int
{
    return 5;
}

function ordersEngineOrder(int $grandTotal = 10000): Order
{
    return Order::create([
        'order_number' => 'ORD-ENG-' . mb_strtoupper(Str::random(10)),
        'status' => PendingPayment::class,
        'currency' => 'MYR',
        'subtotal' => $grandTotal,
        'grand_total' => $grandTotal,
    ])->refresh();
}

/**
 * @return array<string, mixed>
 */
function ordersEnginePayOutcome(string $orderId, string $transactionId, int $amount): array
{
    try {
        $result = app(RegisterOrderPayment::class)->execute(
            Order::query()->findOrFail($orderId),
            $transactionId,
            'stripe',
            $amount,
        );

        return [
            'ok' => true,
            'transaction_id' => $transactionId,
            'status' => class_basename($result->status),
            'paid_total' => $result->getTotalPaid(),
            'order_paid_at' => $result->paid_at !== null,
        ];
    } catch (Throwable $exception) {
        return [
            'ok' => false,
            'transaction_id' => $transactionId,
            'error' => $exception::class,
            'message' => $exception->getMessage(),
        ];
    }
}

/**
 * @return array<string, mixed>
 */
function ordersEngineCancelOutcome(Order $order, string $reason): array
{
    try {
        $result = app(CancelOrder::class)->execute($order, $reason);

        return [
            'ok' => true,
            'status' => class_basename($result->status),
        ];
    } catch (Throwable $exception) {
        return [
            'ok' => false,
            'error' => $exception::class,
            'message' => $exception->getMessage(),
        ];
    }
}

/**
 * @return array<string, mixed>
 */
function ordersEngineCompleteRefundOutcome(string $refundId, string $providerTransactionId): array
{
    try {
        $order = app(RegisterOrderRefund::class)->completePending(
            OrderRefund::query()->findOrFail($refundId),
            $providerTransactionId,
        );
        $order->refresh();

        return [
            'ok' => true,
            'refunded_total' => $order->getTotalRefunded(),
            'pending_refunded_total' => $order->getTotalPendingRefunded(),
        ];
    } catch (Throwable $exception) {
        return [
            'ok' => false,
            'error' => $exception::class,
            'message' => $exception->getMessage(),
        ];
    }
}

/**
 * @return array<int, object>
 */
function ordersEngineOutboxRows(string $orderId): array
{
    return DB::table(config('orders.database.tables.order_outbox', 'order_outbox_messages'))
        ->where('order_id', $orderId)
        ->get()
        ->all();
}

/**
 * @param  array<string, array<string, mixed>>  $results
 * @return array<string, array<string, mixed>>
 */
function ordersEngineAccepted(array $results): array
{
    return array_filter($results, static fn (array $outcome): bool => $outcome['ok'] === true);
}

/**
 * ORD-02 engine race.
 *
 * Genuine multi-process races against PostgreSQL prove the payment
 * settlement contract holds when writers overlap on separate connections:
 * the outstanding-balance ceiling rejects one of two over-balance payments,
 * and a split settlement stages exactly one fulfillment pair.
 */
it('accepts exactly one of two concurrent payments that together exceed the balance', function (): void {
    $this->useEngine();

    for ($round = 0; $round < ordersEngineRepeats(); $round++) {
        $order = ordersEngineOrder(10000);
        $orderId = (string) $order->getKey();
        $transactionA = 'eng_overpay_a_' . Str::lower(Str::random(12));
        $transactionB = 'eng_overpay_b_' . Str::lower(Str::random(12));

        $results = EngineRace::run([
            'pay_a' => static function (string $workdir) use ($orderId, $transactionA): array {
                return ordersEnginePayOutcome($orderId, $transactionA, 6000);
            },
            'pay_b' => static function (string $workdir) use ($orderId, $transactionB): array {
                return ordersEnginePayOutcome($orderId, $transactionB, 6000);
            },
        ]);

        $accepted = ordersEngineAccepted($results);
        $rejected = array_filter($results, static fn (array $outcome): bool => $outcome['ok'] === false);

        expect($accepted)->toHaveCount(1)
            ->and($rejected)->toHaveCount(1);

        $winner = array_values($accepted)[0];
        $loser = array_values($rejected)[0];

        expect($winner['status'])->toBe('PendingPayment')
            ->and($winner['paid_total'])->toBe(6000)
            ->and($winner['order_paid_at'])->toBeFalse()
            ->and($loser['error'])->toBe(InvalidArgumentException::class)
            ->and($loser['message'])->toContain('outstanding balance');

        $order->refresh();

        expect($order->status)->toBeInstanceOf(PendingPayment::class)
            ->and($order->getTotalPaid())->toBe(6000)
            ->and($order->isFullyPaid())->toBeFalse()
            ->and($order->paid_at)->toBeNull()
            ->and($order->payments()->count())->toBe(1)
            ->and(ordersEngineOutboxRows($orderId))->toHaveCount(0);
    }
});

it('settles once when two concurrent payments complete the order balance', function (): void {
    $this->useEngine();

    for ($round = 0; $round < ordersEngineRepeats(); $round++) {
        $order = ordersEngineOrder(10000);
        $orderId = (string) $order->getKey();
        $transactionA = 'eng_split_a_' . Str::lower(Str::random(12));
        $transactionB = 'eng_split_b_' . Str::lower(Str::random(12));

        $results = EngineRace::run([
            'pay_a' => static function (string $workdir) use ($orderId, $transactionA): array {
                return ordersEnginePayOutcome($orderId, $transactionA, 5000);
            },
            'pay_b' => static function (string $workdir) use ($orderId, $transactionB): array {
                return ordersEnginePayOutcome($orderId, $transactionB, 5000);
            },
        ]);

        expect($results['pay_a']['ok'])->toBeTrue()
            ->and($results['pay_b']['ok'])->toBeTrue();

        $statuses = [
            'pay_a' => $results['pay_a']['status'],
            'pay_b' => $results['pay_b']['status'],
        ];

        expect(array_values($statuses))->toEqualCanonicalizing(['PendingPayment', 'Processing']);

        $settler = array_search('Processing', $statuses, true);

        expect($settler)->not->toBeFalse();

        $order->refresh();

        expect($order->status)->toBeInstanceOf(Processing::class)
            ->and($order->getTotalPaid())->toBe(10000)
            ->and($order->isFullyPaid())->toBeTrue()
            ->and($order->paid_at)->not->toBeNull()
            ->and($order->payments()->count())->toBe(2);

        // Only the settling payment stages fulfillment, exactly once.
        $rows = ordersEngineOutboxRows($orderId);

        expect($rows)->toHaveCount(2)
            ->and(array_values(array_unique(array_column($rows, 'transaction_id'))))
            ->toBe([$results[$settler]['transaction_id']])
            ->and(array_values(array_unique(array_column($rows, 'event_class'))))
            ->toEqualCanonicalizing([OrderProcessingStarted::class, OrderFulfillmentRequired::class]);
    }
});

/**
 * ORD-03 engine race.
 *
 * A cancellation reserves the remaining refundable balance while a pending
 * refund may complete concurrently; both interleavings must keep settled
 * plus pending refunds equal to the money actually received.
 */
it('keeps settled plus pending refunds equal to paid when cancel races a refund completion', function (): void {
    $this->useEngine();

    for ($round = 0; $round < ordersEngineRepeats(); $round++) {
        $order = ordersEngineOrder(10000);
        $orderId = (string) $order->getKey();

        app(RegisterOrderPayment::class)->execute(
            $order,
            'eng_full_' . Str::lower(Str::random(12)),
            'stripe',
            10000,
        );
        $order->refresh();

        expect($order->status)->toBeInstanceOf(Processing::class);

        $pendingRefund = app(RegisterOrderRefund::class)->createPending(
            $order,
            3000,
            'eng_refund_' . Str::lower(Str::random(12)),
            'Engine race partial refund',
        );

        $refundId = (string) $pendingRefund[0]->getKey();

        $results = EngineRace::run([
            'complete' => static function (string $workdir) use ($refundId): array {
                return ordersEngineCompleteRefundOutcome($refundId, 'eng_provider_' . Str::lower(Str::random(12)));
            },
            'cancel' => static function (string $workdir) use ($orderId): array {
                return ordersEngineCancelOutcome(
                    Order::query()->findOrFail($orderId),
                    'Engine race cancellation',
                );
            },
        ]);

        expect($results['complete']['ok'])->toBeTrue()
            ->and($results['cancel']['ok'])->toBeTrue();

        $order->refresh();

        expect($order->status)->toBeInstanceOf(Canceled::class);

        $refunds = $order->refunds()->get();
        $payment = $order->payments()->sole();

        expect($refunds)->toHaveCount(2)
            ->and((int) $refunds->sum('amount'))->toBe(10000)
            ->and((int) $refunds->sum('amount'))->toBeLessThanOrEqual((int) $payment->amount)
            ->and($refunds->where('status', RefundStatus::Completed)->count())->toBe(1)
            ->and($refunds->where('status', RefundStatus::Pending)->count())->toBe(1)
            ->and($order->getTotalRefunded() + $order->getTotalPendingRefunded())->toBe(10000)
            ->and($order->getRemainingRefundable())->toBe(0);
    }
});

it('records a single cancellation reservation when two cancels race', function (): void {
    $this->useEngine();

    for ($round = 0; $round < ordersEngineRepeats(); $round++) {
        $order = ordersEngineOrder(10000);
        $orderId = (string) $order->getKey();

        app(RegisterOrderPayment::class)->execute(
            $order,
            'eng_cancel_full_' . Str::lower(Str::random(12)),
            'stripe',
            10000,
        );
        $order->refresh();

        expect($order->status)->toBeInstanceOf(Processing::class);

        // Both workers hold the same pre-race instance, mirroring two admin
        // requests that loaded the order while it was still cancellable.
        $results = EngineRace::run([
            'cancel_a' => static function (string $workdir) use ($order): array {
                return ordersEngineCancelOutcome($order, 'Engine race duplicate cancel');
            },
            'cancel_b' => static function (string $workdir) use ($order): array {
                return ordersEngineCancelOutcome($order, 'Engine race duplicate cancel');
            },
        ]);

        $accepted = ordersEngineAccepted($results);
        $rejected = array_filter($results, static fn (array $outcome): bool => $outcome['ok'] === false);

        expect($accepted)->toHaveCount(1)
            ->and($rejected)->toHaveCount(1);

        $loser = array_values($rejected)[0];

        expect($loser['error'])->toBe(TransitionNotFound::class);

        $order->refresh();

        expect($order->status)->toBeInstanceOf(Canceled::class)
            ->and($order->canceled_at)->not->toBeNull();

        $payment = $order->payments()->sole();
        $refunds = $order->refunds()->get();

        expect($refunds)->toHaveCount(1);

        $reservation = $refunds->sole();

        expect($reservation->status)->toBe(RefundStatus::Pending)
            ->and((int) $reservation->amount)->toBe(10000)
            ->and((string) $reservation->allocation_key)->toBe(sprintf('cancel:%s:%s', $orderId, $payment->getKey()))
            ->and($order->getTotalPendingRefunded())->toBe(10000)
            ->and($order->getTotalRefunded())->toBe(0)
            ->and($order->getRemainingRefundable())->toBe(0);
    }
});
