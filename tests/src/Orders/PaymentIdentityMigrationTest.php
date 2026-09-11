<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('includes the unique index in the create migration', function (): void {
    $table = 'order_payments_unique_fixture';
    config()->set('orders.database.tables.order_payments', $table);

    $migration = require dirname(__DIR__, 3) . '/packages/orders/database/migrations/2000_11_01_000004_create_order_payments_table.php';
    $migration->up();

    expect(Schema::hasIndex($table, 'order_payments_order_gateway_transaction_unique'))->toBeTrue();

    Schema::dropIfExists($table);
});

it('replaces nullable payment identity uniqueness with a guarded partial index', function (): void {
    $table = 'order_payments_partial_fixture';
    config()->set('orders.database.tables.order_payments', $table);

    $createMigration = require dirname(__DIR__, 3) . '/packages/orders/database/migrations/2000_11_01_000004_create_order_payments_table.php';
    $createMigration->up();

    $hardenMigration = require dirname(__DIR__, 3) . '/packages/orders/database/migrations/2026_09_11_000002_harden_order_identity_indexes.php';
    $hardenMigration->up();
    $hardenMigration->up();

    expect(Schema::hasIndex($table, $table . '_order_gateway_transaction_non_null_unique'))->toBeTrue()
        ->and(Schema::hasIndex($table, 'order_payments_order_gateway_transaction_unique'))->toBeFalse();

    Schema::dropIfExists($table);
});
