<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Orders\Actions\RegisterOrderRefund;
use AIArmada\Orders\Enums\PaymentStatus;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\Models\OrderItem;
use AIArmada\Orders\Services\OrderService;
use AIArmada\Orders\States\Processing;
use AIArmada\Products\Models\Product;
use AIArmada\Products\Models\Variant;
use AIArmada\Shipping\Actions\ApproveReturnAuthorization;
use AIArmada\Shipping\Contracts\ShippingDriverInterface;
use AIArmada\Shipping\Data\CarrierOperationResult;
use AIArmada\Shipping\Data\ShipmentData;
use AIArmada\Shipping\Data\ShipmentItemData;
use AIArmada\Shipping\Integrations\OrderFulfillmentHandler;
use AIArmada\Shipping\Integrations\PackageReturns;
use AIArmada\Shipping\Models\ReturnAuthorization;
use AIArmada\Shipping\Models\Shipment;
use AIArmada\Shipping\Models\ShipmentItem;
use AIArmada\Shipping\Services\ShipmentService;
use AIArmada\Shipping\ShippingManager;
use AIArmada\Shipping\States\ReturnAuthorizationState\RmaCompleted;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function (): void {
    config([
        'orders.owner.enabled' => false,
        'orders.owner.auto_assign_on_create' => false,
        'shipping.features.owner.enabled' => false,
        'products.owner.enabled' => false,
        'inventory.owner.enabled' => false,
    ]);
});

/** @return array{Order, OrderItem, Variant, Product} */
function orderWithPackageUnits(string $status = Processing::class): array
{
    $parent = Product::factory()->create(['name' => 'Frozen first component']);
    $first = Variant::factory()->for($parent)->create();
    $second = Product::factory()->create(['name' => 'Frozen second component']);
    $snapshot = fn (Product | Variant $product, int $quantity): array => [
        'product_id' => $product instanceof Variant ? $product->product_id : $product->id,
        'variant_id' => $product instanceof Variant ? $product->id : null,
        'inventoryable_type' => $product->getMorphClass(),
        'inventoryable_id' => $product->id,
        'quantity' => $quantity,
        'sku' => $product->sku,
        'name' => $product instanceof Variant ? $parent->name : $product->name,
        'weight' => 100,
        'dimensions' => null,
        'requires_shipping' => true,
        'tracks_inventory' => true,
    ];

    $order = Order::factory()->create([
        'status' => $status,
        'subtotal' => 2000,
        'discount_total' => 101,
        'tax_total' => 99,
        'shipping_total' => 500,
        'grand_total' => 2498,
    ]);
    $order->payments()->create([
        'gateway' => 'manual',
        'status' => PaymentStatus::Completed,
        'amount' => 2498,
        'currency' => 'MYR',
    ]);
    $order->attachAddress(Address::create([
        'line1' => '456 Shipping Road',
        'city' => 'Johor Bahru',
        'state' => 'Johor',
        'postcode' => '80000',
        'country_code' => 'MY',
    ]), type: 'shipping', isPrimary: true);

    $item = $order->items()->create([
        'name' => 'Package',
        'quantity' => 2,
        'unit_price' => 1000,
        'discount_amount' => 101,
        'tax_amount' => 99,
        'total' => 1998,
        'metadata' => ['components' => [$snapshot($first, 2), $snapshot($second, 1)]],
    ]);

    return [$order, $item, $first, $second];
}

function shipPackageUnit(Order $order, OrderItem $item, int $ordinal, array $extra = []): Shipment
{
    $result = app(OrderFulfillmentHandler::class)->createShipment($order, $extra + [
        'carrier' => 'manual',
        'package_units' => [$item->id => [$ordinal]],
    ]);

    expect($result['error'])->toBeNull();

    return Shipment::query()->findOrFail($result['shipment_id']);
}

it('ships complete units separately and keeps multiple parcels in the same shipment', function (): void {
    [$order, $item, $first] = orderWithPackageUnits();
    $first->product()->firstOrFail()->update(['name' => 'Changed after purchase']);

    $shipment = shipPackageUnit($order, $item, 2, ['parcels' => [['weight' => 200], ['weight' => 100]]]);
    expect($shipment->package_count)->toBe(2)
        ->and($shipment->items()->orderBy('package_component_index')->pluck('quantity')->all())->toBe([2, 1])
        ->and($shipment->items()->orderBy('package_component_index')->first()->name)->toBe('Frozen first component')
        ->and($shipment->items()->pluck('package_unit_ordinal')->unique()->values()->all())->toBe([2])
        ->and(shipPackageUnit($order, $item, 2)->id)->toBe($shipment->id);

    expect(fn () => app(OrderService::class)->ship($order, 'Manual', 'unlinked'))
        ->toThrow(DomainException::class, 'Use an order shipment');

    app(OrderService::class)->ship($order, 'Manual', $shipment->tracking_number, $shipment->id);
    expect($order->isShipped())->toBeFalse();

    $overlap = app(OrderFulfillmentHandler::class)->createShipment($order, ['package_units' => [$item->id => [1, 2]]]);
    expect($overlap['success'])->toBeFalse()
        ->and($overlap['error'])->toContain('already assigned');

    $other = shipPackageUnit($order, $item, 1);
    expect($other->id)->not->toBe($shipment->id);
    app(OrderService::class)->ship($order, 'Manual', $other->tracking_number, $other->id);
    expect($order->isShipped())->toBeTrue();
});

