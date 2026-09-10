<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\Inventory\Models\InventoryAllocation;
use AIArmada\Inventory\Models\InventoryLevel;
use AIArmada\Inventory\Models\InventoryLocation;
use AIArmada\Inventory\Models\InventoryOperation;
use AIArmada\Inventory\Models\InventoryReservation;
use AIArmada\Inventory\Reports\InventoryReconciliationReport;
use Carbon\CarbonImmutable;

it('reports operation and reservation bookkeeping drift', function (): void {
    $item = InventoryItem::create(['name' => 'Reconciliation Item']);
    $location = InventoryLocation::factory()->create();
    $level = InventoryLevel::factory()->create([
        'inventoryable_type' => $item->getMorphClass(),
        'inventoryable_id' => $item->getKey(),
        'location_id' => $location->id,
    ]);

    InventoryOperation::create([
        'order_id' => (string) str()->uuid(),
        'kind' => InventoryOperation::KIND_DEDUCTION,
        'status' => InventoryOperation::STATUS_COMPLETED,
    ]);
    InventoryOperation::create([
        'order_id' => (string) str()->uuid(),
        'kind' => InventoryOperation::KIND_RELEASE,
        'status' => InventoryOperation::STATUS_PENDING,
    ]);
    InventoryOperation::create([
        'order_id' => (string) str()->uuid(),
        'kind' => InventoryOperation::KIND_RELEASE,
        'status' => InventoryOperation::STATUS_FAILED,
    ]);

    InventoryReservation::create([
        'reference' => 'expired-reservation',
        'status' => InventoryReservation::STATE_RESERVED,
        'line_snapshot' => [],
        'ttl_seconds' => 60,
        'expires_at' => CarbonImmutable::now()->subMinute(),
    ]);

    $terminalReservation = InventoryReservation::create([
        'reference' => 'released-reservation',
        'status' => InventoryReservation::STATE_RELEASED,
        'line_snapshot' => [],
        'ttl_seconds' => 60,
        'expires_at' => CarbonImmutable::now()->subMinutes(2),
    ]);

    InventoryAllocation::factory()
        ->forInventoryable($item->getMorphClass(), $item->getKey())
        ->forLocation($location)
        ->forLevel($level)
        ->create([
            'reservation_group_id' => $terminalReservation->id,
        ]);

    $summary = (new InventoryReconciliationReport)->getSummary();

    expect($summary)
        ->toMatchArray([
            'operations_total' => 3,
            'operations_pending' => 1,
            'operations_failed' => 1,
            'completed_operations_without_movement' => 1,
            'reservations_total' => 2,
            'reservations_expired_pending_cleanup' => 1,
            'reservations_without_allocations' => 1,
            'terminal_reservations_with_allocations' => 1,
        ]);
});
