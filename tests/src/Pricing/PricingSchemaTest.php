<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

function runCanonicalPricingMigration(string $file, string $tableKey, string $table): void
{
    config()->set("pricing.database.tables.{$tableKey}", $table);

    $migration = require __DIR__ . '/../../../packages/pricing/database/migrations/' . $file;
    $migration->up();
}

function canonicalPricingColumns(string $table): array
{
    return collect(Schema::getIndexes($table))
        ->flatMap(fn (array $index): array => $index['columns'] ?? [])
        ->all();
}

beforeEach(function (): void {
    // This file exercises the canonical migrations on scratch tables, so the
    // hand-built suite tables (which reuse explicit index names) go first.
    foreach (['price_tiers', 'prices', 'price_lists'] as $table) {
        Schema::dropIfExists($table);
    }
});

afterEach(function (): void {
    foreach (['scratch_price_lists', 'scratch_prices', 'scratch_price_tiers'] as $table) {
        Schema::dropIfExists($table);
    }
});

it('creates no database foreign keys in canonical pricing migrations', function (): void {
    runCanonicalPricingMigration('2000_12_01_000001_create_price_lists_table.php', 'price_lists', 'scratch_price_lists');
    runCanonicalPricingMigration('2000_12_01_000002_create_prices_table.php', 'prices', 'scratch_prices');
    runCanonicalPricingMigration('2000_12_01_000003_create_price_tiers_table.php', 'price_tiers', 'scratch_price_tiers');

    expect(Schema::getForeignKeys('scratch_price_lists'))->toBe([])
        ->and(Schema::getForeignKeys('scratch_prices'))->toBe([])
        ->and(Schema::getForeignKeys('scratch_price_tiers'))->toBe([]);
});

it('indexes lifecycle and currency lookup columns in canonical pricing migrations', function (): void {
    runCanonicalPricingMigration('2000_12_01_000001_create_price_lists_table.php', 'price_lists', 'scratch_price_lists');
    runCanonicalPricingMigration('2000_12_01_000002_create_prices_table.php', 'prices', 'scratch_prices');
    runCanonicalPricingMigration('2000_12_01_000003_create_price_tiers_table.php', 'price_tiers', 'scratch_price_tiers');

    expect(canonicalPricingColumns('scratch_prices'))->toContain('deactivated_at', 'currency')
        ->and(canonicalPricingColumns('scratch_price_lists'))->toContain('is_default', 'currency')
        ->and(canonicalPricingColumns('scratch_price_tiers'))->toContain('is_active', 'currency');
});
