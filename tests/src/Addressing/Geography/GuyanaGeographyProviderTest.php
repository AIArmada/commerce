<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Guyana\GuyanaAddressFormatter;
use AIArmada\Addressing\Geography\Guyana\GuyanaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(GuyanaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Guyanese tree with state links', function (): void {
    $this->seedCountry('GY');

    $result = app(SeedCountryGeographiesAction::class)->execute('GY');
    $country = AddressCountry::query()->where('iso2', 'GY')->firstOrFail();

    expect($result['seeded'])->toContain('GY')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(10)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(10);
});

it('formats Guyanese addresses with the postcode below the locality', function (): void {
    $formatted = app(GuyanaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Room 15',
        'line2' => '183/185 Marja Bulding',
        'line3' => 'Lacytown',
        'city' => 'Georgetown',
        'postcode' => '4130106',
        'country_code' => 'GY',
    ]));

    expect($formatted)->toBe("Room 15\n183/185 Marja Bulding\nLacytown\nGeorgetown\n4130106\nGuyana");
});
it('formats Guyanese East Coast addresses with the district postcode', function (): void {
    $formatted = app(GuyanaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Lot 12 Public Road',
        'city' => 'East Coast Demerara',
        'postcode' => '4212501',
        'country_code' => 'GY',
    ]));

    expect($formatted)->toBe("Lot 12 Public Road\nEast Coast Demerara\n4212501\nGuyana");
});
