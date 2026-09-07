<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('repairs partial owner columns and indexes idempotently', function (): void {
    $tables = [
        'addresses' => 'addressing_partial_owner_type',
        'addressables' => 'addressing_partial_owner_id',
        'snapshots' => 'addressing_partial_owner_columns',
    ];

    $originalTables = [
        'addressing.database.tables.addresses' => config('addressing.database.tables.addresses'),
        'addressing.database.tables.addressables' => config('addressing.database.tables.addressables'),
        'addressing.database.tables.snapshots' => config('addressing.database.tables.snapshots'),
    ];

    try {
        config([
            'addressing.database.tables.addresses' => $tables['addresses'],
            'addressing.database.tables.addressables' => $tables['addressables'],
            'addressing.database.tables.snapshots' => $tables['snapshots'],
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

it('blocks the cutover when legacy ownerless rows exist', function (): void {
    $tables = [
        'addresses' => 'addressing_legacy_addresses',
        'addressables' => 'addressing_legacy_addressables',
        'snapshots' => 'addressing_legacy_snapshots',
    ];

    $originalTables = [
        'addressing.database.tables.addresses' => config('addressing.database.tables.addresses'),
        'addressing.database.tables.addressables' => config('addressing.database.tables.addressables'),
        'addressing.database.tables.snapshots' => config('addressing.database.tables.snapshots'),
    ];

    try {
        config([
            'addressing.database.tables.addresses' => $tables['addresses'],
            'addressing.database.tables.addressables' => $tables['addressables'],
            'addressing.database.tables.snapshots' => $tables['snapshots'],
        ]);

        foreach ($tables as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('owner_type')->nullable();
                $table->uuid('owner_id')->nullable();
            });
        }

        DB::table($tables['addresses'])->insert([
            'id' => (string) Str::orderedUuid(),
        ]);

        DB::table($tables['addressables'])->insert([
            'id' => (string) Str::orderedUuid(),
            'owner_type' => 'test-owner',
        ]);

        DB::table($tables['snapshots'])->insert([
            'id' => (string) Str::orderedUuid(),
            'owner_id' => (string) Str::orderedUuid(),
        ]);

        $migration = require dirname(__DIR__, 4) . '/packages/addressing/database/migrations/2026_09_07_100000_reject_legacy_ownerless_addressing_rows.php';

        expect(fn () => $migration->up())
            ->toThrow(
                RuntimeException::class,
                '[addressing_legacy_addresses], [addressing_legacy_addressables], [addressing_legacy_snapshots]',
            )
            ->and(DB::table($tables['addresses'])->count())->toBe(1)
            ->and(DB::table($tables['addressables'])->count())->toBe(1)
            ->and(DB::table($tables['snapshots'])->count())->toBe(1);
    } finally {
        config($originalTables);

        foreach ($tables as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
});

it('allows fully-owned rows and remains safe to rerun', function (): void {
    $tables = [
        'addresses' => 'addressing_owned_addresses',
        'addressables' => 'addressing_owned_addressables',
        'snapshots' => 'addressing_owned_snapshots',
    ];

    $originalTables = [
        'addressing.database.tables.addresses' => config('addressing.database.tables.addresses'),
        'addressing.database.tables.addressables' => config('addressing.database.tables.addressables'),
        'addressing.database.tables.snapshots' => config('addressing.database.tables.snapshots'),
    ];

    try {
        config([
            'addressing.database.tables.addresses' => $tables['addresses'],
            'addressing.database.tables.addressables' => $tables['addressables'],
            'addressing.database.tables.snapshots' => $tables['snapshots'],
        ]);

        foreach ($tables as $tableName) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('owner_type')->nullable();
                $table->uuid('owner_id')->nullable();
            });

            DB::table($tableName)->insert([
                'id' => (string) Str::orderedUuid(),
                'owner_type' => 'test-owner',
                'owner_id' => (string) Str::orderedUuid(),
            ]);
        }

        $migration = require dirname(__DIR__, 4) . '/packages/addressing/database/migrations/2026_09_07_100000_reject_legacy_ownerless_addressing_rows.php';
        $migration->up();
        $migration->up();

        foreach ($tables as $tableName) {
            expect(DB::table($tableName)->count())->toBe(1);
        }
    } finally {
        config($originalTables);

        foreach ($tables as $tableName) {
            Schema::dropIfExists($tableName);
        }
    }
});
