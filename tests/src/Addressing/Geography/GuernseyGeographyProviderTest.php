<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Guernsey\GuernseyAddressFormatter;
use AIArmada\Addressing\Geography\Guernsey\GuernseyGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(GuernseyGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('parish')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Guernsey tree with state links', function (): void {
    $this->seedCountry('GG');

    $result = app(SeedCountryGeographiesAction::class)->execute('GG');
    $country = AddressCountry::query()->where('iso2', 'GG')->firstOrFail();

    expect($result['seeded'])->toContain('GG')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'parish')->count())->toBe(12)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(12);
});

it('formats Guernsey addresses with the postcode below the post town', function (): void {
    $formatted = app(GuernseyAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Anybank House',
        'line2' => 'Le Pollet',
        'line3' => 'St Peter Port',
        'city' => 'GUERNSEY',
        'postcode' => 'GY1 1AA',
        'country_code' => 'GG',
    ]));

    expect($formatted)->toBe("Anybank House\nLe Pollet\nSt Peter Port\nGUERNSEY\nGY1 1AA\nGuernsey");
});
it('formats Sark addresses with the two-digit district postcode', function (): void {
    $formatted = app(GuernseyAddressFormatter::class)->format(AddressData::from([
        'line1' => 'La Seigneurie',
        'city' => 'SARK',
        'postcode' => 'GY10 1SF',
        'country_code' => 'GG',
    ]));

    expect($formatted)->toBe("La Seigneurie\nSARK\nGY10 1SF\nGuernsey");
});
