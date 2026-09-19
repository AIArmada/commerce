<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Ireland\IrelandAddressFormatter;
use AIArmada\Addressing\Geography\Ireland\IrelandGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a two-level province and county hierarchy', function (): void {
    $hierarchies = app(IrelandGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(2)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('county')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('province');
});

it('imports the province and county trees with state links', function (): void {
    $this->seedCountry('IE');

    $result = app(SeedCountryGeographiesAction::class)->execute('IE');
    $country = AddressCountry::query()->where('iso2', 'IE')->firstOrFail();

    expect($result['seeded'])->toContain('IE')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(30)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(4)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'county')->count())->toBe(26)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('level', 2)->count())->toBe(26)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(4);

    $dublin = AddressArea::query()->where('source_id', 'ie:county:dublin')->firstOrFail();
    $leinster = AddressArea::query()->where('source_id', 'ie:province:leinster')->firstOrFail();
    $cork = AddressArea::query()->where('source_id', 'ie:county:cork')->firstOrFail();
    $munster = AddressArea::query()->where('source_id', 'ie:province:munster')->firstOrFail();
    $galway = AddressArea::query()->where('source_id', 'ie:county:galway')->firstOrFail();
    $connacht = AddressArea::query()->where('source_id', 'ie:province:connacht')->firstOrFail();
    $donegal = AddressArea::query()->where('source_id', 'ie:county:donegal')->firstOrFail();
    $ulster = AddressArea::query()->where('source_id', 'ie:province:ulster')->firstOrFail();

    foreach ([[$leinster, $dublin], [$munster, $cork], [$connacht, $galway], [$ulster, $donegal]] as [$parent, $child]) {
        expect(AddressAreaRelationship::query()
            ->where('parent_address_area_id', $parent->getKey())
            ->where('child_address_area_id', $child->getKey())
            ->where('hierarchy_type', 'administrative')
            ->exists())->toBeTrue();
    }
});

it('formats Irish addresses with the Eircode below the county', function (): void {
    $formatted = app(IrelandAddressFormatter::class)->format(AddressData::from([
        'line1' => '56 Broomfield',
        'city' => 'MACROOM',
        'state' => 'CO. CORK',
        'postcode' => 'T37 F8HK',
        'country_code' => 'IE',
    ]));

    expect($formatted)->toBe("56 Broomfield\nMACROOM\nCO. CORK\nT37 F8HK\nIreland");
});
it('formats Irish Dublin addresses with the district routing key', function (): void {
    $formatted = app(IrelandAddressFormatter::class)->format(AddressData::from([
        'line1' => '12 Grafton Street',
        'city' => 'DUBLIN 2',
        'state' => 'Dublin',
        'postcode' => 'D02 TF12',
        'country_code' => 'IE',
    ]));

    expect($formatted)->toBe("12 Grafton Street\nDUBLIN 2\nDublin\nD02 TF12\nIreland");
});
