<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\CostaRica\CostaRicaAddressFormatter;
use AIArmada\Addressing\Geography\CostaRica\CostaRicaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(CostaRicaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Costa Rican tree with state links', function (): void {
    $this->seedCountry('CR');

    $result = app(SeedCountryGeographiesAction::class)->execute('CR');
    $country = AddressCountry::query()->where('iso2', 'CR')->firstOrFail();

    expect($result['seeded'])->toContain('CR')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(7)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(7)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(7);
});

it('formats Costa Rican addresses with the postcode above the country', function (): void {
    $formatted = app(CostaRicaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Ca 15 Av 37 # 55',
        'city' => 'Heredia, San Rafael, San Rafael',
        'postcode' => '40501',
        'country_code' => 'CR',
    ]));

    expect($formatted)->toBe("Ca 15 Av 37 # 55\nHeredia, San Rafael, San Rafael\n40501\nCosta Rica");
});
it('formats Costa Rican box addresses with the combined box postcode', function (): void {
    $formatted = app(CostaRicaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Apdo 257 – 3017',
        'city' => 'Heredia, San Isidro, San Isidro',
        'postcode' => '3017-40601',
        'country_code' => 'CR',
    ]));

    expect($formatted)->toBe("Apdo 257 – 3017\nHeredia, San Isidro, San Isidro\n3017-40601\nCosta Rica");
});
