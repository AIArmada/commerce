<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Lithuania\LithuaniaAddressFormatter;
use AIArmada\Addressing\Geography\Lithuania\LithuaniaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(LithuaniaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('county')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Lithuanian tree with state links', function (): void {
    $this->seedCountry('LT');

    $result = app(SeedCountryGeographiesAction::class)->execute('LT');
    $country = AddressCountry::query()->where('iso2', 'LT')->firstOrFail();

    expect($result['seeded'])->toContain('LT')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(70)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(70)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'county')->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district_municipality')->count())->toBe(49)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city_municipality')->count())->toBe(2)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(70);
});

it('formats Lithuanian inbound addresses with the LT postcode prefix', function (): void {
    $formatted = app(LithuaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Laisvės pr. 40-12',
        'city' => 'Vilnius',
        'postcode' => 'LT-04340',
        'country_code' => 'LT',
    ]));

    expect($formatted)->toBe("Laisvės pr. 40-12\nLT-04340 Vilnius\nLithuania");
});
it('formats Lithuanian domestic addresses with a bare postcode', function (): void {
    $formatted = app(LithuaniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Laisvės al. 60',
        'city' => 'Kaunas',
        'postcode' => '44280',
        'country_code' => 'LT',
    ]));

    expect($formatted)->toBe("Laisvės al. 60\n44280 Kaunas\nLithuania");
});
