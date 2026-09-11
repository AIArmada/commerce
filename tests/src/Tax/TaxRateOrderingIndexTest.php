<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

test('tax rate ordering migration adds its guarded composite index idempotently', function (): void {
    $tableName = 'tax_rate_ordering_index_fixture';
    $indexName = 'tax_rates_zone_class_active_compound_priority_index';
    $migration = require dirname(__DIR__, 3) . '/packages/tax/database/migrations/2026_09_12_000001_add_tax_rate_ordering_index.php';
    $originalTable = config('tax.database.tables.tax_rates');

    config(['tax.database.tables.tax_rates' => $tableName]);

    Schema::create($tableName, function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->uuid('zone_id');
        $table->string('tax_class');
        $table->boolean('is_active');
        $table->boolean('is_compound');
        $table->integer('priority');
    });

    try {
        $migration->up();
        $migration->up();

        expect(Schema::hasIndex($tableName, $indexName))->toBeTrue();

        $migration->down();

        expect(Schema::hasIndex($tableName, $indexName))->toBeFalse();
    } finally {
        Schema::dropIfExists($tableName);
        config(['tax.database.tables.tax_rates' => $originalTable]);
    }
});
