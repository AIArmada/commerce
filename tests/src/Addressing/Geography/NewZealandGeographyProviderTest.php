<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\NewZealand\NewZealandAddressFormatter;
use AIArmada\Addressing\Geography\NewZealand\NewZealandGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(NewZealandGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the New Zealand tree with state links', function (): void {
    $this->seedCountry('NZ');

    $result = app(SeedCountryGeographiesAction::class)->execute('NZ');
    $country = AddressCountry::query()->where('iso2', 'NZ')->firstOrFail();

    expect($result['seeded'])->toContain('NZ')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(17)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(17)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(16)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'special_island_authority')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(17);
});

it('formats New Zealand addresses with the code left of the locality', function (): void {
    $formatted = app(NewZealandAddressFormatter::class)->format(AddressData::from([
        'line1' => '100 Queen Street',
        'city' => 'Auckland',
        'postcode' => '1010',
        'country_code' => 'NZ',
    ]));

    expect($formatted)->toBe("100 Queen Street\n1010 Auckland\nNew Zealand");
});

it('formats Wellington addresses with their own code', function (): void {
    $formatted = app(NewZealandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Wellington',
        'postcode' => '6011',
        'country_code' => 'NZ',
    ]));

    expect($formatted)->toBe("PO Box 1\n6011 Wellington\nNew Zealand");
});
