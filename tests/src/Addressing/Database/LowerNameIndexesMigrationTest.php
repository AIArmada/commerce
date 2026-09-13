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

    foreach ($indexes as $tableName => $indexName) {
        expect(Schema::hasIndex($tableName, $indexName))->toBeTrue();
    }

    $creates = [
        '2001_01_01_000001_create_address_countries_table.php' => 'countries_name_lower_index',
        '2001_01_01_000002_create_address_states_table.php' => 'states_name_lower_index',
        '2001_01_01_000003_create_address_cities_table.php' => 'cities_name_lower_index',
        '2001_01_01_000004_create_address_areas_table.php' => 'address_areas_name_lower_index',
        '2001_01_01_000012_create_address_area_names_table.php' => 'address_area_names_name_lower_index',
    ];

    foreach ($creates as $file => $indexName) {
        $create = (string) file_get_contents(dirname(__DIR__, 4)
            . '/packages/addressing/database/migrations/' . $file);

        expect($create)->toContain($indexName)
            ->and($create)->toContain('Schema::create');
    }
});
