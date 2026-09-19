<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Belize\BelizeAddressFormatter;
use AIArmada\Addressing\Geography\Belize\BelizeGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(BelizeGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Belizean tree with state links', function (): void {
    $this->seedCountry('BZ');

    $result = app(SeedCountryGeographiesAction::class)->execute('BZ');
    $country = AddressCountry::query()->where('iso2', 'BZ')->firstOrFail();

    expect($result['seeded'])->toContain('BZ')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(6)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(6);
});

it('formats Belizean addresses without a postcode system', function (): void {
    $formatted = app(BelizeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'C Street  Apt 2',
        'city' => 'KINGS PARK, BELIZE CITY',
        'country_code' => 'BZ',
    ]));

    expect($formatted)->toBe("C Street  Apt 2\nKINGS PARK, BELIZE CITY\nBelize");
});
it('prints any supplied Belizean code on its own line', function (): void {
    $formatted = app(BelizeAddressFormatter::class)->format(AddressData::from([
        'line1' => 'C Street  Apt 2',
        'city' => 'BELIZE CITY',
        'postcode' => '99999',
        'country_code' => 'BZ',
    ]));

    expect($formatted)->toBe("C Street  Apt 2\nBELIZE CITY\n99999\nBelize");
});
