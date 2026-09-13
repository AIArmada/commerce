<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('creates owner columns and indexes on fresh installs', function (): void {
    $tables = [
        config('addressing.database.tables.addresses'),
        config('addressing.database.tables.addressables'),
        config('addressing.database.tables.snapshots'),
    ];

    foreach ($tables as $tableName) {
        expect(Schema::hasColumn($tableName, 'owner_type'))->toBeTrue()
            ->and(Schema::hasColumn($tableName, 'owner_id'))->toBeTrue()
            ->and(Schema::hasIndex($tableName, ['owner_type', 'owner_id']))->toBeTrue();
    }

    $creates = [
        '2001_01_01_000005_create_addresses_table.php' => "nullableUuidMorphs('owner')",
        '2001_01_01_000006_create_addressables_table.php' => "nullableUuidMorphs('owner')",
        '2001_01_01_000007_create_address_snapshots_table.php' => "nullableUuidMorphs('owner')",
    ];

    foreach ($creates as $file => $needle) {
        $create = (string) file_get_contents(dirname(__DIR__, 4) . '/packages/addressing/database/migrations/' . $file);

        expect($create)->toContain($needle)
            ->and($create)->toContain('Schema::create')
            ->and($create)->not->toContain('hasTable');
    }
});
