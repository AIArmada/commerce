<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Actions\SeedPostalCodesAction;
use AIArmada\Addressing\Contracts\AddressAreaSource;
use AIArmada\Addressing\Contracts\CountryAddressAreaMetadataProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Geography\SaintBarthelemy\SaintBarthelemyGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Models\AddressAreaPostalCode;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaRole;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\City;
use AIArmada\Addressing\Models\PostalCode;
use AIArmada\Addressing\Models\State;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

final class ReseedingContractGeographyProvider implements CountryAddressAreaMetadataProvider, CountryGeographyProvider, CountryHierarchyProvider
{
    public static string $areaName = 'Version A';

    public static int $areaCount = 1;

    public function providerKey(): string
    {
        return 'test.reseeding-contract';
    }

    public function countryCode(): string
    {
        return 'BL';
    }

    public function seed(AddressCountry $country): void {}

    public function addressHierarchies(): array
    {
        return [];
    }

    public function addressAreaSource(): AddressAreaSource
    {
        $areas = [];

        for ($index = 0; $index < self::$areaCount; $index++) {
            $sourceId = 'area-' . $index;

            $areas[] = new AddressAreaData(
                source: 'test_reseeding_contract',
                sourceId: $sourceId,
                countryCode: 'BL',
                type: 'locality',
                level: $index === 0 ? 1 : 2,
                name: $index === 0 ? self::$areaName : 'Area ' . $index,
                parentSourceId: $index === 0 ? null : 'area-0',
            );
        }

        return new ArrayAddressAreaSource('test_reseeding_contract', $areas);
    }

    public function stateAreaMappings(): array
    {
        return [];
    }

    public function areaRoles(AddressCountry $country): array
    {
        return [
            'area-0' => [['role' => 'locality', 'country_code' => 'BL', 'is_primary' => true]],
        ];
    }

    public function areaNames(AddressCountry $country): array
    {
        return [
            'area-0' => [['name' => 'Source alias', 'name_type' => 'common', 'is_preferred' => false]],
        ];
    }

    public function areaRelationships(AddressCountry $country): array
    {
        return self::$areaCount < 2 ? [] : [
            'area-1' => [[
                'parent_source_id' => 'area-0',
                'relationship_type' => 'contains',
                'hierarchy_type' => 'administrative',
            ]],
        ];
    }
}

beforeEach(function (): void {
    config(['addressing.geography.providers' => [SaintBarthelemyGeographyProvider::class]]);
});

