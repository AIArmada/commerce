<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\USMinorOutlyingIslands\USMinorOutlyingIslandsAddressFormatter;
use AIArmada\Addressing\Geography\USMinorOutlyingIslands\USMinorOutlyingIslandsGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(USMinorOutlyingIslandsGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('island')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the US Minor Outlying Islands tree with state links', function (): void {
    $this->seedCountry('UM');

    $result = app(SeedCountryGeographiesAction::class)->execute('UM');
    $country = AddressCountry::query()->where('iso2', 'UM')->firstOrFail();

    expect($result['seeded'])->toContain('UM')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'island')->count())->toBe(9)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(9);
});

it('formats US Minor Outlying Islands addresses without a postcode system', function (): void {
    $formatted = app(USMinorOutlyingIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Wake Island Airfield',
        'city' => 'Wake Island',
        'country_code' => 'UM',
    ]));

    expect($formatted)->toBe("Wake Island Airfield\nWake Island\nUnited States Minor Outlying Islands");
});

it('prints any supplied Wake code on its own line', function (): void {
    $formatted = app(USMinorOutlyingIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 501',
        'city' => 'Wake Island',
        'postcode' => '96898',
        'country_code' => 'UM',
    ]));

    expect($formatted)->toBe("PO Box 501\nWake Island\n96898\nUnited States Minor Outlying Islands");
});
