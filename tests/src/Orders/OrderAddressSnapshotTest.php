<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\AddressSnapshot;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Services\OrderService;
use AIArmada\Orders\States\Created;

beforeEach(function (): void {
    config()->set('orders.owner.enabled', false);
    config()->set('orders.owner.auto_assign_on_create', false);
    config()->set('orders.address_snapshots.enabled', false);
});

function createSnapshotOrder(): Order
{
    return Order::create([
        'order_number' => 'ORD-SNAP-' . uniqid(),
        'status' => Created::class,
        'currency' => 'MYR',
        'subtotal' => 0,
        'grand_total' => 0,
    ]);
}

function snapshotAddressData(): array
{
    return [
        'first_name' => 'Jane',
        'last_name' => 'Doe',
        'line1' => '12 Jalan Snapshot',
        'city' => 'KL',
        'postcode' => '50000',
        'country' => 'MY',
    ];
}

describe('Order address snapshots', function (): void {
    it('attaches a per-order copy without a snapshot by default', function (): void {
        $order = createSnapshotOrder();

        app(OrderService::class)->addAddress($order, snapshotAddressData(), 'shipping');

        expect($order->primaryAddress('shipping'))->not->toBeNull()
            ->and(AddressSnapshot::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    it('writes one immutable snapshot per type when enabled', function (): void {
        config()->set('orders.address_snapshots.enabled', true);

        $order = createSnapshotOrder();
        $service = app(OrderService::class);

        $service->addAddress($order, snapshotAddressData(), 'billing');
        $service->addAddress($order, snapshotAddressData(), 'shipping');

        $snapshots = AddressSnapshot::query()->withoutGlobalScopes()
            ->where('snapshotable_type', Order::class)
            ->where('snapshotable_id', $order->getKey())
            ->orderBy('reason')
            ->get();

        expect($snapshots)->toHaveCount(2)
            ->and($snapshots->pluck('reason')->all())->toBe(['order_billing', 'order_shipping'])
            ->and($snapshots->firstWhere('reason', 'order_shipping')->line1)->toBe('12 Jalan Snapshot')
            ->and($snapshots->firstWhere('reason', 'order_shipping')->city)->toBe('KL')
            ->and($snapshots->firstWhere('reason', 'order_shipping')->country_code)->toBe('MY');

        $copy = $order->primaryAddress('shipping');
        $copy->update(['line1' => 'Changed after the fact']);

        expect(AddressSnapshot::query()->withoutGlobalScopes()->where('reason', 'order_shipping')->value('line1'))
            ->toBe('12 Jalan Snapshot');
    });
});
