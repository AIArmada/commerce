<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('indexes price tier lookups by tierable and price list', function (): void {
    $migration = require dirname(__DIR__, 4) . '/packages/pricing/database/migrations/2026_09_07_073442_add_tierable_lookup_index_to_price_tiers_table.php';

    $migration->up();
    $migration->up();

    $index = collect(Schema::getIndexes('price_tiers'))
        ->firstWhere('name', 'price_tiers_tierable_lookup_idx');

    expect($index)->toBeArray()
        ->and($index['columns'] ?? null)
        ->toBe(['tierable_type', 'tierable_id', 'price_list_id']);
});
