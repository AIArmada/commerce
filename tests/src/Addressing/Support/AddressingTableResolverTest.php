<?php

declare(strict_types=1);

use AIArmada\Addressing\Models\Address;
use AIArmada\Addressing\Models\Addressable;
use AIArmada\Addressing\Support\AddressingTableResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('uses the canonical configured table names for runtime models and every addressing migration', function (): void {
    $tables = [
        'countries' => 'addressing_custom_countries',
        'areas' => 'addressing_custom_areas',
        'addresses' => 'addressing_custom_addresses',
        'addressables' => 'addressing_custom_addressables',
        'snapshots' => 'addressing_custom_snapshots',
        'states' => 'addressing_custom_states',
        'cities' => 'addressing_custom_cities',
        'country_currency_links' => 'addressing_custom_country_currency_links',
        'country_timezone_links' => 'addressing_custom_country_timezone_links',
        'area_state_links' => 'addressing_custom_area_state_links',
        'area_city_links' => 'addressing_custom_area_city_links',
        'area_names' => 'addressing_custom_area_names',
        'area_roles' => 'addressing_custom_area_roles',
        'area_relationships' => 'addressing_custom_area_relationships',
        'postal_codes' => 'addressing_custom_postal_codes',
        'area_postal_codes' => 'addressing_custom_area_postal_codes',
        'address_area_assignments' => 'addressing_custom_address_area_assignments',
    ];
    $originalTables = config('addressing.database.tables');
    $originalDefaultConnection = config('database.default');

    expect($originalTables)->toBeArray();

    try {
        config()->set('database.connections.addressing_resolver_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        config()->set('database.default', 'addressing_resolver_test');
        DB::purge('addressing_resolver_test');
        config()->set('addressing.database.tables', $tables);

        $repositoryRoot = dirname(__DIR__, 4);
        $migrationPaths = glob($repositoryRoot . '/packages/addressing/database/migrations/2001_*.php') ?: [];

        foreach ($migrationPaths as $migrationPath) {
            $migration = require $migrationPath;
            $migration->up();
        }

        foreach ($tables as $tableKey => $tableName) {
            expect(Schema::hasTable($tableName))->toBeTrue("Missing configured table [{$tableKey}] ({$tableName}).");
        }

        $ownerMigration = require $repositoryRoot . '/packages/addressing/database/migrations/2026_09_07_090000_add_owner_columns_to_addressing_tables.php';
        $cutoverMigration = require $repositoryRoot . '/packages/addressing/database/migrations/2026_09_07_100000_reject_legacy_ownerless_addressing_rows.php';

        $ownerMigration->up();
        $cutoverMigration->up();

        expect((new Address)->getTable())->toBe($tables['addresses'])
            ->and((new Addressable)->getTable())->toBe($tables['addressables']);

        foreach (['addresses', 'addressables', 'snapshots'] as $tableKey) {
            expect(Schema::hasColumn($tables[$tableKey], 'owner_type'))->toBeTrue()
                ->and(Schema::hasColumn($tables[$tableKey], 'owner_id'))->toBeTrue();
        }
    } finally {
        foreach (array_reverse($tables) as $tableName) {
            Schema::dropIfExists($tableName);
        }

        config()->set('addressing.database.tables', $originalTables);
        config()->set('database.default', $originalDefaultConnection);
        DB::purge('addressing_resolver_test');
        config()->set('database.connections.addressing_resolver_test', null);
    }
});

it('fails closed when a canonical table name is missing', function (): void {
    $originalTable = config('addressing.database.tables.addresses');

    try {
        config()->set('addressing.database.tables.addresses', null);

        expect(fn (): string => AddressingTableResolver::resolve('addresses'))
            ->toThrow(LogicException::class, 'addressing.database.tables.addresses');
    } finally {
        config()->set('addressing.database.tables.addresses', $originalTable);
    }
});

it('does not define a second addressing table configuration path', function (): void {
    $addressingConfig = config('addressing');

    expect($addressingConfig)->toBeArray()->not->toHaveKey('tables');
});
