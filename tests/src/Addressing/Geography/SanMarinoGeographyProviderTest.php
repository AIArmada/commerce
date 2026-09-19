<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SanMarino\SanMarinoAddressFormatter;
use AIArmada\Addressing\Geography\SanMarino\SanMarinoGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SanMarinoGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('municipality')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Sammarinese tree with state links', function (): void {
    $this->seedCountry('SM');

    $result = app(SeedCountryGeographiesAction::class)->execute('SM');
    $country = AddressCountry::query()->where('iso2', 'SM')->firstOrFail();

    expect($result['seeded'])->toContain('SM')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'municipality')->count())->toBe(9)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(9);
});

it('formats Sammarinese addresses with the postcode left of the locality', function (): void {
    $formatted = app(SanMarinoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Contrada Omerelli 17',
        'city' => 'SAN MARINO',
        'postcode' => '47890',
        'country_code' => 'SM',
    ]));

    expect($formatted)->toBe("Contrada Omerelli 17\n47890 SAN MARINO\nSan Marino");
});
it('formats Sammarinese Serravalle addresses with the town postcode', function (): void {
    $formatted = app(SanMarinoAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Via del Serrone 12',
        'city' => 'Serravalle',
        'postcode' => '47899',
        'country_code' => 'SM',
    ]));

    expect($formatted)->toBe("Via del Serrone 12\n47899 Serravalle\nSan Marino");
});
