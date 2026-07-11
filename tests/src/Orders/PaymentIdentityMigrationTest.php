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
