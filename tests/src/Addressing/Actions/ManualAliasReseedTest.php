<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedAddressCountriesAction;
use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Contracts\AddressAreaSource;
use AIArmada\Addressing\Contracts\CountryAddressAreaMetadataProvider;
use AIArmada\Addressing\Contracts\CountryGeographyProvider;
use AIArmada\Addressing\Contracts\CountryHierarchyProvider;
use AIArmada\Addressing\Data\AddressAreaData;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaName;
use AIArmada\Addressing\Models\AddressCountry;
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

    public function seed(AddressCountry $country): void {}

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

    public function areaRoles(AddressCountry $country): array
    {
        return [];
    }

    public function areaNames(AddressCountry $country): array
    {
        return [
            'kl' => [
                ['name' => 'KL', 'name_type' => 'abbreviation'],
            ],
        ];
    }

    public function areaRelationships(AddressCountry $country): array
    {
        return [];
    }
}

it('preserves manual gap-match aliases across geography reseeds', function (): void {
    app(SeedAddressCountriesAction::class)->execute();
    config()->set('addressing.geography.providers', [GapReseedFakeGeographyProvider::class]);

    app(SeedCountryGeographiesAction::class)->execute('MY');

    $area = AddressArea::query()->where('source_id', 'kl')->firstOrFail();

    AddressAreaName::query()->create([
        'address_area_id' => $area->getKey(),
        'name' => 'Kuala Lumpur',
        'source' => 'manual',
        'name_type' => 'common',
        'is_preferred' => false,
    ]);

    app(SeedCountryGeographiesAction::class)->execute('MY');

    expect(AddressAreaName::query()->where('address_area_id', $area->getKey())->where('source', 'manual')->count())->toBe(1)
        ->and(AddressAreaName::query()->where('address_area_id', $area->getKey())->where('source', 'test.fake')->count())->toBe(1)
        ->and(AddressAreaName::query()->where('address_area_id', $area->getKey())->where('name', 'Kuala Lumpur')->exists())->toBeTrue();
});
