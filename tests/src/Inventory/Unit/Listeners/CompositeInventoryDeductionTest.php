<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\BatchStatus;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Features\Reservations\EntryPoints\Listeners\DeductInventoryFromOrder;
use AIArmada\Inventory\Features\Reservations\EntryPoints\Listeners\ReleaseInventoryFromOrder;
use AIArmada\Inventory\Features\Reservations\Models\InventoryOperation;
use AIArmada\Inventory\Features\Reservations\Persistence\InventoryAllocationService;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Features\Stock\Models\InventoryMovement;
use AIArmada\Inventory\Features\Traceability\Models\InventoryBatch;
use AIArmada\Inventory\Features\Traceability\Persistence\BatchAllocationService;
use AIArmada\Inventory\Features\Stock\Services\InventoryService;
use AIArmada\Orders\Events\InventoryDeductionRequired;
use AIArmada\Orders\Events\InventoryReleaseRequired;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\PendingPayment;

/**
 * @param  list<array{product: InventoryItem, quantity: int}>  $components
 * @return list<array<string, mixed>>
 */
function frozenOrderComponentSnapshot(array $components): array
{
    return array_map(static fn (array $component): array => [
        'product_id' => (string) $component['product']->getKey(),
        'variant_id' => null,
        'inventoryable_type' => $component['product']->getMorphClass(),
        'inventoryable_id' => (string) $component['product']->getKey(),
        'quantity' => $component['quantity'],
        'tracks_inventory' => true,
        'requires_shipping' => true,
        'name' => $component['product']->name,
        'sku' => 'SKU-' . (string) $component['product']->getKey(),
        'weight' => 250,
        'dimensions' => ['length' => 20, 'width' => 10, 'height' => 5],
    ], $components);
}

function createCompositeInventoryOrder(string $number, ?array $orderMetadata = null): Order
{
    $attributes = [
        'order_number' => $number,
        'status' => PendingPayment::class,
        'currency' => 'MYR',
        'subtotal' => 10000,
        'grand_total' => 10000,
    ];

    if ($orderMetadata !== null) {
        $attributes['metadata'] = $orderMetadata;
    }

    return Order::create($attributes);
}

beforeEach(function (): void {
    $this->inventoryService = app(InventoryService::class);
    $this->location = InventoryLocation::factory()->create([
        'name' => 'Order WH',
        'code' => 'ORDER-WH-' . uniqid(),
        'priority' => 100,
    ]);

    $this->mouse = InventoryItem::create(['name' => 'Order Mouse']);
    $this->cable = InventoryItem::create(['name' => 'Order Cable']);
    $this->parent = InventoryItem::create(['name' => 'Order Bundle']);

    $this->inventoryService->receive($this->mouse, $this->location->id, 10);
    $this->inventoryService->receive($this->cable, $this->location->id, 10);
    $this->inventoryService->receive($this->parent, $this->location->id, 10);
});

