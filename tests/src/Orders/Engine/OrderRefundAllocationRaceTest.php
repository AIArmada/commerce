<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Orders\OrdersEngineTestCase;
use AIArmada\Orders\Actions\RegisterOrderPayment;
use AIArmada\Orders\Actions\RegisterOrderRefund;
use AIArmada\Orders\Enums\PaymentStatus;
use AIArmada\Orders\Enums\RefundStatus;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderRefund;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use Illuminate\Support\Str;
use Tests\Support\EngineRace;

uses(OrdersEngineTestCase::class);

function ord04EngineRepeats(): int
{
    return 5;
}

function ord04EngineOrder(int $grandTotal = 10000): Order
{
    return Order::create([
        'order_number' => 'ORD-ORD04-ENG-' . mb_strtoupper(Str::random(10)),
        'status' => PendingPayment::class,
        'currency' => 'MYR',
        'subtotal' => $grandTotal,
        'grand_total' => $grandTotal,
    ])->refresh();
}

/**
 * @return array<string, mixed>
 */
function ord04EngineSplitOutcome(string $orderId, string $transactionId, int $amount): array
{
    try {
        $refunds = app(RegisterOrderRefund::class)->createPending(
            Order::query()->findOrFail($orderId),
            $amount,
            $transactionId,
            'Engine allocation race',
        );

        $ids = array_map(static fn (OrderRefund $refund): string => (string) $refund->getKey(), $refunds);
        $amounts = array_map(static fn (OrderRefund $refund): int => (int) $refund->amount, $refunds);

        sort($amounts);

        return [
            'ok' => true,
            'transaction_id' => $transactionId,
            'ids' => $ids,
            'amounts' => $amounts,
            'total' => array_sum($amounts),
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
 * @param  list<string>  $refundIds
 * @return array<string, mixed>
 */
function ord04EngineCompleteAllOutcome(array $refundIds, string $providerPrefix): array
{
    try {
        $order = null;

        foreach (array_values($refundIds) as $index => $refundId) {
            $order = app(RegisterOrderRefund::class)->completePending(
                OrderRefund::query()->findOrFail($refundId),
                sprintf('%s-%d', $providerPrefix, $index),
            );
        }

        $order?->refresh();

        return [
            'ok' => true,
            'refunded_total' => $order?->getTotalRefunded(),
            'pending_refunded_total' => $order?->getTotalPendingRefunded(),
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
 * ORD-04 engine race.
 *
 * Two concurrent 6,000 refund requests on a 10,000 order settled by two
 * 5,000 receipts can never both pass: the order lock serializes capacity
 * checks and the winner stays within each referenced payment.
 */
it('accepts exactly one of two concurrent refunds that together exceed the received money', function (): void {
    $this->useEngine();

    for ($round = 0; $round < ord04EngineRepeats(); $round++) {
        $order = ord04EngineOrder(10000);
        $orderId = (string) $order->getKey();

        $payments = app(RegisterOrderPayment::class);
        $payments->execute($order, 'ord04e_over_a_' . Str::lower(Str::random(12)), 'stripe', 5000);
        $payments->execute($order->refresh(), 'ord04e_over_b_' . Str::lower(Str::random(12)), 'chip', 5000);
        $order->refresh();

        expect($order->status)->toBeInstanceOf(Processing::class);

        $keyA = 'ord04e_alloc_a_' . Str::lower(Str::random(12));
        $keyB = 'ord04e_alloc_b_' . Str::lower(Str::random(12));

        $results = EngineRace::run([
            'refund_a' => static function (string $workdir) use ($orderId, $keyA): array {
                return ord04EngineSplitOutcome($orderId, $keyA, 6000);
            },
            'refund_b' => static function (string $workdir) use ($orderId, $keyB): array {
                return ord04EngineSplitOutcome($orderId, $keyB, 6000);
            },
        ]);

        $accepted = array_filter($results, static fn (array $outcome): bool => $outcome['ok'] === true);
        $rejected = array_filter($results, static fn (array $outcome): bool => $outcome['ok'] === false);

        expect($accepted)->toHaveCount(1)
            ->and($rejected)->toHaveCount(1);

        $winner = array_values($accepted)[0];
        $loser = array_values($rejected)[0];

        expect($winner['total'])->toBe(6000)
            ->and($winner['amounts'])->toBe([1000, 5000])
            ->and($loser['error'])->toBe(InvalidArgumentException::class)
            ->and($loser['message'])->toContain('remaining refundable amount');

        $order->refresh();
        $refunds = $order->refunds()->get();

        expect($refunds)->toHaveCount(2)
            ->and((int) $refunds->sum('amount'))->toBe(6000)
            ->and($order->getTotalPendingRefunded())->toBe(6000)
            ->and($order->getTotalRefunded())->toBe(0)
            ->and($order->getRemainingRefundable())->toBe(4000);

        foreach ($refunds as $refund) {
            $payment = $refund->payment;

            expect($payment)->not->toBeNull()
                ->and((int) $refund->amount)->toBeLessThanOrEqual((int) $payment->amount)
                ->and($refund->gateway)->toBe($payment->gateway);
        }
    }
});

/**
 * ORD-04 engine race.
 *
 * Concurrent retries of the same split-refund request (same allocation
 * key) reserve exactly one set of per-payment allocations.
 */
it('reserves one split refund set when the same request retries concurrently', function (): void {
    $this->useEngine();

    for ($round = 0; $round < ord04EngineRepeats(); $round++) {
        $order = ord04EngineOrder(10000);
        $orderId = (string) $order->getKey();

        $payments = app(RegisterOrderPayment::class);
        $payments->execute($order, 'ord04e_dup_a_' . Str::lower(Str::random(12)), 'stripe', 5000);
        $payments->execute($order->refresh(), 'ord04e_dup_b_' . Str::lower(Str::random(12)), 'chip', 5000);
        $order->refresh();

        $key = 'ord04e_dup_' . Str::lower(Str::random(12));

        $results = EngineRace::run([
            'retry_a' => static function (string $workdir) use ($orderId, $key): array {
                return ord04EngineSplitOutcome($orderId, $key, 7000);
            },
            'retry_b' => static function (string $workdir) use ($orderId, $key): array {
                return ord04EngineSplitOutcome($orderId, $key, 7000);
            },
        ]);

        expect($results['retry_a']['ok'])->toBeTrue()
            ->and($results['retry_b']['ok'])->toBeTrue();

        expect($results['retry_a']['ids'])->toEqualCanonicalizing($results['retry_b']['ids'])
            ->and($results['retry_a']['amounts'])->toBe([2000, 5000])
            ->and($results['retry_b']['amounts'])->toBe([2000, 5000]);

        $order->refresh();
        $refunds = $order->refunds()->get();

        expect($refunds)->toHaveCount(2)
            ->and((int) $refunds->sum('amount'))->toBe(7000)
            ->and($order->getTotalPendingRefunded())->toBe(7000)
            ->and($order->getRemainingRefundable())->toBe(3000);
    }
});

/**
 * ORD-04 engine race.
 *
 * Completing a pending allocation while a new refund allocates the order
 * remainder must never overshoot either dimension: the completed receipt
 * keeps consuming its capacity, so the new request lands on the other
 * receipt and each receipt's refunds stay within its own amount.
 */
it('keeps order and payment capacity when a completion races a new refund', function (): void {
    $this->useEngine();

    for ($round = 0; $round < ord04EngineRepeats(); $round++) {
        $order = ord04EngineOrder(10000);
        $orderId = (string) $order->getKey();

        $payments = app(RegisterOrderPayment::class);
        $payments->execute($order, 'ord04e_race_a_' . Str::lower(Str::random(12)), 'stripe', 5000);
        $payments->execute($order->refresh(), 'ord04e_race_b_' . Str::lower(Str::random(12)), 'chip', 5000);
        $order->refresh();

        $firstPayment = $order->payments()->orderBy('created_at')->orderBy('id')->firstOrFail();

        $pending = app(RegisterOrderRefund::class)->createPending(
            $order,
            5000,
            'ord04e_hold_' . Str::lower(Str::random(12)),
            'Engine hold',
            [],
            $firstPayment,
        );

        $pendingIds = array_map(static fn (OrderRefund $refund): string => (string) $refund->getKey(), $pending);
        $providerPrefix = 'ord04e_provider_' . Str::lower(Str::random(12));
        $newKey = 'ord04e_new_' . Str::lower(Str::random(12));

        expect($pendingIds)->toHaveCount(1);

        $results = EngineRace::run([
            'complete' => static function (string $workdir) use ($pendingIds, $providerPrefix): array {
                return ord04EngineCompleteAllOutcome($pendingIds, $providerPrefix);
            },
            'allocate' => static function (string $workdir) use ($orderId, $newKey): array {
                return ord04EngineSplitOutcome($orderId, $newKey, 5000);
            },
        ]);

        expect($results['complete']['ok'])->toBeTrue()
            ->and($results['allocate']['ok'])->toBeTrue()
            ->and($results['allocate']['total'])->toBe(5000);

        $order->refresh();
        $refunds = $order->refunds()->get();

        expect($refunds)->toHaveCount(2)
            ->and((int) $refunds->sum('amount'))->toBe(10000)
            ->and($order->getTotalRefunded())->toBe(5000)
            ->and($order->getTotalPendingRefunded())->toBe(5000)
            ->and($order->getRemainingRefundable())->toBe(0);

        $completed = $refunds->where('status', RefundStatus::Completed)->sole();
        $pendingRefund = $refunds->where('status', RefundStatus::Pending)->sole();

        expect($completed->payment_id)->toBe($firstPayment->getKey())
            ->and($pendingRefund->payment_id)->not->toBe($firstPayment->getKey())
            ->and($firstPayment->refresh()->status)->toBe(PaymentStatus::Refunded)
            ->and($pendingRefund->payment->refresh()->status)->toBe(PaymentStatus::Completed);

        foreach ($order->payments()->get() as $payment) {
            $attributed = (int) $order->refunds()
                ->where('payment_id', $payment->getKey())
                ->whereIn('status', [RefundStatus::Pending->value, RefundStatus::Completed->value])
                ->sum('amount');

            expect($attributed)->toBeLessThanOrEqual((int) $payment->amount);
        }
    }
});
