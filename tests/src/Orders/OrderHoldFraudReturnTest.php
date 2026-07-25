<?php

declare(strict_types=1);

use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Orders\Actions\FlagOrderAsFraud;
use AIArmada\Orders\Actions\HoldOrder;
use AIArmada\Orders\Actions\ReleaseOrderHold;
use AIArmada\Orders\Actions\ReturnOrder;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\Created;
use AIArmada\Orders\States\Delivered;
use AIArmada\Orders\States\Fraud;
use AIArmada\Orders\States\OnHold;
use AIArmada\Orders\States\Processing;
use AIArmada\Orders\States\Returned;

beforeEach(function (): void {
    config()->set('orders.owner.enabled', false);
    config()->set('orders.owner.auto_assign_on_create', false);
});

function makeOrder(string $stateClass): Order
{
    return Order::create([
        'order_number' => 'ORD-LC-' . uniqid(),
        'status' => $stateClass,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ]);
}

describe('HoldOrder', function (): void {
    it('places a processing order on hold and records held_at', function (): void {
        $order = makeOrder(Processing::class);

        $result = OwnerContext::withOwner(null, fn (): Order => (new HoldOrder)->execute($order, 'Manual review', 'admin@test.com'));

        expect($result->status)->toBeInstanceOf(OnHold::class);
        expect($result->held_at)->not->toBeNull();
        expect($result->isOnHold())->toBeTrue();
        expect($order->orderNotes)->toHaveCount(1);
    });

    it('throws when the order cannot be placed on hold', function (): void {
        $order = makeOrder(Created::class);

        expect(fn (): Order => (new HoldOrder)->execute($order, 'Too early'))
            ->toThrow(RuntimeException::class, 'cannot be placed on hold');
    });
});

describe('ReleaseOrderHold', function (): void {
    it('releases a held order back to processing and clears held_at', function (): void {
        $order = makeOrder(Processing::class);
        OwnerContext::withOwner(null, fn (): Order => (new HoldOrder)->execute($order, 'Review'));

        $result = OwnerContext::withOwner(null, fn (): Order => (new ReleaseOrderHold)->execute($order->refresh(), 'Approved'));

        expect($result->status)->toBeInstanceOf(Processing::class);
        expect($result->held_at)->toBeNull();
        expect($result->isOnHold())->toBeFalse();
    });

    it('throws when the order is not on hold', function (): void {
        $order = makeOrder(Processing::class);

        expect(fn (): Order => (new ReleaseOrderHold)->execute($order))
            ->toThrow(RuntimeException::class, 'is not on hold');
    });
});

describe('FlagOrderAsFraud', function (): void {
    it('flags a processing order as fraud and records flagged_at', function (): void {
        $order = makeOrder(Processing::class);

        $result = OwnerContext::withOwner(null, fn (): Order => (new FlagOrderAsFraud)->execute($order, 'Chargeback pattern', 'risk@test.com'));

        expect($result->status)->toBeInstanceOf(Fraud::class);
        expect($result->flagged_at)->not->toBeNull();
        expect($result->isFlaggedAsFraud())->toBeTrue();
    });

    it('throws when the order cannot be flagged', function (): void {
        $order = makeOrder(Created::class);

        expect(fn (): Order => (new FlagOrderAsFraud)->execute($order, 'Nope'))
            ->toThrow(RuntimeException::class, 'cannot be flagged as fraud');
    });
});

describe('ReturnOrder', function (): void {
    it('marks a delivered order as returned and records returned_at', function (): void {
        $order = makeOrder(Delivered::class);

        $result = OwnerContext::withOwner(null, fn (): Order => (new ReturnOrder)->execute($order, 'Damaged goods'));

        expect($result->status)->toBeInstanceOf(Returned::class);
        expect($result->returned_at)->not->toBeNull();
        expect($result->isReturned())->toBeTrue();
    });

    it('throws when the order cannot be returned', function (): void {
        $order = makeOrder(Processing::class);

        expect(fn (): Order => (new ReturnOrder)->execute($order))
            ->toThrow(RuntimeException::class, 'cannot be returned');
    });
});