describe('composite direct order inventory', function (): void {
    it('deducts frozen components and never the parent, idempotently', function (): void {
        $order = createCompositeInventoryOrder('ORD-COMP-DED-' . uniqid());
        $order->items()->create([
            'name' => 'Order Bundle',
            'sku' => 'BUNDLE-1',
            'quantity' => 2,
            'unit_price' => 5000,
            'currency' => 'MYR',
            'purchasable_type' => $this->parent->getMorphClass(),
            'purchasable_id' => $this->parent->getKey(),
            'metadata' => [
                'components' => frozenOrderComponentSnapshot([
                    ['product' => $this->mouse, 'quantity' => 2],
                    ['product' => $this->cable, 'quantity' => 1],
                ]),
            ],
        ]);

        $listener = app(DeductInventoryFromOrder::class);
        $event = new InventoryDeductionRequired($order);
        $listener->handle($event);

        expect($this->inventoryService->getLevel($this->parent, $this->location->id)?->fresh()->quantity_on_hand)->toBe(10)
            ->and($this->inventoryService->getLevel($this->mouse, $this->location->id)?->fresh()->quantity_on_hand)->toBe(6)
            ->and($this->inventoryService->getLevel($this->cable, $this->location->id)?->fresh()->quantity_on_hand)->toBe(8)
            ->and(InventoryMovement::query()->where('type', MovementType::Shipment->value)->count())->toBe(2);

        $listener->handle($event);

        expect($this->inventoryService->getLevel($this->parent, $this->location->id)?->fresh()->quantity_on_hand)->toBe(10)
            ->and($this->inventoryService->getLevel($this->mouse, $this->location->id)?->fresh()->quantity_on_hand)->toBe(6)
            ->and($this->inventoryService->getLevel($this->cable, $this->location->id)?->fresh()->quantity_on_hand)->toBe(8)
            ->and(InventoryMovement::query()->where('type', MovementType::Shipment->value)->count())->toBe(2)
            ->and(InventoryOperation::query()
                ->where('order_id', $order->getKey())
                ->where('kind', InventoryOperation::KIND_DEDUCTION)
                ->count())->toBe(1);
    });

    it('restores component stock once when a deducted order is cancelled', function (): void {
        $order = createCompositeInventoryOrder('ORD-COMP-REL-' . uniqid());
        $order->items()->create([
            'name' => 'Order Bundle',
            'sku' => 'BUNDLE-2',
            'quantity' => 1,
            'unit_price' => 5000,
            'currency' => 'MYR',
            'purchasable_type' => $this->parent->getMorphClass(),
            'purchasable_id' => $this->parent->getKey(),
            'metadata' => [
                'components' => frozenOrderComponentSnapshot([
                    ['product' => $this->mouse, 'quantity' => 3],
                    ['product' => $this->cable, 'quantity' => 2],
                ]),
            ],
        ]);

        app(DeductInventoryFromOrder::class)->handle(new InventoryDeductionRequired($order));

        expect($this->inventoryService->getLevel($this->mouse, $this->location->id)?->fresh()->quantity_on_hand)->toBe(7)
            ->and($this->inventoryService->getLevel($this->cable, $this->location->id)?->fresh()->quantity_on_hand)->toBe(8);

        $release = app(ReleaseInventoryFromOrder::class);
        $event = new InventoryReleaseRequired($order);
        $release->handle($event);
        $release->handle($event);

        expect($this->inventoryService->getLevel($this->mouse, $this->location->id)?->fresh()->quantity_on_hand)->toBe(10)
            ->and($this->inventoryService->getLevel($this->cable, $this->location->id)?->fresh()->quantity_on_hand)->toBe(10)
            ->and($this->inventoryService->getLevel($this->parent, $this->location->id)?->fresh()->quantity_on_hand)->toBe(10)
            ->and(InventoryOperation::query()
                ->where('order_id', $order->getKey())
                ->where('kind', InventoryOperation::KIND_RELEASE)
                ->count())->toBe(1);
    });

    it('reads components only from order items', function (): void {
        $order = createCompositeInventoryOrder('ORD-COMP-META-' . uniqid(), [
            'components' => frozenOrderComponentSnapshot([
                ['product' => $this->mouse, 'quantity' => 4],
            ]),
        ]);

        $order->items()->create([
            'name' => 'Order Bundle',
            'sku' => 'BUNDLE-3',
            'quantity' => 2,
            'unit_price' => 5000,
            'currency' => 'MYR',
            'purchasable_type' => $this->parent->getMorphClass(),
            'purchasable_id' => $this->parent->getKey(),
        ]);

        app(DeductInventoryFromOrder::class)->handle(new InventoryDeductionRequired($order));

        expect($this->inventoryService->getLevel($this->parent, $this->location->id)?->fresh()->quantity_on_hand)->toBe(8)
            ->and($this->inventoryService->getLevel($this->mouse, $this->location->id)?->fresh()->quantity_on_hand)->toBe(10);
    });

    it('does not deduct a composite parent without a component snapshot', function (): void {
        $order = createCompositeInventoryOrder('ORD-COMP-EMPTY-' . uniqid());
        $order->items()->create([
            'name' => 'Order Bundle',
            'sku' => 'BUNDLE-4',
            'quantity' => 2,
            'unit_price' => 5000,
            'currency' => 'MYR',
            'purchasable_type' => $this->parent->getMorphClass(),
            'purchasable_id' => $this->parent->getKey(),
            'metadata' => ['components' => []],
        ]);

        expect(fn () => app(DeductInventoryFromOrder::class)->handle(new InventoryDeductionRequired($order)))
            ->toThrow(InvalidArgumentException::class, 'requires a non-empty list')
            ->and($this->inventoryService->getLevel($this->parent, $this->location->id)?->fresh()->quantity_on_hand)->toBe(10)
            ->and(InventoryMovement::query()->where('type', MovementType::Shipment->value)->count())->toBe(0);
    });
});

