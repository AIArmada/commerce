<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Jamaica\JamaicaAddressFormatter;
use AIArmada\Addressing\Geography\Jamaica\JamaicaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(JamaicaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('parish')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Jamaican tree with state links', function (): void {
    $this->seedCountry('JM');

    $result = app(SeedCountryGeographiesAction::class)->execute('JM');
    $country = AddressCountry::query()->where('iso2', 'JM')->firstOrFail();

    expect($result['seeded'])->toContain('JM')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(14)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'parish')->count())->toBe(14)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(14);
});

it('formats Jamaican addresses without a national postcode', function (): void {
    $formatted = app(JamaicaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Lot 19 Mona Estate',
        'line2' => 'Bog Walk',
        'line3' => 'Bog Walk PO',
        'city' => 'St. Catherine',
        'country_code' => 'JM',
    ]));

    expect($formatted)->toBe("Lot 19 Mona Estate\nBog Walk\nBog Walk PO\nSt. Catherine\nJamaica");
});
it('formats Jamaican Kingston addresses keeping the sector in the locality', function (): void {
    $formatted = app(JamaicaAddressFormatter::class)->format(AddressData::from([
        'line1' => '15 Molynes Road',
        'city' => 'Kingston 10',
        'country_code' => 'JM',
    ]));

    expect($formatted)->toBe("15 Molynes Road\nKingston 10\nJamaica");
});