it('keeps real Saint Barthélemy geography and postcode rows unchanged on a no-op reseed', function (): void {
    $country = $this->seedCountry('BL');

    app(SeedCountryGeographiesAction::class)->execute('BL');
    app(SeedPostalCodesAction::class)->execute('BL');

    $area = AddressArea::query()->where('country_id', $country->getKey())->firstOrFail();
    $postalCode = PostalCode::query()->where('country_code', 'BL')->firstOrFail();

    $manualName = AddressAreaName::query()->create([
        'address_area_id' => $area->getKey(),
        'name' => 'Local review alias',
        'source' => 'manual',
        'name_type' => 'common',
        'is_preferred' => false,
    ]);
    $manualLink = AddressAreaPostalCode::query()->create([
        'address_area_id' => $area->getKey(),
        'postal_code_id' => $postalCode->getKey(),
        'source' => 'manual',
        'source_id' => 'local-review',
        'relationship_type' => 'manual-review',
        'is_primary' => false,
    ]);

    $tableModels = [
        $country,
        $area,
        $postalCode,
        new AddressAreaName,
        new AddressAreaPostalCode,
        new State,
        new City,
        new AddressAreaRole,
        new AddressAreaRelationship,
        new AddressAreaStateLink,
    ];
    $snapshot = static function () use ($tableModels): array {
        $rows = [];

        foreach ($tableModels as $model) {
            $table = $model->getTable();
            $rows[$table] = DB::table($table)->orderBy('id')->get()->map(static fn (object $row): array => (array) $row)->all();
        }

        return $rows;
    };
    $before = $snapshot();
    $writeQueries = 0;
    $writeSql = [];
    $selectQueries = 0;
    $captureQueries = false;

    DB::listen(function (QueryExecuted $query) use (&$captureQueries, &$writeQueries, &$writeSql, &$selectQueries): void {
        if (! $captureQueries) {
            return;
        }

        $sql = mb_strtolower(mb_ltrim($query->sql));

        if (str_starts_with($sql, 'select')) {
            $selectQueries++;
        }

        if (preg_match('/^(insert|update|delete|replace)\b/', $sql) === 1) {
            $writeQueries++;
            $writeSql[] = $query->sql;
        }
    });

    $this->travel(1)->minutes();
    $captureQueries = true;
    $geographyResult = app(SeedCountryGeographiesAction::class)->execute('BL');
    $postalCodeResult = app(SeedPostalCodesAction::class)->execute('BL');
    $captureQueries = false;

    expect($snapshot())->toBe($before)
        ->and($writeQueries)->toBe(0, json_encode($writeSql, JSON_THROW_ON_ERROR))
        ->and($selectQueries)->toBeGreaterThan(0)
        ->and($geographyResult['areas']['BL'])->toBe(['created' => 0, 'updated' => 0, 'skipped' => 1])
        ->and($postalCodeResult['codes']['BL'])->toBe(['created' => 0, 'updated' => 0, 'skipped' => 1, 'links' => 0])
        ->and(AddressAreaName::query()->whereKey($manualName->getKey())->value('name'))->toBe('Local review alias')
        ->and(AddressAreaPostalCode::query()->whereKey($manualLink->getKey())->value('source'))->toBe('manual');
});

it('updates source changes by stable identity, overwriting local edits', function (): void {
    $this->seedCountry('BL');
    config(['addressing.geography.providers' => [ReseedingContractGeographyProvider::class]]);
    ReseedingContractGeographyProvider::$areaCount = 2;
    ReseedingContractGeographyProvider::$areaName = 'Version A';

    $seeder = app(SeedCountryGeographiesAction::class);
    $seeder->execute('BL');

    $area = AddressArea::query()
        ->where('source', 'test_reseeding_contract')
        ->where('source_id', 'area-0')
        ->firstOrFail();
    $stableId = $area->getKey();
    $oldTimestamp = $area->updated_at?->toISOString();

    $this->travel(1)->minutes();
    ReseedingContractGeographyProvider::$areaName = 'Version B';
    $seeder->execute('BL');

    $area->refresh();

    expect($area->getKey())->toBe($stableId)
        ->and($area->name)->toBe('Version B')
        ->and($area->updated_at?->toISOString())->not->toBe($oldTimestamp);

    $area->update(['name' => 'Local correction']);

    $this->travel(1)->minutes();
    ReseedingContractGeographyProvider::$areaName = 'Version C';
    $seeder->execute('BL');

    expect(AddressArea::query()->findOrFail($stableId)->name)->toBe('Version C');
});

it('keeps geography select counts bounded as a provider grows from 25 to 100 areas', function (): void {
    $this->seedCountry('BL');
    config(['addressing.geography.providers' => [ReseedingContractGeographyProvider::class]]);

    $countSelects = static function (int $areaCount): int {
        ReseedingContractGeographyProvider::$areaCount = $areaCount;
        $selects = 0;
        $captureQueries = true;

        DB::listen(function (QueryExecuted $query) use (&$captureQueries, &$selects): void {
            if ($captureQueries && str_starts_with(mb_strtolower(mb_ltrim($query->sql)), 'select')) {
                $selects++;
            }
        });

        app(SeedCountryGeographiesAction::class)->execute('BL');
        $captureQueries = false;

        return $selects;
    };

    $smallDatasetSelects = $countSelects(25);
    $largeDatasetSelects = $countSelects(100);
    $largeAreaCount = AddressArea::query()->where('source', 'test_reseeding_contract')->count();

    expect($largeAreaCount)->toBe(100)
        ->and($largeDatasetSelects)->toBeLessThanOrEqual($smallDatasetSelects + 3);
});
