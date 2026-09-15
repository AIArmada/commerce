<?php

declare(strict_types=1);

use AIArmada\Addressing\Support\AddressingTableResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->addressesTable = AddressingTableResolver::resolve('addresses');
    $this->snapshotsTable = AddressingTableResolver::resolve('snapshots');
});

it('has geo and navigation columns on addresses and snapshots tables', function (): void {
    $columns = [
        'latitude',
        'longitude',
        'formatted_address',
        'provider',
        'provider_place_id',
        'google_maps_url',
        'waze_url',
        'navigation_links',
    ];

    foreach ([$this->addressesTable, $this->snapshotsTable] as $table) {
        foreach ($columns as $column) {
            expect(Schema::hasColumn($table, $column))->toBeTrue("{$table} should have {$column}");
        }
    }
});

it('uses configured json column type for navigation_links', function (): void {
    $driver = Schema::getConnection()->getDriverName();

    $column = Schema::getColumnType($this->addressesTable, 'navigation_links');
    $jsonColumnType = commerce_json_column_type('addressing', 'json');

    if ($driver === 'sqlite') {
        expect($column)->toBeIn(['text', $jsonColumnType]);
    } else {
        expect($column)->toBe($jsonColumnType);
    }
});

it('does not add database constraints or cascades', function (): void {
    $driver = Schema::getConnection()->getDriverName();

    if ($driver === 'sqlite') {
        $columns = Schema::getColumnListing($this->addressesTable);

        $createSql = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", [$this->addressesTable]);
        expect($createSql)->not->toBeNull();

        $sql = $createSql->sql;
        expect($sql)->not->toContain('REFERENCES');
    } else {
        $createSql = DB::selectOne("SHOW CREATE TABLE {$this->addressesTable}");
        $createKey = array_keys((array) $createSql)[1] ?? 'Create Table';
        $sql = is_object($createSql) ? $createSql->$createKey : '';

        if ($sql !== '') {
            expect($sql)->not->toContain('REFERENCES');
            expect($sql)->not->toContain('ON DELETE');
            expect($sql)->not->toContain('ON UPDATE');
            expect($sql)->not->toContain('CASCADE');
        }
    }
});
