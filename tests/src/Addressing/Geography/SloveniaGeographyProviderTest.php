<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Slovenia\SloveniaAddressFormatter;
use AIArmada\Addressing\Geography\Slovenia\SloveniaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SloveniaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Slovenian tree with state links', function (): void {
    $this->seedCountry('SI');

    $result = app(SeedCountryGeographiesAction::class)->execute('SI');
    $country = AddressCountry::query()->where('iso2', 'SI')->firstOrFail();

    expect($result['seeded'])->toContain('SI')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(212)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(212)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(200)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'urban_municipality')->count())->toBe(12)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(212);
});

it('formats Slovenian addresses with the postcode left of the locality', function (): void {
    $formatted = app(SloveniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Prešemova ul. 16',
        'city' => 'KRANJ',
        'postcode' => '4000',
        'country_code' => 'SI',
    ]));

    expect($formatted)->toBe("Prešemova ul. 16\n4000 KRANJ\nSlovenia");
});
it('formats Slovenian addresses passing SI prefixes through', function (): void {
    $formatted = app(SloveniaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Slovenska cesta 1',
        'city' => 'LJUBLJANA',
        'postcode' => 'SI-1000',
        'country_code' => 'SI',
    ]));

    expect($formatted)->toBe("Slovenska cesta 1\nSI-1000 LJUBLJANA\nSlovenia");
});
