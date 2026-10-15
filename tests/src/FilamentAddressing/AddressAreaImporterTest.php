<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Commerce\Tests\Fixtures\Models\User;
use AIArmada\FilamentAddressing\Exports\AddressAreaExporter;
use AIArmada\FilamentAddressing\Imports\AddressAreaImporter;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Gate;

final class AddressAreaImporterPolicy
{
    public function update(User $user, AddressArea $area): bool
    {
        return $area->country_code === 'US';
    }
}

beforeEach(function (): void {
    app(SeedAddressCountriesAction::class)->execute();
    config()->set('filament-addressing.resources.areas.read_only', false);
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
        'hierarchy_type' => 'administrative',
        'relationship_type' => 'contains',
        'latitude' => '3.0738',
        'longitude' => '101.5183',
        'metadata' => '{"source":"import"}',
        'source_payload' => '{"dataset":"official"}',
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
        'hierarchy_type' => 'administrative',
        'relationship_type' => 'contains',
        'latitude' => '3.1073',
        'longitude' => '101.6067',
        'metadata' => '{"source":"import"}',
    ]);

    $child = AddressArea::where('source', 'app.malaysia')
        ->where('source_id', 'MY-10-PETALING')
        ->firstOrFail();
    $parentRelationship = $child->ancestors()->firstOrFail();

    expect($parentRelationship->pivot->relationship_type)->toBe('contains')
        ->and($parentRelationship->pivot->hierarchy_type)->toBe('administrative');

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

    expect($root->metadata)->toBe(['source' => 'import'])
        ->and($root->source_payload)->toBe(['dataset' => 'official']);
    expect($child->parent_id)->toBeNull();
    expect($child->metadata)->toBe(['source' => 'updated']);
});

it('blocks area imports when the resource is read only', function (): void {
    config()->set('filament-addressing.resources.areas.read_only', true);

    expect(fn () => makeAddressAreaImporter()([
        'country_code' => 'MY',
        'type' => 'state',
        'name' => 'Selangor',
        'source' => 'app.malaysia',
        'source_id' => 'MY-10',
    ]))->toThrow(RowImportFailedException::class, 'read only');
});

it('authorizes area updates against persisted identity before applying imported country data', function (): void {
    $this->actingAs(User::factory()->create());
    Gate::policy(AddressArea::class, AddressAreaImporterPolicy::class);
    $area = AddressArea::factory()->create([
        'country_code' => 'MY',
        'source' => 'app.malaysia',
        'source_id' => 'MY-10',
    ]);

    expect(fn () => makeAddressAreaImporter()([
        'country_code' => 'US',
        'type' => $area->type,
        'level' => (string) $area->level,
        'name' => $area->name,
        'source' => $area->source,
        'source_id' => $area->source_id,
    ]))->toThrow(RowImportFailedException::class, 'not authorized');

    expect($area->fresh()->country_code)->toBe('MY');
});

it('exports the typed parent relationship and area provenance columns', function (): void {
    $columns = array_map(
        static fn ($column): string => $column->getName(),
        AddressAreaExporter::getColumns(),
    );

    expect($columns)->toContain(
        'parent_source_id',
        'relationship_type',
        'hierarchy_type',
        'source',
        'source_id',
        'metadata',
        'source_payload',
    );
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
        'metadata' => '{"source":"import"}',
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
        'metadata' => '{"source":"import"}',
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
            'metadata' => '{"source":"import"}',
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
        'hierarchy_type' => 'hierarchy_type',
        'relationship_type' => 'relationship_type',
        'latitude' => 'latitude',
        'longitude' => 'longitude',
        'metadata' => 'metadata',
        'source_payload' => 'source_payload',
    ];
}
