<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Estonia\EstoniaAddressFormatter;
use AIArmada\Addressing\Geography\Estonia\EstoniaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a two-level county and municipality hierarchy', function (): void {
    $hierarchies = app(EstoniaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(2)
        ->and($hierarchies[0]->levels[0]->key)->toBe('county')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('county')
        ->and($hierarchies[0]->levels[1]->assignmentRole)->toBe('municipality');
});

it('imports the county and municipality trees with state links', function (): void {
    $this->seedCountry('EE');

    $result = app(SeedCountryGeographiesAction::class)->execute('EE');
    $country = AddressCountry::query()->where('iso2', 'EE')->firstOrFail();

    expect($result['seeded'])->toContain('EE')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(15)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(93)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'county')->count())->toBe(15)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'rural_municipality')->count())->toBe(63)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'urban_municipality')->count())->toBe(15)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('level', 2)->count())->toBe(78)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(15);

    $tallinn = AddressArea::query()->where('source_id', 'ee:urban_municipality:tallinn')->firstOrFail();
    $harju = AddressArea::query()->where('source_id', 'ee:county:harju')->firstOrFail();
    $tartuRural = AddressArea::query()->where('source_id', 'ee:rural_municipality:tartu')->firstOrFail();
    $tartu = AddressArea::query()->where('source_id', 'ee:county:tartu')->firstOrFail();

    expect(AddressAreaRelationship::query()
        ->where('parent_address_area_id', $harju->getKey())
        ->where('child_address_area_id', $tallinn->getKey())
        ->where('hierarchy_type', 'administrative')
        ->exists())->toBeTrue()
        ->and(AddressAreaRelationship::query()
            ->where('parent_address_area_id', $tartu->getKey())
            ->where('child_address_area_id', $tartuRural->getKey())
            ->where('hierarchy_type', 'administrative')
            ->exists())->toBeTrue();
});

it('formats Estonian addresses with the postcode left of the locality', function (): void {
    $formatted = app(EstoniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Astri 6–1',
        'city' => 'TALLINN',
        'postcode' => '11212',
        'country_code' => 'EE',
    ]));

    expect($formatted)->toBe("Astri 6–1\n11212 TALLINN\nEstonia");
});
it('formats Estonian rural addresses with the county on the postcode line', function (): void {
    $formatted = app(EstoniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Allika talu',
        'line2' => 'Halliste alevik',
        'city' => 'VILJANDIMAA',
        'postcode' => '69501',
        'country_code' => 'EE',
    ]));

    expect($formatted)->toBe("Allika talu\nHalliste alevik\n69501 VILJANDIMAA\nEstonia");
});
