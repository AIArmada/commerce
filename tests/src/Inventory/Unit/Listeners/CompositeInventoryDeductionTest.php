<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Enums\MovementType;
use AIArmada\Inventory\Listeners\DeductInventoryFromOrder;
use AIArmada\Inventory\Listeners\ReleaseInventoryFromOrder;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Models\InventoryOperation;
use AIArmada\Inventory\Services\InventoryService;
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
        'product_id' => $component['product']->getKey(),
        'variant_id' => null,
        'inventoryable_type' => $component['product']->getMorphClass(),
        'inventoryable_id' => $component['product']->getKey(),
        'quantity' => $component['quantity'],
        'tracks_inventory' => true,
        'requires_shipping' => true,
        'name' => $component['product']->name,
        'sku' => 'SKU-' . $component['product']->getKey(),
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

    it('deducts an order-level frozen component snapshot without touching the parent line', function (): void {
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

        expect($this->inventoryService->getLevel($this->parent, $this->location->id)?->fresh()->quantity_on_hand)->toBe(10)
            ->and($this->inventoryService->getLevel($this->mouse, $this->location->id)?->fresh()->quantity_on_hand)->toBe(6)
            ->and(InventoryMovement::query()->where('type', MovementType::Shipment->value)->count())->toBe(1);
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

        app(DeductInventoryFromOrder::class)->handle(new InventoryDeductionRequired($order));

        expect($this->inventoryService->getLevel($this->parent, $this->location->id)?->fresh()->quantity_on_hand)->toBe(10)
            ->and(InventoryMovement::query()->where('type', MovementType::Shipment->value)->count())->toBe(0);
    });
});
