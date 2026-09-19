<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\EquatorialGuinea\EquatorialGuineaAddressFormatter;
use AIArmada\Addressing\Geography\EquatorialGuinea\EquatorialGuineaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaRelationship;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a two-level region and province hierarchy', function (): void {
    $hierarchies = app(EquatorialGuineaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(2)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state')
        ->and($hierarchies[0]->levels[1]->key)->toBe('province')
        ->and($hierarchies[0]->levels[1]->parentKey)->toBe('region')
        ->and($hierarchies[0]->levels[1]->assignmentRole)->toBe('province');
});

it('imports the region and province trees with state links', function (): void {
    $this->seedCountry('GQ');

    $result = app(SeedCountryGeographiesAction::class)->execute('GQ');
    $country = AddressCountry::query()->where('iso2', 'GQ')->firstOrFail();

    expect($result['seeded'])->toContain('GQ')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(2)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('level', 2)->count())->toBe(8)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(2);

    $bioko = AddressArea::query()->where('source_id', 'gq:province:bioko-norte')->firstOrFail();
    $insular = AddressArea::query()->where('source_id', 'gq:region:insular')->firstOrFail();
    $djibloho = AddressArea::query()->where('source_id', 'gq:province:djibloho')->firstOrFail();
    $rioMuni = AddressArea::query()->where('source_id', 'gq:region:rio-muni')->firstOrFail();

    expect(AddressAreaRelationship::query()
        ->where('parent_address_area_id', $insular->getKey())
        ->where('child_address_area_id', $bioko->getKey())
        ->where('hierarchy_type', 'administrative')
        ->exists())->toBeTrue()
        ->and(AddressAreaRelationship::query()
            ->where('parent_address_area_id', $rioMuni->getKey())
            ->where('child_address_area_id', $djibloho->getKey())
            ->where('hierarchy_type', 'administrative')
            ->exists())->toBeTrue();
});

it('formats Equatoguinean addresses without a postcode system', function (): void {
    $formatted = app(EquatorialGuineaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Viviendas sociales vicatana',
        'line2' => 'Portal 5, puerta 29',
        'city' => 'MALABO',
        'state' => 'Bioko Norte',
        'country_code' => 'GQ',
    ]));

    expect($formatted)->toBe("Viviendas sociales vicatana\nPortal 5, puerta 29\nMALABO\nBioko Norte\nEquatorial Guinea");
});
it('prints any supplied Equatoguinean code on its own line', function (): void {
    $formatted = app(EquatorialGuineaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Apartado postal 7',
        'city' => 'MALABO',
        'postcode' => '99999',
        'country_code' => 'GQ',
    ]));

    expect($formatted)->toBe("Apartado postal 7\nMALABO\n99999\nEquatorial Guinea");
});