it('rejects an incomplete package shipment at the shipment service boundary', function (): void {
    [$order, $item] = orderWithPackageUnits();
    $shipment = shipPackageUnit($order, $item, 1);

    expect(fn () => app(ShipmentService::class)->create(ShipmentData::from([
        'reference' => 'partial-package',
        'carrierCode' => 'manual',
        'serviceCode' => 'standard',
        'origin' => $shipment->origin_address,
        'destination' => $shipment->destination_address,
        'shippableType' => $order->getMorphClass(),
        'shippableId' => $order->id,
        'items' => [new ShipmentItemData(
            name: 'Only one component',
            quantity: 2,
            orderItemId: $item->id,
            packageUnitOrdinal: 2,
            packageComponentIndex: 0,
        )],
    ]), $order->owner_id, $order->owner_type))->toThrow(DomainException::class, 'All components');
});

it('requires the complete inspected unit and settles its net price and tax exactly once', function (): void {
    [$order, $item, $first, $second] = orderWithPackageUnits();
    $shipment = shipPackageUnit($order, $item, 1);
    $returns = app(PackageReturns::class);
    $rma = $returns->request($shipment, $item->id, 1, 'defective');

    expect($returns->request($shipment, $item->id, 1, 'defective')->id)->toBe($rma->id);
    expect(fn () => $returns->request($shipment, $item->id, 2, 'defective'))->toThrow(DomainException::class);
    $refund = app(RegisterOrderRefund::class);
    $metadata = ['return_authorization_id' => $rma->id];

    expect(fn () => $refund->execute($order, 1000, 'refund-1', 'Return', $metadata))->toThrow(DomainException::class);
    expect(fn () => $refund->createPending($order, 1000, 'refund-1', 'Return'))->toThrow(DomainException::class, 'authorization');

    ApproveReturnAuthorization::run($rma);
    $lines = $rma->items()->get();
    $inspection = $lines->mapWithKeys(fn ($line): array => [$line->id => [
        'quantity' => $line->quantity_requested,
        'condition' => $line->quantity_requested === 2 ? 'mixed' : 'damaged',
        'restock_quantity' => $line->quantity_requested === 2 ? 1 : 0,
    ]])->all();

    $partial = $inspection;
    $partial[array_key_first($partial)]['quantity'] = 0;
    expect(fn () => $returns->receive($rma, $partial))->toThrow(DomainException::class, 'received and inspected')
        ->and(InventoryMovement::query()->where('reason', $rma->rma_number)->exists())->toBeFalse();

    $returns->receive($rma, $inspection);
    $returns->receive($rma, $inspection);
    $movements = InventoryMovement::query()->where('reason', $rma->rma_number)->get();
    expect($movements)->toHaveCount(1)
        ->and($movements->first()->inventoryable_id)->toBe($first->id)
        ->and($movements->first()->inventoryable_type)->toBe($first->getMorphClass())
        ->and($movements->first()->quantity)->toBe(1)
        ->and($movements->first()->inventoryable_id)->not->toBe($second->id);

    expect(fn () => $returns->complete($rma))->toThrow(DomainException::class, 'Settle');
    expect(fn () => $refund->createPending($order, 999, 'wrong-amount', 'Return', $metadata))->toThrow(DomainException::class, 'remaining net paid');

    $pending = $refund->createPending($order, 1000, 'refund-1', 'Return', $metadata)[0];
    expect($refund->createPending($order, 1000, 'refund-1', 'Return', $metadata)[0]->id)->toBe($pending->id)
        ->and($pending->metadata['package_unit'])->toBe(['order_item_id' => $item->id, 'ordinal' => 1]);
    expect(fn () => $refund->createPending($order, 1000, 'refund-again', 'Return', $metadata))->toThrow(DomainException::class, 'already');
    $refund->completePending($pending, 'provider-refund-1');
    $refund->completePending($pending, 'provider-refund-1');

    expect($returns->complete($rma)->status)->toBeInstanceOf(RmaCompleted::class)
        ->and($returns->complete($rma)->status)->toBeInstanceOf(RmaCompleted::class)
        ->and($order->fresh()->getTotalRefunded())->toBe(1000)
        ->and($order->refunds()->count())->toBe(1);
});

it('prevents a standalone return from referencing only one package component', function (): void {
    [$order, $item] = orderWithPackageUnits();
    $shipment = shipPackageUnit($order, $item, 1);
    $rma = ReturnAuthorization::create(['original_shipment_id' => $shipment->id, 'type' => 'refund', 'reason' => 'damaged']);

    expect(fn () => $rma->items()->create([
        'original_item_id' => $shipment->items()->firstOrFail()->id,
        'original_item_type' => new ShipmentItem()->getMorphClass(),
        'name' => 'Only one component',
    ]))->toThrow(DomainException::class, 'complete package unit');
});

