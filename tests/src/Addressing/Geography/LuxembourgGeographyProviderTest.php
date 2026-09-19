<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Luxembourg\LuxembourgAddressFormatter;
use AIArmada\Addressing\Geography\Luxembourg\LuxembourgGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(LuxembourgGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('canton')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Luxembourger tree with state links', function (): void {
    $this->seedCountry('LU');

    $result = app(SeedCountryGeographiesAction::class)->execute('LU');
    $country = AddressCountry::query()->where('iso2', 'LU')->firstOrFail();

    expect($result['seeded'])->toContain('LU')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'canton')->count())->toBe(12)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(12)
        ->and(State::query()->where('country_id', $country->id)->where('code', 'GR')->exists())->toBeTrue()
        ->and(State::query()->where('country_id', $country->id)->where('code', 'G')->exists())->toBeFalse();
});

it('formats Luxembourger addresses with the postcode left of the locality', function (): void {
    $formatted = app(LuxembourgAddressFormatter::class)->format(AddressData::from([
        'line1' => '71, route de Berlin',
        'city' => 'DUDELANGE',
        'postcode' => 'L-1234',
        'country_code' => 'LU',
    ]));

    expect($formatted)->toBe("71, route de Berlin\nL-1234 DUDELANGE\nLuxembourg");
});
it('prints matching Luxembourger city and canton once', function (): void {
    $formatted = app(LuxembourgAddressFormatter::class)->format(AddressData::from([
        'line1' => '2, rue de la Gare',
        'city' => 'Luxembourg',
        'state' => 'Luxembourg',
        'postcode' => 'L-1118',
        'country_code' => 'LU',
    ]));

    expect($formatted)->toBe("2, rue de la Gare\nL-1118 Luxembourg\nLuxembourg");
});
