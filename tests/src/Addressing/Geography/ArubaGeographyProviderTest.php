<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Aruba\ArubaAddressFormatter;
use AIArmada\Addressing\Geography\Aruba\ArubaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(ArubaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Aruban tree with state links', function (): void {
    $this->seedCountry('AW');

    $result = app(SeedCountryGeographiesAction::class)->execute('AW');
    $country = AddressCountry::query()->where('iso2', 'AW')->firstOrFail();

    expect($result['seeded'])->toContain('AW')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(9)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'capital_city')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(9);
});

it('formats Aruban addresses without a postcode system', function (): void {
    $formatted = app(ArubaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Sun Plaza Suite 110',
        'line2' => 'L.G. Smith Boulevard #160',
        'city' => 'ORANJESTAD',
        'country_code' => 'AW',
    ]));

    expect($formatted)->toBe("Sun Plaza Suite 110\nL.G. Smith Boulevard #160\nORANJESTAD\nAruba");
});
it('prints any supplied Aruban code on its own line', function (): void {
    $formatted = app(ArubaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'L.G. Smith Boulevard #160',
        'city' => 'ORANJESTAD',
        'postcode' => '99999',
        'country_code' => 'AW',
    ]));

    expect($formatted)->toBe("L.G. Smith Boulevard #160\nORANJESTAD\n99999\nAruba");
});
