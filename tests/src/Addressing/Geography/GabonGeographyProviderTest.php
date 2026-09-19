<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Gabon\GabonAddressFormatter;
use AIArmada\Addressing\Geography\Gabon\GabonGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(GabonGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Gabonese tree with state links', function (): void {
    $this->seedCountry('GA');

    $result = app(SeedCountryGeographiesAction::class)->execute('GA');
    $country = AddressCountry::query()->where('iso2', 'GA')->firstOrFail();

    expect($result['seeded'])->toContain('GA')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(9)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(9);
});

it('formats Gabonese addresses with the zone left of the locality', function (): void {
    $formatted = app(GabonAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 13210',
        'city' => 'LIBREVILLE',
        'postcode' => '01',
        'country_code' => 'GA',
    ]));

    expect($formatted)->toBe("BP 13210\n01 LIBREVILLE\nGabon");
});
it('formats Gabonese addresses with the province below the postcode line', function (): void {
    $formatted = app(GabonAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 45',
        'city' => 'TCHIBANGA',
        'state' => 'Nyanga',
        'postcode' => '05',
        'country_code' => 'GA',
    ]));

    expect($formatted)->toBe("BP 45\n05 TCHIBANGA\nNyanga\nGabon");
});
