<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Guinea\GuineaAddressFormatter;
use AIArmada\Addressing\Geography\Guinea\GuineaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a two-level region and prefecture hierarchy', function (): void {
    $hierarchies = app(GuineaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(2)
        ->and($hierarchies[0]->levels[0]->key)->toBe('administrative_region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('prefecture')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('administrative_region');
});

it('imports the region and prefecture trees with state links', function (): void {
    $this->seedCountry('GN');

    $result = app(SeedCountryGeographiesAction::class)->execute('GN');
    $country = AddressCountry::query()->where('iso2', 'GN')->firstOrFail();

    expect($result['seeded'])->toContain('GN')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(41)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'administrative_region')->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'governorate')->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'prefecture')->count())->toBe(33)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('level', 2)->count())->toBe(33)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(8);

    $kankan = AddressArea::query()->where('source_id', 'gn:prefecture:kankan')->firstOrFail();
    $kankanRegion = AddressArea::query()->where('source_id', 'gn:administrative_region:kankan')->firstOrFail();
    $coyah = AddressArea::query()->where('source_id', 'gn:prefecture:coyah')->firstOrFail();
    $kindia = AddressArea::query()->where('source_id', 'gn:administrative_region:kindia')->firstOrFail();

    expect(AddressAreaRelationship::query()
        ->where('parent_address_area_id', $kankanRegion->getKey())
        ->where('child_address_area_id', $kankan->getKey())
        ->where('hierarchy_type', 'administrative')
        ->exists())->toBeTrue()
        ->and(AddressAreaRelationship::query()
            ->where('parent_address_area_id', $kindia->getKey())
            ->where('child_address_area_id', $coyah->getKey())
            ->where('hierarchy_type', 'administrative')
            ->exists())->toBeTrue();
});

it('formats Guinean addresses with the radical left of the locality', function (): void {
    $formatted = app(GuineaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 457',
        'city' => 'CONAKRY',
        'postcode' => '001',
        'country_code' => 'GN',
    ]));

    expect($formatted)->toBe("BP 457\n001 CONAKRY\nGuinea");
});
it('prints matching Guinean city and region once', function (): void {
    $formatted = app(GuineaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 12',
        'city' => 'Labé',
        'state' => 'Labé',
        'postcode' => '201',
        'country_code' => 'GN',
    ]));

    expect($formatted)->toBe("BP 12\n201 Labé\nGuinea");
});