it('keeps an ordinary order pending when any tracked item is unavailable and retries atomically', function (): void {
    $order = createCompositeInventoryOrder('ORD-RETRY-' . uniqid());

    foreach ([[$this->mouse, 2], [$this->cable, 11]] as [$item, $quantity]) {
        $order->items()->create([
            'name' => $item->name,
            'quantity' => $quantity,
            'unit_price' => 100,
            'currency' => 'MYR',
            'purchasable_type' => $item->getMorphClass(),
            'purchasable_id' => $item->getKey(),
        ]);
    }

    $listener = app(DeductInventoryFromOrder::class);
    $event = new InventoryDeductionRequired($order);

    expect(fn () => $listener->handle($event))->toThrow(RuntimeException::class, 'Insufficient inventory')
        ->and($this->inventoryService->getLevel($this->mouse, $this->location->id)?->fresh()->quantity_on_hand)->toBe(10)
        ->and($this->inventoryService->getLevel($this->cable, $this->location->id)?->fresh()->quantity_on_hand)->toBe(10)
        ->and(InventoryOperation::query()->where('order_id', $order->id)->firstOrFail()->status)->toBe(InventoryOperation::STATUS_PENDING)
        ->and(InventoryMovement::query()->where('type', MovementType::Shipment->value)->count())->toBe(0);

    $this->inventoryService->receive($this->cable, $this->location->id, 1);
    $listener->handle($event);
    $listener->handle($event);

    expect($this->inventoryService->getLevel($this->mouse, $this->location->id)?->fresh()->quantity_on_hand)->toBe(8)
        ->and($this->inventoryService->getLevel($this->cable, $this->location->id)?->fresh()->quantity_on_hand)->toBe(0)
        ->and(InventoryOperation::query()->where('order_id', $order->id)->firstOrFail()->status)->toBe(InventoryOperation::STATUS_COMPLETED)
        ->and(InventoryMovement::query()->where('type', MovementType::Shipment->value)->count())->toBe(2);
});

it('does not deduct from an inactive preferred fulfillment location', function (): void {
    $inactive = InventoryLocation::factory()->create(['is_active' => false, 'priority' => 200]);
    $this->inventoryService->receive($this->mouse, $inactive->id, 50);

    $order = createCompositeInventoryOrder('ORD-INACTIVE-' . uniqid(), ['fulfillment_location_id' => $inactive->id]);
    $order->items()->create([
        'name' => 'Mouse',
        'quantity' => 2,
        'unit_price' => 100,
        'currency' => 'MYR',
        'purchasable_type' => $this->mouse->getMorphClass(),
        'purchasable_id' => $this->mouse->getKey(),
    ]);

    app(DeductInventoryFromOrder::class)->handle(new InventoryDeductionRequired($order));

    expect($this->inventoryService->getLevel($this->mouse, $inactive->id)?->fresh()->quantity_on_hand)->toBe(50)
        ->and($this->inventoryService->getLevel($this->mouse, $this->location->id)?->fresh()->quantity_on_hand)->toBe(8);
});

it('restores only order shipments with the immutable order identity', function (): void {
    $order = createCompositeInventoryOrder('ORD-IDENTITY-' . uniqid());
    $order->items()->create([
        'name' => 'Mouse',
        'quantity' => 2,
        'unit_price' => 100,
        'currency' => 'MYR',
        'purchasable_type' => $this->mouse->getMorphClass(),
        'purchasable_id' => $this->mouse->getKey(),
    ]);

    $this->inventoryService->ship($this->mouse, $this->location->id, 1, 'manual', $order->id);
    $this->inventoryService->ship($this->mouse, $this->location->id, 1, 'order', $order->order_number);

    app(DeductInventoryFromOrder::class)->handle(new InventoryDeductionRequired($order));

    expect(InventoryMovement::query()->where('reference', $order->id)->where('reason', 'order')->count())->toBe(1)
        ->and($this->inventoryService->getLevel($this->mouse, $this->location->id)?->fresh()->quantity_on_hand)->toBe(6);

    $listener = app(ReleaseInventoryFromOrder::class);
    $event = new InventoryReleaseRequired($order);
    $listener->handle($event);
    $listener->handle($event);

    expect($this->inventoryService->getLevel($this->mouse, $this->location->id)?->fresh()->quantity_on_hand)->toBe(8);
});

