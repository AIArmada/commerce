<?php

declare(strict_types=1);

use AIArmada\Inventory\Models\InventoryOperation;
use Illuminate\Support\Facades\Schema;

it('includes the operations table in the configurable table map', function (): void {
    expect(config('inventory.database.tables.operations'))->toBe('inventory_operations')
        ->and((new InventoryOperation)->getTable())->toBe('inventory_operations');
});

it('carries the reservation group column in the allocations table shape', function (): void {
    $repoRoot = dirname(__DIR__, 4);
    $allocationTable = config('inventory.database.tables.allocations', 'inventory_allocations');
    $createMigrationPath = $repoRoot . '/packages/inventory/database/migrations/2000_09_01_000004_create_inventory_allocations_table.php';

    $reservationsMigration = file_get_contents($repoRoot . '/packages/inventory/database/migrations/2026_07_12_000002_create_inventory_reservations_table.php');
    expect($reservationsMigration)->toBeString()
        ->not->toContain('reservation_group_id');

    expect(Schema::hasColumn($allocationTable, 'reservation_group_id'))->toBeTrue()
        ->and(Schema::hasIndex($allocationTable, 'inv_allocations_reservation_group_idx'))->toBeTrue();

    $reservationGroupColumn = collect(Schema::getColumns($allocationTable))
        ->firstWhere('name', 'reservation_group_id');

    expect($reservationGroupColumn)->not->toBeNull()
        ->and($reservationGroupColumn['nullable'])->toBeTrue();

    expect(file_get_contents($createMigrationPath))->toContain('reservation_group_id')
        ->and(file_get_contents($createMigrationPath))->toContain('inv_allocations_reservation_group_idx');

    $columnsBeforeRerun = Schema::getColumnListing($allocationTable);
    $indexesBeforeRerun = Schema::getIndexes($allocationTable);
    $migration = require $createMigrationPath;

    $migration->up();
    $migration->up();

    expect(Schema::getColumnListing($allocationTable))->toEqual($columnsBeforeRerun)
        ->and(Schema::getIndexes($allocationTable))->toEqual($indexesBeforeRerun);
});

it('omits unused decimal quantity columns from the levels shape while keeping unit conversion', function (): void {
    $repoRoot = dirname(__DIR__, 4);
    $levelsTable = config('inventory.database.tables.levels', 'inventory_levels');
    $createMigrationPath = $repoRoot . '/packages/inventory/database/migrations/2000_09_01_000002_create_inventory_levels_table.php';

    expect(Schema::hasColumn($levelsTable, 'quantity_on_hand_decimal'))->toBeFalse()
        ->and(Schema::hasColumn($levelsTable, 'quantity_reserved_decimal'))->toBeFalse()
        ->and(Schema::hasColumn($levelsTable, 'unit_conversion_factor'))->toBeTrue();

    expect(file_get_contents($createMigrationPath))->not->toContain('quantity_on_hand_decimal')
        ->and(file_get_contents($createMigrationPath))->not->toContain('quantity_reserved_decimal');

    $columnsBeforeRerun = Schema::getColumnListing($levelsTable);
    $migration = require $createMigrationPath;

    $migration->up();
    $migration->up();

    expect(Schema::getColumnListing($levelsTable))->toEqual($columnsBeforeRerun);
});

it('carries the movement location history index in the movements shape idempotently', function (): void {
    $movementTable = config('inventory.database.tables.movements', 'inventory_movements');
    $indexName = 'inventory_movements_location_history_index';
    $columns = ['from_location_id', 'to_location_id', 'occurred_at'];

    expect(Schema::hasIndex($movementTable, $indexName))->toBeTrue()
        ->and(Schema::hasIndex($movementTable, $columns))->toBeTrue();

    $repoRoot = dirname(__DIR__, 4);
    $createMigrationPath = $repoRoot
        . '/packages/inventory/database/migrations/2000_09_01_000003_create_inventory_movements_table.php';

    expect(file_get_contents($createMigrationPath))->toContain($indexName);

    $migration = require $createMigrationPath;

    $migration->up();
    $migration->up();

    expect(Schema::hasIndex($movementTable, $indexName))->toBeTrue()
        ->and(Schema::hasIndex($movementTable, $columns))->toBeTrue();
});
