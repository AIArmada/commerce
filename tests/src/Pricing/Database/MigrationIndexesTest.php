<?php

declare(strict_types=1);

it('indexes price tier lookups by tierable and price list', function (): void {
    $migrationPath = dirname(__DIR__, 4) . '/packages/pricing/database/migrations/2026_09_07_073442_add_tierable_lookup_index_to_price_tiers_table.php';
    $migration = file_get_contents($migrationPath);

    expect($migrationPath)->toBeFile();
    expect($migration)
        ->toBeString()
        ->toContain("\$table->index(['tierable_type', 'tierable_id', 'price_list_id'], \$indexName);");
});
