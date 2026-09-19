<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Lebanon\LebanonAddressFormatter;
use AIArmada\Addressing\Geography\Lebanon\LebanonGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(LebanonGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('governorate')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Lebanese tree with state links', function (): void {
    $this->seedCountry('LB');

    $result = app(SeedCountryGeographiesAction::class)->execute('LB');
    $country = AddressCountry::query()->where('iso2', 'LB')->firstOrFail();

    expect($result['seeded'])->toContain('LB')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'governorate')->count())->toBe(8)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(8);
});

it('formats Lebanese addresses with the postcode right of the locality', function (): void {
    $formatted = app(LebanonAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Building Al Amal, 2nd floor',
        'line2' => 'Australia Street',
        'city' => 'Raoucheh',
        'state' => 'Beirut',
        'postcode' => '1107 2080',
        'country_code' => 'LB',
    ]));

    expect($formatted)->toBe("Building Al Amal, 2nd floor\nAustralia Street\nRaoucheh 1107 2080\nBeirut\nLebanon");
});
it('formats Lebanese addresses without a postcode when missing', function (): void {
    $formatted = app(LebanonAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Building Al Amal, 2nd floor',
        'city' => 'Raoucheh',
        'state' => 'Beirut',
        'country_code' => 'LB',
    ]));

    expect($formatted)->toBe("Building Al Amal, 2nd floor\nRaoucheh\nBeirut\nLebanon");
});
