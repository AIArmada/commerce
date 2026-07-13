<?php

declare(strict_types=1);

use AIArmada\Inventory\Listeners\DeductInventoryFromOrder;
use AIArmada\Inventory\Listeners\ReleaseInventoryFromOrder;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryMovement;
use AIArmada\Inventory\Models\InventoryOperation;
use AIArmada\Orders\Events\InventoryDeductionRequired;
use AIArmada\Orders\Events\InventoryReleaseRequired;
use AIArmada\Orders\Models\Order;
use AIArmada\Orders\States\PendingPayment;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

describe('InventoryOperation model', function (): void {
    it('creates an operation with unique order_id + kind', function (): void {
        $orderId = (string) str()->uuid();

        $op1 = InventoryOperation::create([
            'order_id' => $orderId,
            'kind' => InventoryOperation::KIND_DEDUCTION,
            'status' => InventoryOperation::STATUS_PENDING,
        ]);

        expect($op1)->toBeInstanceOf(InventoryOperation::class);
        expect($op1->status)->toBe(InventoryOperation::STATUS_PENDING);
        expect($op1->completed_at)->toBeNull();

        $this->expectException(\Illuminate\Database\QueryException::class);

        InventoryOperation::create([
            'order_id' => $orderId,
            'kind' => InventoryOperation::KIND_DEDUCTION,
            'status' => InventoryOperation::STATUS_PENDING,
        ]);
    });

    it('distinguishes deduction and release as separate operations', function (): void {
        $orderId = (string) str()->uuid();

        InventoryOperation::create([
            'order_id' => $orderId,
            'kind' => InventoryOperation::KIND_DEDUCTION,
            'status' => InventoryOperation::STATUS_COMPLETED,
        ]);

        $release = InventoryOperation::create([
            'order_id' => $orderId,
            'kind' => InventoryOperation::KIND_RELEASE,
            'status' => InventoryOperation::STATUS_PENDING,
        ]);

        expect($release->kind)->toBe(InventoryOperation::KIND_RELEASE);
        expect(InventoryOperation::count())->toBe(2);
    });

    it('marks completion with timestamp', function (): void {
        $op = InventoryOperation::create([
            'order_id' => (string) str()->uuid(),
            'kind' => InventoryOperation::KIND_DEDUCTION,
            'status' => InventoryOperation::STATUS_PENDING,
        ]);

        $op->update([
            'status' => InventoryOperation::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $op->refresh();

        expect($op->status)->toBe(InventoryOperation::STATUS_COMPLETED);
        expect($op->completed_at)->not->toBeNull();
    });
});

describe('DeductInventoryFromOrder idempotency', function (): void {
    it('skips processing when operation is already completed', function (): void {
        $order = Order::create([
            'order_number' => 'ORD-IDM-DED-' . uniqid(),
            'status' => PendingPayment::class,
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
        ]);

        InventoryOperation::create([
            'order_id' => $order->id,
            'kind' => InventoryOperation::KIND_DEDUCTION,
            'status' => InventoryOperation::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $listener = app(DeductInventoryFromOrder::class);
        $event = new InventoryDeductionRequired($order);

        $listener->handle($event);

        expect(InventoryOperation::count())->toBe(1);
    });

    it('creates operation on first deduction and marks completed', function (): void {
        $order = Order::create([
            'order_number' => 'ORD-IDM-DED2-' . uniqid(),
            'status' => PendingPayment::class,
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
        ]);

        $listener = app(DeductInventoryFromOrder::class);
        $event = new InventoryDeductionRequired($order);

        $listener->handle($event);

        $operation = InventoryOperation::where('order_id', $order->id)
            ->where('kind', InventoryOperation::KIND_DEDUCTION)
            ->first();

        expect($operation)->not->toBeNull();
        expect($operation->status)->toBe(InventoryOperation::STATUS_COMPLETED);
        expect($operation->completed_at)->not->toBeNull();
    });

    it('does not create duplicate stock movements on replay', function (): void {
        Schema::dropIfExists('inventory_test_products');
        Schema::create('inventory_test_products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->timestamps();
        });

        $item = \AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem::create(['name' => 'Dedup SKU']);
        $location = InventoryLocation::factory()->create([
            'name' => 'WH1',
            'code' => 'WH1',
            'priority' => 100,
        ]);

        $inventoryService = app(\AIArmada\Inventory\Services\InventoryService::class);
        $inventoryService->receive($item, $location->id, 10);

        $order = Order::create([
            'order_number' => 'ORD-IDM-REPLAY-' . uniqid(),
            'status' => PendingPayment::class,
            'currency' => 'MYR',
            'subtotal' => 5000,
            'grand_total' => 5000,
        ]);

        $order->items()->create([
            'name' => 'Test Line',
            'quantity' => 3,
            'unit_price' => 5000,
            'currency' => 'MYR',
            'purchasable_type' => $item->getMorphClass(),
            'purchasable_id' => $item->getKey(),
        ]);

        $listener = app(DeductInventoryFromOrder::class);
        $event = new InventoryDeductionRequired($order);

        $listener->handle($event);

        $level = $inventoryService->getLevel($item, $location->id)?->fresh();
        $stockAfterFirst = $level?->quantity_on_hand;
        $movementsAfterFirst = InventoryMovement::count();

        $listener->handle($event);

        $level = $inventoryService->getLevel($item, $location->id)?->fresh();
        $stockAfterReplay = $level?->quantity_on_hand;

        expect($stockAfterReplay)->toBe($stockAfterFirst);
        expect(InventoryMovement::count())->toBe($movementsAfterFirst);
    });

    it('retries after a pending operation', function (): void {
        $order = Order::create([
            'order_number' => 'ORD-IDM-PEND-' . uniqid(),
            'status' => PendingPayment::class,
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
        ]);

        InventoryOperation::create([
            'order_id' => $order->id,
            'kind' => InventoryOperation::KIND_DEDUCTION,
            'status' => InventoryOperation::STATUS_PENDING,
        ]);

        $listener = app(DeductInventoryFromOrder::class);
        $event = new InventoryDeductionRequired($order);

        $listener->handle($event);

        $operation = InventoryOperation::where('order_id', $order->id)
            ->where('kind', InventoryOperation::KIND_DEDUCTION)
            ->first();

        expect($operation)->not->toBeNull();
        expect($operation->status)->toBe(InventoryOperation::STATUS_COMPLETED);
        expect($operation->completed_at)->not->toBeNull();
    });
});

describe('ReleaseInventoryFromOrder idempotency', function (): void {
    it('skips processing when operation is already completed', function (): void {
        $order = Order::create([
            'order_number' => 'ORD-IDM-REL-' . uniqid(),
            'status' => PendingPayment::class,
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
        ]);

        InventoryOperation::create([
            'order_id' => $order->id,
            'kind' => InventoryOperation::KIND_RELEASE,
            'status' => InventoryOperation::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $listener = app(ReleaseInventoryFromOrder::class);
        $event = new InventoryReleaseRequired($order);

        $listener->handle($event);

        expect(InventoryOperation::count())->toBe(1);
    });

    it('creates operation on first release and marks completed', function (): void {
        $order = Order::create([
            'order_number' => 'ORD-IDM-REL2-' . uniqid(),
            'status' => PendingPayment::class,
            'currency' => 'MYR',
            'subtotal' => 10000,
            'grand_total' => 10000,
        ]);

        $listener = app(ReleaseInventoryFromOrder::class);
        $event = new InventoryReleaseRequired($order);

        $listener->handle($event);

        $operation = InventoryOperation::where('order_id', $order->id)
            ->where('kind', InventoryOperation::KIND_RELEASE)
            ->first();

        expect($operation)->not->toBeNull();
        expect($operation->status)->toBe(InventoryOperation::STATUS_COMPLETED);
    });
});
