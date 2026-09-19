<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Kazakhstan\KazakhstanAddressFormatter;
use AIArmada\Addressing\Geography\Kazakhstan\KazakhstanGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(KazakhstanGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Kazakh tree with state links', function (): void {
    $this->seedCountry('KZ');

    $result = app(SeedCountryGeographiesAction::class)->execute('KZ');
    $country = AddressCountry::query()->where('iso2', 'KZ')->firstOrFail();

    expect($result['seeded'])->toContain('KZ')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(20)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(20)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(17)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'city')->count())->toBe(3)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(20);
});

it('formats Kazakh addresses with the postcode and comma left of the locality', function (): void {
    $formatted = app(KazakhstanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'ul. Ryskulbekov, dom16, kv 224',
        'city' => 'ASTANA',
        'postcode' => 'Z00Y5M7',
        'country_code' => 'KZ',
    ]));

    expect($formatted)->toBe("ul. Ryskulbekov, dom16, kv 224\nZ00Y5M7, ASTANA\nKazakhstan");
});
it('formats Kazakh addresses with legacy 6-digit postcodes and region', function (): void {
    $formatted = app(KazakhstanAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Abay Street 1',
        'city' => 'Taldykorgan',
        'state' => 'Jetisu',
        'postcode' => '040000',
        'country_code' => 'KZ',
    ]));

    expect($formatted)->toBe("Abay Street 1\n040000, Taldykorgan\nJetisu\nKazakhstan");
});
