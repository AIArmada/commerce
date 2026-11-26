<?php

declare(strict_types=1);

use AIArmada\Addressing\Contracts\AddressAreaSource;
use AIArmada\Addressing\Contracts\CountryAddressAreaMetadataProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryGeographySeeder;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Data\CountryStateSeedData;
use AIArmada\Addressing\Features\Geography\Models\AddressArea;
use AIArmada\Addressing\Features\Geography\Models\AddressAreaName;
use AIArmada\Addressing\Support\ArrayAddressAreaSource;

final class GapReseedFakeGeographyProvider implements CountryAddressAreaMetadataProvider, CountryGeographyProvider, CountryHierarchyProvider
{
    public function providerKey(): string
    {
        return 'test.fake';
    }

    public function countryCode(): string
    {
        return 'MY';
    }

    public function stateSeedData(): CountryStateSeedData
    {
        return new CountryStateSeedData([]);
    }

    public function addressHierarchies(): array
    {
        return [];
    }

    public function addressAreaSource(): AddressAreaSource
    {
        return new ArrayAddressAreaSource('fake', [
            new AddressAreaData(source: 'fake', sourceId: 'kl', countryCode: 'MY', type: 'locality', level: 2, name: 'Wilayah Persekutuan Kuala Lumpur'),
        ]);
    }

    public function stateAreaMappings(): array
    {
        return [];
    }

    public function areaRoles(): array
    {
        return [];
    }

    public function areaNames(): array
    {
        return [
            'kl' => [
                ['name' => 'KL', 'name_type' => 'abbreviation'],
            ],
        ];
    }

    public function areaRelationships(): array
    {
        return [];
    }
}

it('preserves manual gap-match aliases across geography reseeds', function (): void {
    $this->seedCountry('MY');
    config()->set('addressing.geography.providers', [GapReseedFakeGeographyProvider::class]);

    app(CountryGeographySeeder::class)->execute('MY');

    $area = AddressArea::query()->where('source_id', 'kl')->firstOrFail();

    AddressAreaName::query()->create([
        'address_area_id' => $area->getKey(),
        'name' => 'Kuala Lumpur',
        'source' => 'manual',
        'name_type' => 'common',
        'is_preferred' => false,
    ]);

    app(CountryGeographySeeder::class)->execute('MY');

    expect(AddressAreaName::query()->where('address_area_id', $area->getKey())->where('source', 'manual')->count())->toBe(1)
        ->and(AddressAreaName::query()->where('address_area_id', $area->getKey())->where('source', 'test.fake')->count())->toBe(1)
        ->and(AddressAreaName::query()->where('address_area_id', $area->getKey())->where('name', 'Kuala Lumpur')->exists())->toBeTrue();
});
