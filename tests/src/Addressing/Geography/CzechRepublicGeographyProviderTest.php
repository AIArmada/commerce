<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\CzechRepublic\CzechRepublicAddressFormatter;
use AIArmada\Addressing\Geography\CzechRepublic\CzechRepublicGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a two-level region and district hierarchy', function (): void {
    $hierarchies = app(CzechRepublicGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(2)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('district')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('region');
});

it('imports the region and district trees with state links', function (): void {
    $this->seedCountry('CZ');

    $result = app(SeedCountryGeographiesAction::class)->execute('CZ');
    $country = AddressCountry::query()->where('iso2', 'CZ')->firstOrFail();

    expect($result['seeded'])->toContain('CZ')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(90)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(13)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(76)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'capital_city')->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('level', 2)->count())->toBe(76)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(14);

    $kladno = AddressArea::query()->where('source_id', 'cz:district:kladno')->firstOrFail();
    $stredocesky = AddressArea::query()->where('source_id', 'cz:region:stredocesky-kraj')->firstOrFail();
    $brnoMesto = AddressArea::query()->where('source_id', 'cz:district:brno-mesto')->firstOrFail();
    $jihomoravsky = AddressArea::query()->where('source_id', 'cz:region:jihomoravsky-kraj')->firstOrFail();

    expect(AddressAreaRelationship::query()
        ->where('parent_address_area_id', $stredocesky->getKey())
        ->where('child_address_area_id', $kladno->getKey())
        ->where('hierarchy_type', 'administrative')
        ->exists())->toBeTrue()
        ->and(AddressAreaRelationship::query()
            ->where('parent_address_area_id', $jihomoravsky->getKey())
            ->where('child_address_area_id', $brnoMesto->getKey())
            ->where('hierarchy_type', 'administrative')
            ->exists())->toBeTrue();
});

it('formats Czech addresses with the spaced postcode left of the locality', function (): void {
    $formatted = app(CzechRepublicAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Hrušovská 455/10',
        'city' => 'Praha 102',
        'postcode' => '102 00',
        'country_code' => 'CZ',
    ]));

    expect($formatted)->toBe("Hrušovská 455/10\n102 00 Praha 102\nCzech Republic");
});
it('formats Czech rural addresses with the region below the postcode line', function (): void {
    $formatted = app(CzechRepublicAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Roprachtice 129',
        'city' => 'Roprachtice',
        'state' => 'Liberecký kraj',
        'postcode' => '513 01',
        'country_code' => 'CZ',
    ]));

    expect($formatted)->toBe("Roprachtice 129\n513 01 Roprachtice\nLiberecký kraj\nCzech Republic");
});
