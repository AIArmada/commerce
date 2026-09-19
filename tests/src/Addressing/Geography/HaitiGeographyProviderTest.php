<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Haiti\HaitiAddressFormatter;
use AIArmada\Addressing\Geography\Haiti\HaitiGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(HaitiGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('department')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Haitian tree with state links', function (): void {
    $this->seedCountry('HT');

    $result = app(SeedCountryGeographiesAction::class)->execute('HT');
    $country = AddressCountry::query()->where('iso2', 'HT')->firstOrFail();

    expect($result['seeded'])->toContain('HT')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'department')->count())->toBe(10)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(10);
});

it('formats Haitian addresses with the HT postcode left of the locality', function (): void {
    $formatted = app(HaitiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue Samba 1',
        'city' => 'DELMAS',
        'postcode' => 'HT6120',
        'country_code' => 'HT',
    ]));

    expect($formatted)->toBe("Rue Samba 1\nHT6120 DELMAS\nHaiti");
});
it('formats Haitian capital addresses with the Port-au-Prince postcode', function (): void {
    $formatted = app(HaitiAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue Capois 5',
        'city' => 'PORT-AU-PRINCE',
        'postcode' => 'HT6110',
        'country_code' => 'HT',
    ]));

    expect($formatted)->toBe("Rue Capois 5\nHT6110 PORT-AU-PRINCE\nHaiti");
});
