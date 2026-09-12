<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('adds lower-name indexes to all geography lookup tables', function (): void {
    $indexes = [
        config('addressing.database.tables.countries') => 'countries_name_lower_index',
        config('addressing.database.tables.states') => 'states_name_lower_index',
        config('addressing.database.tables.cities') => 'cities_name_lower_index',
        config('addressing.database.tables.areas') => 'address_areas_name_lower_index',
        config('addressing.database.tables.area_names') => 'address_area_names_name_lower_index',
    ];

    $migration = require dirname(__DIR__, 4)
        . '/packages/addressing/database/migrations/2026_09_13_000001_add_lower_name_indexes_to_addressing_tables.php';

    $migration->up();
    $migration->up();

    foreach ($indexes as $tableName => $indexName) {
        expect(Schema::hasIndex($tableName, $indexName))->toBeTrue();
    }
});
