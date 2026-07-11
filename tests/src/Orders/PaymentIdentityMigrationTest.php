<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('fails the migration when duplicate payment identities exist', function (): void {
    $table = 'order_payment_identity_upgrade_fixture_dup';
    config()->set('orders.database.tables.order_payments', $table);

    Schema::create($table, function (Blueprint $blueprint): void {
        $blueprint->uuid('id')->primary();
        $blueprint->uuid('order_id');
        $blueprint->string('gateway');
        $blueprint->string('transaction_id')->nullable();
        $blueprint->timestamp('created_at')->nullable();
    });

    DB::table($table)->insert([
        [
            'id' => '00000000-0000-0000-0000-000000000001',
            'order_id' => '00000000-0000-0000-0000-000000000010',
            'gateway' => 'stripe',
            'transaction_id' => 'txn-1',
            'created_at' => '2026-01-01 00:00:00',
        ],
        [
            'id' => '00000000-0000-0000-0000-000000000002',
            'order_id' => '00000000-0000-0000-0000-000000000010',
            'gateway' => 'stripe',
            'transaction_id' => 'txn-1',
            'created_at' => '2026-01-02 00:00:00',
        ],
    ]);

    $migration = require dirname(__DIR__, 3) . '/packages/orders/database/migrations/2026_07_11_000001_add_order_payment_identity_unique_index.php';

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'Duplicate order_payments rows');

    // Duplicate rows are preserved — migration does not delete financial records
    expect(DB::table($table)->count())->toBe(2);

    Schema::dropIfExists($table);
});

it('adds the unique index when no duplicates exist', function (): void {
    $table = 'order_payment_identity_upgrade_fixture_clean';
    config()->set('orders.database.tables.order_payments', $table);

    Schema::create($table, function (Blueprint $blueprint): void {
        $blueprint->uuid('id')->primary();
        $blueprint->uuid('order_id');
        $blueprint->string('gateway');
        $blueprint->string('transaction_id')->nullable();
        $blueprint->timestamp('created_at')->nullable();
    });

    DB::table($table)->insert([
        [
            'id' => '00000000-0000-0000-0000-000000000001',
            'order_id' => '00000000-0000-0000-0000-000000000010',
            'gateway' => 'stripe',
            'transaction_id' => 'txn-1',
            'created_at' => '2026-01-01 00:00:00',
        ],
        [
            'id' => '00000000-0000-0000-0000-000000000002',
            'order_id' => '00000000-0000-0000-0000-000000000010',
            'gateway' => 'cashier',
            'transaction_id' => 'txn-2',
            'created_at' => '2026-01-02 00:00:00',
        ],
    ]);

    $migration = require dirname(__DIR__, 3) . '/packages/orders/database/migrations/2026_07_11_000001_add_order_payment_identity_unique_index.php';
    $migration->up();

    expect(Schema::hasIndex($table, 'order_payments_order_gateway_transaction_unique'))->toBeTrue();

    Schema::dropIfExists($table);
});
