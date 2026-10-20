<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Contracts\OwnerResolverInterface;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Actions\RegisterOrderRefund;
use AIArmada\Orders\Enums\PaymentStatus;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderPayment;
use AIArmada\Orders\States\Processing;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * ORD-05 regressions: a caller-supplied payment is only an identity
 * reference. Every allocation decision must be made from the authoritative
 * row resolved through the locked order's owner-safe relationship.
 */
beforeEach(function (): void {
    config()->set('orders.owner.enabled', false);
    config()->set('orders.owner.auto_assign_on_create', false);
});

function ord05Order(int $grandTotal = 10000, string $currency = 'MYR'): Order
{
    return Order::create([
        'order_number' => 'ORD-ORD05-' . mb_strtoupper(uniqid()),
        'status' => Processing::class,
        'currency' => $currency,
        'subtotal' => $grandTotal,
        'grand_total' => $grandTotal,
    ])->refresh();
}

function ord05Receipt(
    Order $order,
    string $transactionId,
    int $amount,
    string $gateway = 'stripe',
    string $currency = 'MYR',
): OrderPayment {
    return OrderPayment::create([
        'order_id' => $order->getKey(),
        'gateway' => $gateway,
        'transaction_id' => $transactionId,
        'amount' => $amount,
        'currency' => $currency,
        'status' => PaymentStatus::Completed,
        'paid_at' => now(),
    ])->refresh();
}

it('reserves a specified refund against the persisted payment amount, not a stale snapshot', function (): void {
    $order = ord05Order();
    $first = ord05Receipt($order, 'ord05-stale-amount-a', 5000, 'stripe');
    ord05Receipt($order, 'ord05-stale-amount-b', 5000, 'chip');
    $order->refresh();

    $stale = $first->fresh();

    // Persist a smaller amount through another instance: the order paid
    // total must drop and the first receipt's remaining capacity is 2,000.
    $first->fresh()->update(['amount' => 2000]);
    $order->refresh();

    expect($order->getTotalPaid())->toBe(7000);

    expect(fn (): array => app(RegisterOrderRefund::class)->createPending(
        $order,
        5000,
        'ord05-stale-amount-key',
        'Stale amount',
        [],
        $stale,
    ))->toThrow(InvalidArgumentException::class, 'specified payment');

    expect($order->refresh()->refunds()->count())->toBe(0)
        ->and($order->getTotalPendingRefunded())->toBe(0)
        ->and($order->getRemainingRefundable())->toBe(7000);

    // The persisted capacity still funds a refund up to 2,000.
    $allocated = app(RegisterOrderRefund::class)->createPending(
        $order->refresh(),
        2000,
        'ord05-stale-amount-ok',
        'Persisted capacity',
        [],
        $stale,
    );

    expect($allocated)->toHaveCount(1)
        ->and((int) $allocated[0]->amount)->toBe(2000)
        ->and($allocated[0]->payment_id)->toBe($first->getKey())
        ->and($stale->refresh()->amount)->toBe(2000);
});

it('rejects a specified refund when the payment no longer received money', function (): void {
    $order = ord05Order();
    $first = ord05Receipt($order, 'ord05-stale-status-a', 5000, 'stripe');
    $order->refresh();

    $stale = $first->fresh();

    $first->fresh()->markAsFailed('Declined by provider');
    $order->refresh();

    expect(fn (): array => app(RegisterOrderRefund::class)->createPending(
        $order,
        5000,
        'ord05-stale-status-key',
        'Stale status',
        [],
        $stale,
    ))->toThrow(InvalidArgumentException::class, 'The specified payment has not received any refundable money.');

    expect($order->refresh()->refunds()->count())->toBe(0)
        ->and($order->getTotalPendingRefunded())->toBe(0)
        ->and($stale->refresh()->status)->toBe(PaymentStatus::Failed);
});

it('rejects a specified refund when the payment was deleted after it was read', function (): void {
    $order = ord05Order();
    $first = ord05Receipt($order, 'ord05-deleted-a', 5000, 'stripe');
    ord05Receipt($order, 'ord05-deleted-b', 5000, 'chip');
    $order->refresh();

    $stale = $first->fresh();

    $first->delete();
    $order->refresh();

    expect(fn (): array => app(RegisterOrderRefund::class)->createPending(
        $order,
        2000,
        'ord05-deleted-key',
        'Deleted payment',
        [],
        $stale,
    ))->toThrow(InvalidArgumentException::class, 'same order');

    expect($order->refresh()->refunds()->count())->toBe(0)
        ->and($order->getTotalPendingRefunded())->toBe(0)
        ->and($order->getRemainingRefundable())->toBe(5000);
});

