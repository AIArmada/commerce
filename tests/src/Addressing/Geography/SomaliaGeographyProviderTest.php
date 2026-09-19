<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Somalia\SomaliaAddressFormatter;
use AIArmada\Addressing\Geography\Somalia\SomaliaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SomaliaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Somali tree with state links', function (): void {
    $this->seedCountry('SO');

    $result = app(SeedCountryGeographiesAction::class)->execute('SO');
    $country = AddressCountry::query()->where('iso2', 'SO')->firstOrFail();

    expect($result['seeded'])->toContain('SO')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(18)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(18)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(18)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(18);
});

it('formats Somali addresses without an operational postcode', function (): void {
    $formatted = app(SomaliaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 1001',
        'city' => 'KISMAYU',
        'country_code' => 'SO',
    ]));

    expect($formatted)->toBe("P.O. Box 1001\nKISMAYU\nSomalia");
});
it('prints any supplied Somali paper code on its own line', function (): void {
    $formatted = app(SomaliaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 1001',
        'city' => 'KISMAYU',
        'postcode' => 'JH 09010',
        'country_code' => 'SO',
    ]));

    expect($formatted)->toBe("P.O. Box 1001\nKISMAYU\nJH 09010\nSomalia");
});
