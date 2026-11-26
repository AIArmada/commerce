<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\CountryDatasetSeeder;
use AIArmada\Addressing\Features\Geography\Models\AddressArea;
use AIArmada\Addressing\Features\Geography\Models\AddressAreaRelationship;
use AIArmada\Addressing\Features\Geography\Models\AddressCountry;
use AIArmada\FilamentAddressing\Exports\AddressAreaExporter;
use AIArmada\FilamentAddressing\Exports\AddressCountryExporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

function eagerExportExporter(string $exporterClass): object
{
    $columns = $exporterClass::getColumns();
    $columnNames = array_map(static fn ($column): string => $column->getName(), $columns);

    return new $exporterClass(new Export, array_combine($columnNames, $columnNames), []);
}

it('eager loads ancestors once for area exports', function (): void {
    app(CountryDatasetSeeder::class)->execute();
    $country = AddressCountry::query()->where('iso2', 'MY')->firstOrFail();
    $parent = AddressArea::query()->create([
        'country_id' => $country->getKey(), 'country_code' => 'MY', 'type' => 'state', 'level' => 1,
        'name' => 'Selangor', 'slug' => 'selangor', 'source' => 'areas', 'source_id' => 'selangor', 'is_active' => true,
    ]);
    $child = AddressArea::query()->create([
        'country_id' => $country->getKey(), 'country_code' => 'MY', 'parent_id' => $parent->getKey(),
        'type' => 'district', 'level' => 2, 'name' => 'Petaling', 'slug' => 'petaling',
        'source' => 'areas', 'source_id' => 'petaling', 'is_active' => true,
    ]);
    AddressAreaRelationship::query()->create([
        'parent_address_area_id' => $parent->getKey(), 'child_address_area_id' => $child->getKey(),
        'relationship_type' => 'contains', 'hierarchy_type' => 'administrative', 'source' => 'areas',
    ]);

    $exporter = eagerExportExporter(AddressAreaExporter::class);
    $query = AddressArea::query();

    foreach ($exporter->getCachedColumns() as $column) {
        $column->applyEagerLoading($query);
    }

    expect(array_key_exists('ancestors', $query->getEagerLoads()))->toBeTrue();

    $records = $query->whereIn('id', [$parent->getKey(), $child->getKey()])->orderBy('level')->get();
    $columnNames = array_keys($exporter->getCachedColumns());
    $rows = [];
    $queries = [];

    DB::listen(static function (QueryExecuted $query) use (&$queries): void {
        $queries[] = mb_strtolower($query->sql);
    });

    foreach ($records as $record) {
        $rows[(string) $record->getKey()] = array_combine($columnNames, $exporter($record));
    }

    expect($records)->toHaveCount(2)
        ->and(collect($queries)->filter(static fn (string $sql): bool => str_contains($sql, 'area_relationships')))->toBeEmpty()
        ->and($rows[(string) $child->getKey()]['relationship_type'])->toBe('contains')
        ->and($rows[(string) $child->getKey()]['hierarchy_type'])->toBe('administrative');
});

it('eager loads currencies once for country exports', function (): void {
    app(CountryDatasetSeeder::class)->execute();

    $exporter = eagerExportExporter(AddressCountryExporter::class);
    $query = AddressCountry::query();

    foreach ($exporter->getCachedColumns() as $column) {
        $column->applyEagerLoading($query);
    }

    $countries = $query->get();
    $queries = [];

    DB::listen(static function (QueryExecuted $query) use (&$queries): void {
        $queries[] = mb_strtolower($query->sql);
    });

    foreach ($countries as $country) {
        $exporter($country);
    }

    expect($countries)->not->toBeEmpty()
        ->and(collect($queries)->filter(static fn (string $sql): bool => str_contains($sql, 'country_currency_links')))->toBeEmpty();
});
