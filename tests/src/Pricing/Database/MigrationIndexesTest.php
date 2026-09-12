<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('indexes price tier lookups by tierable and price list', function (): void {
    $table = 'price_tiers_lookup_fixture';
    $originalTable = config('pricing.database.tables.price_tiers');

    config()->set('pricing.database.tables.price_tiers', $table);

    try {
        $migration = require dirname(__DIR__, 4) . '/packages/pricing/database/migrations/2000_12_01_000003_create_price_tiers_table.php';

        $migration->up();
        $migration->up();

        $index = collect(Schema::getIndexes($table))
            ->firstWhere('name', 'price_tiers_tierable_lookup_idx');

        expect($index)->toBeArray()
            ->and($index['columns'] ?? null)
            ->toBe(['tierable_type', 'tierable_id', 'price_list_id']);
    } finally {
        config()->set('pricing.database.tables.price_tiers', $originalTable);
        Schema::dropIfExists($table);
    }
});
