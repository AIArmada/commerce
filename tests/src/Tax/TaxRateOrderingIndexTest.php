<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

test('tax rate create migration ships its guarded composite index idempotently', function (): void {
    $tableName = 'tax_rate_ordering_index_fixture';
    $indexName = 'tax_rates_zone_class_active_compound_priority_index';
    $migration = require dirname(__DIR__, 3) . '/packages/tax/database/migrations/2001_03_01_000003_create_tax_rates_table.php';
    $originalTable = config('tax.database.tables.tax_rates');

    config(['tax.database.tables.tax_rates' => $tableName]);

    try {
        $migration->up();
        $migration->up();

        expect(Schema::hasIndex($tableName, $indexName))->toBeTrue();
    } finally {
        Schema::dropIfExists($tableName);
        config(['tax.database.tables.tax_rates' => $originalTable]);
    }
});
