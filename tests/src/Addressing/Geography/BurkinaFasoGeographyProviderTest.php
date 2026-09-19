<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\BurkinaFaso\BurkinaFasoAddressFormatter;
use AIArmada\Addressing\Geography\BurkinaFaso\BurkinaFasoGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(BurkinaFasoGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Burkinabe tree with state links', function (): void {
    $this->seedCountry('BF');

    $result = app(SeedCountryGeographiesAction::class)->execute('BF');
    $country = AddressCountry::query()->where('iso2', 'BF')->firstOrFail();

    expect($result['seeded'])->toContain('BF')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(64)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(64)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(17)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(47)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(64)
        ->and(State::query()->where('country_id', $country->id)->where('name', 'Bankui')->exists())->toBeTrue()
        ->and(State::query()->where('country_id', $country->id)->where('name', 'Boucle du Mouhoun')->exists())->toBeFalse()
        ->and(State::query()->where('country_id', $country->id)->where('code', '14')->value('name'))->toBe('Sirba');
});

it('formats Burkinabe addresses with the postcode left of the locality', function (): void {
    $formatted = app(BurkinaFasoAddressFormatter::class)->format(AddressData::from([
        'line1' => '566 Avenue de la Nation',
        'city' => 'OUAGADOUGOU',
        'postcode' => '10010',
        'country_code' => 'BF',
    ]));

    expect($formatted)->toBe("566 Avenue de la Nation\n10010 OUAGADOUGOU\nBurkina Faso");
});
it('formats rural Burkinabe addresses with the region below the postcode line', function (): void {
    $formatted = app(BurkinaFasoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Secteur 3',
        'city' => 'TENKODOGO',
        'state' => 'Centre-Est',
        'postcode' => '70000',
        'country_code' => 'BF',
    ]));

    expect($formatted)->toBe("Secteur 3\n70000 TENKODOGO\nCentre-Est\nBurkina Faso");
});
