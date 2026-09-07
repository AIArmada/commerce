<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it('repairs partial owner columns and indexes idempotently', function (): void {
    $tables = [
        'addresses' => 'addressing_partial_owner_type',
        'addressables' => 'addressing_partial_owner_id',
        'snapshots' => 'addressing_partial_owner_columns',
    ];

    $originalTables = [
        'addressing.tables.addresses' => config('addressing.tables.addresses'),
        'addressing.tables.addressables' => config('addressing.tables.addressables'),
        'addressing.tables.snapshots' => config('addressing.tables.snapshots'),
    ];

    try {
        config([
            'addressing.tables.addresses' => $tables['addresses'],
            'addressing.tables.addressables' => $tables['addressables'],
            'addressing.tables.snapshots' => $tables['snapshots'],
        ]);

        Schema::create($tables['addresses'], function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('owner_type')->nullable();
        });

        Schema::create($tables['addressables'], function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('owner_id')->nullable();
        });

        Schema::create($tables['snapshots'], function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('owner_type')->nullable();
            $table->uuid('owner_id')->nullable();
        });

        $migration = require dirname(__DIR__, 4) . '/packages/addressing/database/migrations/2026_09_07_090000_add_owner_columns_to_addressing_tables.php';
        $migration->up();
        $migration->up();

        foreach ($tables as $tableName) {
            expect(Schema::hasColumn($tableName, 'owner_type'))->toBeTrue()
                ->and(Schema::hasColumn($tableName, 'owner_id'))->toBeTrue()
                ->and(Schema::hasIndex($tableName, ['owner_type', 'owner_id']))->toBeTrue();
        }
    } finally {
        config($originalTables);

        foreach ($tables as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
});