it('treats an unsaved or unknown payment model as an identity reference only', function (): void {
    $order = ord05Order();
    ord05Receipt($order, 'ord05-unsaved-a', 5000, 'stripe');
    $order->refresh();

    $unsaved = new OrderPayment([
        'order_id' => $order->getKey(),
        'gateway' => 'stripe',
        'amount' => 5000,
        'currency' => 'MYR',
        'status' => PaymentStatus::Completed,
    ]);

    expect(fn (): array => app(RegisterOrderRefund::class)->createPending(
        $order,
        5000,
        'ord05-unsaved-key',
        'Unsaved payment',
        [],
        $unsaved,
    ))->toThrow(InvalidArgumentException::class, 'same order');

    $unknown = (new OrderPayment)->forceFill([
        'id' => (string) Str::uuid(),
        'order_id' => $order->getKey(),
        'gateway' => 'stripe',
        'amount' => 5000,
        'currency' => 'MYR',
        'status' => PaymentStatus::Completed,
    ]);

    expect(fn (): array => app(RegisterOrderRefund::class)->createPending(
        $order,
        5000,
        'ord05-unknown-key',
        'Unknown payment',
        [],
        $unknown,
    ))->toThrow(InvalidArgumentException::class, 'same order');

    expect($order->refresh()->refunds()->count())->toBe(0)
        ->and($order->getTotalPendingRefunded())->toBe(0);
});

it('ignores forged amount and status fields on a supplied payment model', function (): void {
    $order = ord05Order();
    ord05Receipt($order, 'ord05-forged-small', 2000, 'stripe');
    ord05Receipt($order, 'ord05-forged-large', 5000, 'chip');
    $order->refresh();

    $small = $order->payments()->where('gateway', 'stripe')->sole();

    $forged = $small->fresh();
    $forged->forceFill(['amount' => 9000, 'status' => PaymentStatus::Completed]);

    expect(fn (): array => app(RegisterOrderRefund::class)->createPending(
        $order,
        5000,
        'ord05-forged-key',
        'Forged fields',
        [],
        $forged,
    ))->toThrow(InvalidArgumentException::class, 'specified payment');

    expect($order->refresh()->refunds()->count())->toBe(0)
        ->and($order->getTotalPendingRefunded())->toBe(0)
        ->and($order->getRemainingRefundable())->toBe(7000);
});

it('uses the persisted currency instead of a forged currency on the supplied model', function (): void {
    $order = ord05Order();
    $first = ord05Receipt($order, 'ord05-stale-currency-a', 5000, 'stripe', 'MYR');
    $order->refresh();

    $forged = $first->fresh();
    $forged->forceFill(['currency' => 'USD']);

    $allocated = app(RegisterOrderRefund::class)->createPending(
        $order->refresh(),
        2000,
        'ord05-stale-currency-key',
        'Forged currency',
        [],
        $forged,
    );

    expect($allocated)->toHaveCount(1)
        ->and($allocated[0]->payment_id)->toBe($first->getKey())
        ->and($allocated[0]->currency)->toBe('MYR');

    expect($order->refresh()->getTotalPendingRefunded())->toBe(2000);
});

it('rejects a payment that belongs to another order', function (): void {
    $order = ord05Order();
    ord05Receipt($order, 'ord05-foreign-a', 5000, 'stripe');
    $order->refresh();

    $other = ord05Order(2000);
    $foreign = ord05Receipt($other, 'ord05-foreign-b', 2000, 'chip');

    expect(fn (): array => app(RegisterOrderRefund::class)->createPending(
        $order,
        1000,
        'ord05-foreign-key',
        'Foreign payment',
        [],
        $foreign,
    ))->toThrow(InvalidArgumentException::class, 'same order');

    expect($order->refresh()->refunds()->count())->toBe(0);
});

