<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\CzechRepublic\CzechRepublicAddressFormatter;
use AIArmada\Addressing\Geography\CzechRepublic\CzechRepublicGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(CzechRepublicGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Czech tree with state links', function (): void {
    $this->seedCountry('CZ');

    $result = app(SeedCountryGeographiesAction::class)->execute('CZ');
    $country = AddressCountry::query()->where('iso2', 'CZ')->firstOrFail();

    expect($result['seeded'])->toContain('CZ')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(90)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(90)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(13)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(76)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'capital_city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(90);
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
