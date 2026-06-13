<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\FilamentAddressing\Imports\AddressAreaImporter;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;

beforeEach(function (): void {
    app(SeedAddressCountriesAction::class)->execute();
});

it('imports metadata and clears parent links through the core action', function (): void {
    $importer = makeAddressAreaImporter();

    $importer([
        'country_code' => 'MY',
        'type' => 'state',
        'level' => '1',
        'name' => 'Selangor',
        'native_name' => 'Selangor',
        'code' => '10',
        'parent_source_id' => null,
        'source' => 'app.malaysia',
        'source_id' => 'MY-10',
        'latitude' => '3.0738',
        'longitude' => '101.5183',
        'metadata' => '{"source":"legacy"}',
    ]);

    $importer([
        'country_code' => 'MY',
        'type' => 'district',
        'level' => '2',
        'name' => 'Petaling',
        'native_name' => 'Petaling',
        'code' => 'PETALING',
        'parent_source_id' => 'MY-10',
        'source' => 'app.malaysia',
        'source_id' => 'MY-10-PETALING',
        'latitude' => '3.1073',
        'longitude' => '101.6067',
        'metadata' => '{"source":"legacy"}',
    ]);

    $importer([
        'country_code' => 'MY',
        'type' => 'district',
        'level' => '2',
        'name' => 'Petaling',
        'native_name' => 'Petaling',
        'code' => 'PETALING',
        'parent_source_id' => '',
        'source' => 'app.malaysia',
        'source_id' => 'MY-10-PETALING',
        'latitude' => '3.1073',
        'longitude' => '101.6067',
        'metadata' => '{"source":"updated"}',
    ]);

    $root = AddressArea::where('source', 'app.malaysia')
        ->where('source_id', 'MY-10')
        ->firstOrFail();

    $child = AddressArea::where('source', 'app.malaysia')
        ->where('source_id', 'MY-10-PETALING')
        ->firstOrFail();

    expect($root->metadata)->toBe(['source' => 'legacy']);
    expect($child->parent_id)->toBeNull();
    expect($child->metadata)->toBe(['source' => 'updated']);
});

it('surfaces core import failures as row import errors', function (): void {
    $importer = makeAddressAreaImporter();

    $importer([
        'country_code' => 'MY',
        'type' => 'state',
        'level' => '1',
        'name' => 'Selangor',
        'native_name' => 'Selangor',
        'code' => '10',
        'parent_source_id' => null,
        'source' => 'app.malaysia',
        'source_id' => 'MY-10',
        'latitude' => '3.0738',
        'longitude' => '101.5183',
        'metadata' => '{"source":"legacy"}',
    ]);

    $importer([
        'country_code' => 'MY',
        'type' => 'district',
        'level' => '2',
        'name' => 'Petaling',
        'native_name' => 'Petaling',
        'code' => 'PETALING',
        'parent_source_id' => 'MY-10',
        'source' => 'app.malaysia',
        'source_id' => 'MY-10-PETALING',
        'latitude' => '3.1073',
        'longitude' => '101.6067',
        'metadata' => '{"source":"legacy"}',
    ]);

    expect(function () use ($importer): void {
        $importer([
            'country_code' => 'MY',
            'type' => 'state',
            'level' => '1',
            'name' => 'Selangor',
            'native_name' => 'Selangor',
            'code' => '10',
            'parent_source_id' => 'MY-10-PETALING',
            'source' => 'app.malaysia',
            'source_id' => 'MY-10',
            'latitude' => '3.0738',
            'longitude' => '101.5183',
            'metadata' => '{"source":"legacy"}',
        ]);
    })->toThrow(RowImportFailedException::class, 'hierarchy cycle');
});

function makeAddressAreaImporter(): AddressAreaImporter
{
    return new AddressAreaImporter(new Import, addressAreaImportColumnMap(), []);
}

/**
 * @return array<string, string>
 */
function addressAreaImportColumnMap(): array
{
    return [
        'country_code' => 'country_code',
        'type' => 'type',
        'level' => 'level',
        'name' => 'name',
        'native_name' => 'native_name',
        'code' => 'code',
        'parent_source_id' => 'parent_source_id',
        'source' => 'source',
        'source_id' => 'source_id',
        'latitude' => 'latitude',
        'longitude' => 'longitude',
        'metadata' => 'metadata',
    ];
}
