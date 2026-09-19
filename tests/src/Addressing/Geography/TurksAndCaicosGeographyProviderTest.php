<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\TurksAndCaicos\TurksAndCaicosAddressFormatter;
use AIArmada\Addressing\Geography\TurksAndCaicos\TurksAndCaicosGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(TurksAndCaicosGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Turks and Caicos Islander tree with state links', function (): void {
    $this->seedCountry('TC');

    $result = app(SeedCountryGeographiesAction::class)->execute('TC');
    $country = AddressCountry::query()->where('iso2', 'TC')->firstOrFail();

    expect($result['seeded'])->toContain('TC')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(6)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(6);
});

it('formats Turks and Caicos addresses with the single code below the locality', function (): void {
    $formatted = app(TurksAndCaicosAddressFormatter::class)->format(AddressData::from([
        'line1' => 'George Brown Post Office',
        'line2' => 'Airport road',
        'city' => 'DOWNTOWN, PROVIDENCIALES',
        'postcode' => 'TKCA 1ZZ',
        'country_code' => 'TC',
    ]));

    expect($formatted)->toBe("George Brown Post Office\nAirport road\nDOWNTOWN, PROVIDENCIALES\nTKCA 1ZZ\nTurks and Caicos Islands");
});
it('formats Turks and Caicos addresses without a postcode when missing', function (): void {
    $formatted = app(TurksAndCaicosAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Airport road',
        'city' => 'DOWNTOWN, PROVIDENCIALES',
        'country_code' => 'TC',
    ]));

    expect($formatted)->toBe("Airport road\nDOWNTOWN, PROVIDENCIALES\nTurks and Caicos Islands");
});
