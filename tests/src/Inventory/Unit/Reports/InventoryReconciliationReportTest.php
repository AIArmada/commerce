<?php

declare(strict_types=1);

use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\Commerce\Tests\Inventory\Fixtures\InventoryItem;
use AIArmada\CommerceSupport\Support\OwnerContext;
use AIArmada\Inventory\Features\Reporting\Queries\InventoryReconciliationReport;
use AIArmada\Inventory\Features\Reservations\Models\InventoryAllocation;
use AIArmada\Inventory\Features\Reservations\Models\InventoryOperation;
use AIArmada\Inventory\Features\Reservations\Models\InventoryReservation;
use AIArmada\Inventory\Features\Stock\Models\InventoryLevel;
use AIArmada\Inventory\Features\Stock\Models\InventoryLocation;
use AIArmada\Inventory\Services\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

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

it('reconciles completed operations without a query per operation', function (): void {
    config()->set('inventory.owner.enabled', false);

    $item = InventoryItem::create(['name' => 'Matched Movement Item']);
    $location = InventoryLocation::factory()->create();
    $inventory = app(InventoryService::class);
    $inventory->receive($item, $location->id, 10);

    $orderId = (string) str()->uuid();
    $inventory->ship($item, $location->id, 1, reason: 'order', reference: $orderId);

    InventoryOperation::create([
        'order_id' => $orderId,
        'kind' => InventoryOperation::KIND_DEDUCTION,
        'status' => InventoryOperation::STATUS_COMPLETED,
    ]);

    foreach (range(1, 20) as $index) {
        InventoryOperation::create([
            'order_id' => (string) str()->uuid(),
            'kind' => InventoryOperation::KIND_DEDUCTION,
            'status' => InventoryOperation::STATUS_COMPLETED,
        ]);
    }

    DB::enableQueryLog();
    DB::flushQueryLog();

    try {
        $summary = (new InventoryReconciliationReport)->getSummary();
        $queries = DB::getQueryLog();
    } finally {
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    expect($summary['operations_total'])->toBe(21)
        ->and($summary['completed_operations_without_movement'])->toBe(20)
        ->and(count($queries))->toBeLessThanOrEqual(6);
});

it('does not reconcile an owned operation against a visible global movement', function (): void {
    config()->set('inventory.owner.enabled', true);
    config()->set('inventory.owner.include_global', true);
    config()->set('inventory.owner.auto_assign_on_create', true);

    $owner = User::query()->create([
        'name' => 'Reconciliation Owner',
        'email' => 'reconciliation-owner@example.com',
        'password' => 'secret',
    ]);

    $orderId = (string) str()->uuid();

    OwnerContext::withOwner(null, static function () use ($orderId): void {
        $item = InventoryItem::create(['name' => 'Global Reconciliation Item']);
        $location = InventoryLocation::factory()->create();
        $inventory = app(InventoryService::class);
        $inventory->receive($item, $location->id, 10);
        $inventory->ship($item, $location->id, 1, reason: 'order', reference: $orderId);
    });

    OwnerContext::withOwner($owner, static function () use ($orderId): void {
        InventoryOperation::create([
            'order_id' => $orderId,
            'kind' => InventoryOperation::KIND_DEDUCTION,
            'status' => InventoryOperation::STATUS_COMPLETED,
        ]);

        $summary = (new InventoryReconciliationReport)->getSummary();

        expect($summary['operations_total'])->toBe(1)
            ->and($summary['completed_operations_without_movement'])->toBe(1);
    });
});
