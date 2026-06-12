<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    $this->addressesTable = config('addressing.tables.addresses', 'addresses');
    $this->snapshotsTable = config('addressing.tables.snapshots', 'address_snapshots');
});

it('has latitude on addresses table', function (): void {
    expect(Schema::hasColumn($this->addressesTable, 'latitude'))->toBeTrue();
});

it('has longitude on addresses table', function (): void {
    expect(Schema::hasColumn($this->addressesTable, 'longitude'))->toBeTrue();
});

it('has formatted_address on addresses table', function (): void {
    expect(Schema::hasColumn($this->addressesTable, 'formatted_address'))->toBeTrue();
});

it('has provider on addresses table', function (): void {
    expect(Schema::hasColumn($this->addressesTable, 'provider'))->toBeTrue();
});

it('has provider_place_id on addresses table', function (): void {
    expect(Schema::hasColumn($this->addressesTable, 'provider_place_id'))->toBeTrue();
});

it('has google_maps_url on addresses table', function (): void {
    expect(Schema::hasColumn($this->addressesTable, 'google_maps_url'))->toBeTrue();
});

it('has waze_url on addresses table', function (): void {
    expect(Schema::hasColumn($this->addressesTable, 'waze_url'))->toBeTrue();
});

it('has navigation_links on addresses table', function (): void {
    expect(Schema::hasColumn($this->addressesTable, 'navigation_links'))->toBeTrue();
});

it('has latitude on address_snapshots table', function (): void {
    expect(Schema::hasColumn($this->snapshotsTable, 'latitude'))->toBeTrue();
});

it('has longitude on address_snapshots table', function (): void {
    expect(Schema::hasColumn($this->snapshotsTable, 'longitude'))->toBeTrue();
});

it('has formatted_address on address_snapshots table', function (): void {
    expect(Schema::hasColumn($this->snapshotsTable, 'formatted_address'))->toBeTrue();
});

it('has provider on address_snapshots table', function (): void {
    expect(Schema::hasColumn($this->snapshotsTable, 'provider'))->toBeTrue();
});

it('has provider_place_id on address_snapshots table', function (): void {
    expect(Schema::hasColumn($this->snapshotsTable, 'provider_place_id'))->toBeTrue();
});

it('has google_maps_url on address_snapshots table', function (): void {
    expect(Schema::hasColumn($this->snapshotsTable, 'google_maps_url'))->toBeTrue();
});

it('has waze_url on address_snapshots table', function (): void {
    expect(Schema::hasColumn($this->snapshotsTable, 'waze_url'))->toBeTrue();
});

it('has navigation_links on address_snapshots table', function (): void {
    expect(Schema::hasColumn($this->snapshotsTable, 'navigation_links'))->toBeTrue();
});

it('uses configured json column type for navigation_links', function (): void {
    $driver = Schema::getConnection()->getDriverName();

    $column = Schema::getColumnType($this->addressesTable, 'navigation_links');
    $jsonColumnType = config('addressing.database.json_column_type', 'json');

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
