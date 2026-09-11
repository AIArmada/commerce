<?php

declare(strict_types=1);

use AIArmada\Inventory\Models\InventoryOperation;
use Illuminate\Support\Facades\Schema;

it('includes the operations table in the configurable table map', function (): void {
    expect(config('inventory.database.tables.operations'))->toBe('inventory_operations')
        ->and((new InventoryOperation)->getTable())->toBe('inventory_operations');
});

it('keeps the reservation group schema change in its dedicated migration', function (): void {
    $repoRoot = dirname(__DIR__, 4);
    $allocationTable = config('inventory.database.tables.allocations', 'inventory_allocations');
    $reservationsMigrationPath = $repoRoot . '/packages/inventory/database/migrations/2026_07_12_000002_create_inventory_reservations_table.php';
    $dedicatedMigrationPath = $repoRoot . '/packages/inventory/database/migrations/2026_09_07_000001_add_reservation_group_id_to_inventory_allocations_table.php';

    $reservationsMigration = file_get_contents($reservationsMigrationPath);
    expect($reservationsMigration)->toBeString()
        ->not->toContain('reservation_group_id');

    expect(Schema::hasColumn($allocationTable, 'reservation_group_id'))->toBeTrue()
        ->and(Schema::hasIndex($allocationTable, 'inv_allocations_reservation_group_idx'))->toBeTrue();

    $reservationGroupColumn = collect(Schema::getColumns($allocationTable))
        ->firstWhere('name', 'reservation_group_id');

    expect($reservationGroupColumn)->not->toBeNull()
        ->and($reservationGroupColumn['nullable'])->toBeTrue();

    $columnsBeforeRerun = Schema::getColumnListing($allocationTable);
    $indexesBeforeRerun = Schema::getIndexes($allocationTable);
    $migration = require $dedicatedMigrationPath;

    $migration->up();
    $migration->up();

    expect(Schema::getColumnListing($allocationTable))->toEqual($columnsBeforeRerun)
        ->and(Schema::getIndexes($allocationTable))->toEqual($indexesBeforeRerun);
});

it('drops unused decimal quantity columns while keeping unit conversion', function (): void {
    $repoRoot = dirname(__DIR__, 4);
    $levelsTable = config('inventory.database.tables.levels', 'inventory_levels');
    $migrationPath = $repoRoot . '/packages/inventory/database/migrations/2026_09_07_000002_drop_decimal_quantities_from_inventory_levels_table.php';

    expect(Schema::hasColumn($levelsTable, 'quantity_on_hand_decimal'))->toBeFalse()
        ->and(Schema::hasColumn($levelsTable, 'quantity_reserved_decimal'))->toBeFalse()
        ->and(Schema::hasColumn($levelsTable, 'unit_conversion_factor'))->toBeTrue();

    $columnsBeforeRerun = Schema::getColumnListing($levelsTable);
    $migration = require $migrationPath;

    $migration->up();
    $migration->up();

    expect(Schema::getColumnListing($levelsTable))->toEqual($columnsBeforeRerun);
});

it('adds the movement location history index idempotently', function (): void {
    $movementTable = config('inventory.database.tables.movements', 'inventory_movements');
    $indexName = 'inventory_movements_location_history_index';
    $columns = ['from_location_id', 'to_location_id', 'occurred_at'];

    expect(Schema::hasIndex($movementTable, $indexName))->toBeTrue()
        ->and(Schema::hasIndex($movementTable, $columns))->toBeTrue();

    $repoRoot = dirname(__DIR__, 4);
    $migrationPath = $repoRoot
        . '/packages/inventory/database/migrations/2026_09_11_000004_add_movement_location_history_index.php';
    $migration = require $migrationPath;

    $migration->up();
    $migration->up();

    expect(Schema::hasIndex($movementTable, $indexName))->toBeTrue()
        ->and(Schema::hasIndex($movementTable, $columns))->toBeTrue();
});