it('rejects a forged parentage pointing at a payment owned by another owner', function (): void {
    Schema::dropIfExists('test_owners_ord05');

    Schema::create('test_owners_ord05', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    config()->set('orders.owner.enabled', true);
    config()->set('orders.owner.include_global', false);

    app()->instance(OwnerResolverInterface::class, new class implements OwnerResolverInterface
    {
        public function resolve(): ?Model
        {
            return null;
        }
    });

    $ownerA = TestOwnerOrd05::query()->create(['name' => 'Owner A']);
    $ownerB = TestOwnerOrd05::query()->create(['name' => 'Owner B']);

    $orderA = OwnerContext::withOwner($ownerA, function () use ($ownerA): Order {
        $order = Order::query()->make([
            'order_number' => 'ORD-ORD05-OWNER-A-' . mb_strtoupper(uniqid()),
            'status' => Processing::class,
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
        ]);
        $order->assignOwner($ownerA);
        $order->save();

        return $order;
    });

    $paymentA = OwnerContext::withOwner($ownerA, fn (): OrderPayment => OrderPayment::create([
        'order_id' => $orderA->getKey(),
        'gateway' => 'stripe',
        'transaction_id' => 'ord05-owner-a',
        'amount' => 5000,
        'currency' => 'MYR',
        'status' => PaymentStatus::Completed,
        'paid_at' => now(),
    ]));

    $orderB = OwnerContext::withOwner($ownerB, function () use ($ownerB): Order {
        $order = Order::query()->make([
            'order_number' => 'ORD-ORD05-OWNER-B-' . mb_strtoupper(uniqid()),
            'status' => Processing::class,
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
        ]);
        $order->assignOwner($ownerB);
        $order->save();

        return $order;
    });

    $paymentB = OwnerContext::withOwner($ownerB, fn (): OrderPayment => OrderPayment::create([
        'order_id' => $orderB->getKey(),
        'gateway' => 'chip',
        'transaction_id' => 'ord05-owner-b',
        'amount' => 5000,
        'currency' => 'MYR',
        'status' => PaymentStatus::Completed,
        'paid_at' => now(),
    ]));

    // Forge the parentage so only owner-safe identity resolution can reject it.
    $forged = $paymentB->fresh();
    $forged->forceFill(['order_id' => $orderA->getKey()]);

    OwnerContext::withOwner($ownerA, function () use ($orderA, $forged): void {
        expect(fn (): array => app(RegisterOrderRefund::class)->createPending(
            $orderA,
            2000,
            'ord05-owner-forgery-key',
            'Owner forgery',
            [],
            $forged,
        ))->toThrow(InvalidArgumentException::class, 'same order');
    });

    OwnerContext::withOwner($ownerA, function () use ($orderA, $paymentA): void {
        expect($orderA->refresh()->refunds()->count())->toBe(0)
            ->and($orderA->getTotalPendingRefunded())->toBe(0)
            ->and($paymentA->refresh()->status)->toBe(PaymentStatus::Completed);
    });

    expect($paymentB->refresh()->owner_id)->toBe($ownerB->getKey());
});

it('never lets pending plus completed refunds exceed the persisted receipt capacity', function (): void {
    $order = ord05Order();
    $first = ord05Receipt($order, 'ord05-capacity-a', 5000, 'stripe');
    $second = ord05Receipt($order, 'ord05-capacity-b', 5000, 'chip');
    $order->refresh();

    $stale = $first->fresh();

    $first->fresh()->update(['amount' => 2000]);
    $order->refresh();

    // FIFO across the persisted rows: 2,000 on the first receipt, 4,000
    // on the second.
    $pending = app(RegisterOrderRefund::class)->createPending(
        $order,
        6000,
        'ord05-capacity-key',
        'FIFO capacity',
    );

    $byPayment = [];

    foreach ($pending as $refund) {
        $byPayment[(string) $refund->payment_id] = $refund;
    }

    expect($pending)->toHaveCount(2)
        ->and((int) $byPayment[$first->getKey()]->amount)->toBe(2000)
        ->and((int) $byPayment[$second->getKey()]->amount)->toBe(4000)
        ->and($order->refresh()->getTotalPendingRefunded())->toBe(6000);

    // A late retry that still carries the stale 5,000 snapshot must not
    // over-reserve the first receipt beyond its persisted 2,000.
    expect(fn (): array => app(RegisterOrderRefund::class)->createPending(
        $order,
        1000,
        'ord05-capacity-late',
        'Late stale retry',
        [],
        $stale,
    ))->toThrow(InvalidArgumentException::class, 'specified payment');

    $order->refresh();

    expect($order->refunds()->count())->toBe(2)
        ->and($order->getTotalPendingRefunded())->toBe(6000)
        ->and((int) $order->refunds()->where('payment_id', $first->getKey())->sum('amount'))->toBe(2000);
});

it('applies identity resolution to immediate refunds', function (): void {
    $order = ord05Order();
    $first = ord05Receipt($order, 'ord05-now-a', 5000, 'stripe');
    ord05Receipt($order, 'ord05-now-b', 5000, 'chip');
    $order->refresh();

    $stale = $first->fresh();

    $first->fresh()->update(['amount' => 2000]);
    $order->refresh();

    expect(fn (): Order => app(RegisterOrderRefund::class)->execute(
        $order,
        5000,
        'ord05-now-stale',
        'Immediate stale amount',
        [],
        $stale,
    ))->toThrow(InvalidArgumentException::class, 'specified payment');

    expect($order->refresh()->refunds()->count())->toBe(0)
        ->and($order->getTotalRefunded())->toBe(0);

    $result = app(RegisterOrderRefund::class)->execute(
        $order->refresh(),
        2000,
        'ord05-now-ok',
        'Immediate persisted capacity',
        [],
        $stale,
    );

    $refund = $result->refunds()->sole();

    expect($refund->payment_id)->toBe($first->getKey())
        ->and((int) $refund->amount)->toBe(2000)
        ->and($result->refresh()->getTotalRefunded())->toBe(2000);
});

class TestOwnerOrd05 extends Model
{
    use HasUuids;

    protected $table = 'test_owners_ord05';

    protected $fillable = ['name'];
}
