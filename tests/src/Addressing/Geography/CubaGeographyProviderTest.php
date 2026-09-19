<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Cuba\CubaAddressFormatter;
use AIArmada\Addressing\Geography\Cuba\CubaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(CubaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Cuban tree with state links', function (): void {
    $this->seedCountry('CU');

    $result = app(SeedCountryGeographiesAction::class)->execute('CU');
    $country = AddressCountry::query()->where('iso2', 'CU')->firstOrFail();

    expect($result['seeded'])->toContain('CU')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(16)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(16)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(15)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'special_municipality')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(16);
});

it('formats Cuban addresses with the CP postcode left of the locality', function (): void {
    $formatted = app(CubaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ave Independencia s/n',
        'line2' => '19 de Mayo y Aranguren',
        'line3' => 'Habana 6',
        'city' => 'CIUDAD HABANA',
        'postcode' => 'CP 10600',
        'country_code' => 'CU',
    ]));

    expect($formatted)->toBe("Ave Independencia s/n\n19 de Mayo y Aranguren\nHabana 6\nCP 10600 CIUDAD HABANA\nCuba");
});
it('formats Cuban addresses passing bare postcodes through', function (): void {
    $formatted = app(CubaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Calle 23 No. 55',
        'city' => 'CIUDAD HABANA',
        'postcode' => '10600',
        'country_code' => 'CU',
    ]));

    expect($formatted)->toBe("Calle 23 No. 55\n10600 CIUDAD HABANA\nCuba");
});
