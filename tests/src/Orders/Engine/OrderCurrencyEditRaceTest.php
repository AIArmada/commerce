<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Orders\OrdersEngineTestCase;
use AIArmada\Orders\Actions\RegisterOrderPayment;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\PendingPayment;
use AIArmada\Orders\States\Processing;
use Illuminate\Support\Str;
use Tests\Support\EngineRace;

uses(OrdersEngineTestCase::class);

function ord08EngineRepeats(): int
{
    return 5;
}

function ord08EngineOrder(int $grandTotal = 10000): Order
{
    return Order::create([
        'order_number' => 'ORD-ORD08-ENG-' . mb_strtoupper(Str::random(10)),
        'status' => PendingPayment::class,
        'currency' => 'MYR',
        'subtotal' => $grandTotal,
        'grand_total' => $grandTotal,
    ])->refresh();
}

/**
 * @return array<string, mixed>
 */
function ord08EngineEditOutcome(string $orderId): array
{
    try {
        Order::query()->findOrFail($orderId)->update(['currency' => 'USD']);

        return ['ok' => true];
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
function ord08EnginePayOutcome(string $orderId, string $transactionId, int $amount): array
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
 * ORD-08 engine race.
 *
 * A currency edit racing a receipt creation must never re-denominate the
 * money: the edit is rejected in every interleaving and the settled receipt
 * stays in the currency the order was created with.
 */
it('rejects a currency edit racing a payment creation without re-denominating the receipt', function (): void {
    $this->useEngine();

    for ($round = 0; $round < ord08EngineRepeats(); $round++) {
        $order = ord08EngineOrder(10000);
        $orderId = (string) $order->getKey();
        $transactionId = 'eng_currency_' . Str::lower(Str::random(12));

        $results = EngineRace::run([
            'edit' => static function (string $workdir) use ($orderId): array {
                return ord08EngineEditOutcome($orderId);
            },
            'pay' => static function (string $workdir) use ($orderId, $transactionId): array {
                return ord08EnginePayOutcome($orderId, $transactionId, 10000);
            },
        ]);

        expect($results['edit']['ok'])->toBeFalse()
            ->and($results['edit']['error'])->toBe(InvalidArgumentException::class)
            ->and($results['edit']['message'])->toContain('currency is immutable after creation');

        expect($results['pay']['ok'])->toBeTrue()
            ->and($results['pay']['paid_total'])->toBe(10000);

        $order->refresh();
        $payment = $order->payments()->sole();

        expect($order->currency)->toBe('MYR')
            ->and($payment->currency)->toBe('MYR')
            ->and((int) $payment->amount)->toBe(10000)
            ->and($order->getTotalPaid())->toBe(10000)
            ->and($order->isFullyPaid())->toBeTrue()
            ->and($order->status)->toBeInstanceOf(Processing::class)
            ->and($order->paid_at)->not->toBeNull();
    }
});