it('reuses the same shipment after a definite carrier failure and blocks an ambiguous retry', function (): void {
    [$order, $item] = orderWithPackageUnits();
    $driver = Mockery::mock(ShippingDriverInterface::class);
    $reference = null;
    $driver->shouldReceive('createShipment')->once()->andReturnUsing(function (ShipmentData $data) use (&$reference) {
        $reference = $data->reference;

        return CarrierOperationResult::failed('Carrier refused the request.');
    });
    $driver->shouldReceive('createShipment')->once()->andReturnUsing(function (ShipmentData $data) use (&$reference) {
        expect($data->reference)->toBe($reference);

        return CarrierOperationResult::succeeded('TRACK-RETRY');
    });
    $driver->shouldReceive('createShipment')->once()->andReturn(CarrierOperationResult::unknown('Carrier timed out.'));
    app(ShippingManager::class)->extend('package-retry', fn () => $driver);
    config(['shipping.drivers.package-retry' => ['driver' => 'package-retry']]);

    $handler = app(OrderFulfillmentHandler::class);
    $selection = ['carrier' => 'package-retry', 'package_units' => [$item->id => [1]]];
    expect($handler->createShipment($order, $selection)['error'])->toContain('Carrier refused');
    expect($handler->createShipment($order, $selection)['tracking_number'])->toBe('TRACK-RETRY')
        ->and(Shipment::query()->where('shippable_id', $order->id)->count())->toBe(1);

    $selection['package_units'][$item->id] = [2];
    expect($handler->createShipment($order, $selection)['error'])->toContain('Carrier timed out');
    expect($handler->createShipment($order, $selection)['error'])->toContain('Reconcile')
        ->and(Shipment::query()->where('shippable_id', $order->id)->count())->toBe(2);
});

it('rejects a package return or refund when the current owner owns a different order', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    config(['orders.owner.enabled' => true, 'orders.owner.auto_assign_on_create' => true, 'shipping.features.owner.enabled' => true]);

    [$shipment, $order, $item, $rma] = OwnerContext::withOwner($owner, function (): array {
        [$order, $item] = orderWithPackageUnits();
        $shipment = shipPackageUnit($order, $item, 1);
        $rma = app(PackageReturns::class)->request($shipment, $item->id, 1, 'damaged');

        return [$shipment, $order, $item, $rma];
    });

    OwnerContext::withOwner($other, function () use ($shipment, $order, $item, $rma): void {
        expect(ReturnAuthorization::query()->find($rma->id))->toBeNull()
            ->and(Shipment::query()->find($shipment->id))->toBeNull();
        expect(fn () => app(PackageReturns::class)->request($shipment, $item->id, 1, 'damaged'))
            ->toThrow(ModelNotFoundException::class);
        expect(fn () => app(RegisterOrderRefund::class)->execute($order, 1000, 'foreign-refund', 'Return', [
            'return_authorization_id' => $rma->id,
        ]))->toThrow(RuntimeException::class, 'Cross-owner');
    });
});

it('retries only the unsettled part when a package refund spans multiple payments', function (): void {
    [$order, $item] = orderWithPackageUnits();
    $order->payments()->firstOrFail()->forceFill(['amount' => 600, 'created_at' => now()->subSecond()])->save();
    $order->payments()->create(['gateway' => 'manual', 'status' => PaymentStatus::Completed, 'amount' => 1898, 'currency' => 'MYR']);
    $shipment = shipPackageUnit($order, $item, 1);
    $returns = app(PackageReturns::class);
    $rma = ApproveReturnAuthorization::run($returns->request($shipment, $item->id, 1, 'damaged'));
    $inspection = $rma->items()->get()->mapWithKeys(fn ($line): array => [$line->id => [
        'quantity' => $line->quantity_approved,
        'condition' => 'damaged',
    ]])->all();
    $returns->receive($rma, $inspection);

    $refund = app(RegisterOrderRefund::class);
    $metadata = ['return_authorization_id' => $rma->id];
    $parts = $refund->createPending($order, 1000, 'split-refund', 'Return', $metadata);
    expect($parts)->toHaveCount(2);
    $refund->completePending($parts[0], 'settled-first-payment');
    $refund->failPending($parts[1], 'Payment provider declined');

    expect(fn () => $refund->createPending($order, 1000, 'retry-full', 'Return', $metadata))
        ->toThrow(DomainException::class, 'remaining net paid');
    $retry = $refund->createPending($order, $parts[1]->amount, 'retry-balance', 'Return', $metadata)[0];
    $refund->completePending($retry, 'settled-second-payment');

    expect($returns->complete($rma)->isCompleted())->toBeTrue()
        ->and($order->refresh()->getTotalRefunded())->toBe(1000);
});