it('restores a committed batch to its original identity and reactivates depleted stock', function (): void {
    $order = createCompositeInventoryOrder('ORD-BATCH-' . uniqid());
    $batch = InventoryBatch::factory()->create([
        'inventoryable_type' => $this->mouse->getMorphClass(),
        'inventoryable_id' => $this->mouse->getKey(),
        'location_id' => $this->location->id,
        'quantity_received' => 2,
        'quantity_on_hand' => 2,
        'quantity_reserved' => 0,
        'status' => BatchStatus::Active->value,
        'expires_at' => null,
    ]);

    $allocations = app(InventoryAllocationService::class);
    $allocation = $allocations->allocate($this->mouse, 2, 'batch-cart')->firstOrFail();
    app(BatchAllocationService::class)->reserveBatches([['batch' => $batch, 'quantity' => 2]]);
    $allocation->forceFill(['batch_id' => $batch->id])->saveOrFail();
    $allocations->commit('batch-cart', $order->id);

    expect($batch->fresh()->quantity_on_hand)->toBe(0)
        ->and($batch->fresh()->status)->toBe(BatchStatus::Depleted->value)
        ->and($this->inventoryService->getTotalOnHand($this->mouse))->toBe(8);

    $listener = app(ReleaseInventoryFromOrder::class);
    $event = new InventoryReleaseRequired($order);
    $listener->handle($event);
    $listener->handle($event);

    expect($batch->fresh()->quantity_on_hand)->toBe(2)
        ->and($batch->fresh()->quantity_reserved)->toBe(0)
        ->and($batch->fresh()->status)->toBe(BatchStatus::Active->value)
        ->and($this->inventoryService->getTotalOnHand($this->mouse))->toBe(10)
        ->and(InventoryMovement::query()->where('type', MovementType::Receipt->value)->where('reference', $order->id)->where('batch_id', $batch->id)->count())->toBe(1);
});

it('does not return a recalled batch to sellable warehouse stock', function (): void {
    $order = createCompositeInventoryOrder('ORD-RECALLED-' . uniqid());
    $batch = InventoryBatch::factory()->create([
        'inventoryable_type' => $this->mouse->getMorphClass(),
        'inventoryable_id' => $this->mouse->getKey(),
        'location_id' => $this->location->id,
        'quantity_received' => 2,
        'quantity_on_hand' => 2,
        'quantity_reserved' => 0,
        'status' => BatchStatus::Active->value,
        'expires_at' => null,
    ]);

    $allocations = app(InventoryAllocationService::class);
    $allocation = $allocations->allocate($this->mouse, 2, 'recalled-batch-cart')->firstOrFail();
    app(BatchAllocationService::class)->reserveBatches([['batch' => $batch, 'quantity' => 2]]);
    $allocation->forceFill(['batch_id' => $batch->id])->saveOrFail();
    $allocations->commit('recalled-batch-cart', $order->id);
    $batch->fresh()->update(['status' => BatchStatus::Recalled->value]);

    expect(fn () => app(ReleaseInventoryFromOrder::class)->handle(new InventoryReleaseRequired($order)))
        ->toThrow(InvalidArgumentException::class, 'Cannot automatically restore non-sellable batch inventory');

    expect($batch->fresh()->quantity_on_hand)->toBe(0)
        ->and($batch->fresh()->status)->toBe(BatchStatus::Recalled->value)
        ->and($this->inventoryService->getTotalOnHand($this->mouse))->toBe(8)
        ->and(InventoryOperation::query()->where('order_id', $order->id)->where('kind', InventoryOperation::KIND_RELEASE)->firstOrFail()->status)->toBe(InventoryOperation::STATUS_PENDING);
});
