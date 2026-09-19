<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\DominicanRepublic\DominicanRepublicAddressFormatter;
use AIArmada\Addressing\Geography\DominicanRepublic\DominicanRepublicGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a two-level region and province hierarchy', function (): void {
    $hierarchies = app(DominicanRepublicGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(2)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('province')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('region');
});

it('imports the region and province trees with state links', function (): void {
    $this->seedCountry('DO');

    $result = app(SeedCountryGeographiesAction::class)->execute('DO');
    $country = AddressCountry::query()->where('iso2', 'DO')->firstOrFail();

    expect($result['seeded'])->toContain('DO')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(42)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(31)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('level', 2)->count())->toBe(32)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(10);

    $santiago = AddressArea::query()->where('source_id', 'do:province:santiago')->firstOrFail();
    $cibaoNorte = AddressArea::query()->where('source_id', 'do:region:cibao-norte')->firstOrFail();
    $distrito = AddressArea::query()->where('source_id', 'do:district:distrito-nacional')->firstOrFail();
    $ozama = AddressArea::query()->where('source_id', 'do:region:ozama')->firstOrFail();

    expect(AddressAreaRelationship::query()
        ->where('parent_address_area_id', $cibaoNorte->getKey())
        ->where('child_address_area_id', $santiago->getKey())
        ->where('hierarchy_type', 'administrative')
        ->exists())->toBeTrue()
        ->and(AddressAreaRelationship::query()
            ->where('parent_address_area_id', $ozama->getKey())
            ->where('child_address_area_id', $distrito->getKey())
            ->where('hierarchy_type', 'administrative')
            ->exists())->toBeTrue();
});

it('formats Dominican Republic addresses with the postcode left of the locality', function (): void {
    $formatted = app(DominicanRepublicAddressFormatter::class)->format(AddressData::from([
        'line1' => 'C/45 # 33',
        'line2' => 'Katanga, Los Minas',
        'city' => 'SANTO DOMINGO',
        'postcode' => '11903',
        'country_code' => 'DO',
    ]));

    expect($formatted)->toBe("C/45 # 33\nKatanga, Los Minas\n11903 SANTO DOMINGO\nDominican Republic");
});
it('formats Dominican Republic Santiago addresses with the town postcode', function (): void {
    $formatted = app(DominicanRepublicAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Calle del Sol 12',
        'city' => 'SANTIAGO',
        'postcode' => '51000',
        'country_code' => 'DO',
    ]));

    expect($formatted)->toBe("Calle del Sol 12\n51000 SANTIAGO\nDominican Republic");
});
