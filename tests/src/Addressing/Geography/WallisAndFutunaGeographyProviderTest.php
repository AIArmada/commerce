<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\WallisAndFutuna\WallisAndFutunaAddressFormatter;
use AIArmada\Addressing\Geography\WallisAndFutuna\WallisAndFutunaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(WallisAndFutunaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('administrative_precinct')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Wallis and Futuna tree with state links', function (): void {
    $this->seedCountry('WF');

    $result = app(SeedCountryGeographiesAction::class)->execute('WF');
    $country = AddressCountry::query()->where('iso2', 'WF')->firstOrFail();

    expect($result['seeded'])->toContain('WF')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'administrative_precinct')->count())->toBe(3)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(3);
});

it('formats Wallis and Futuna addresses with the code left of the locality', function (): void {
    $formatted = app(WallisAndFutunaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Route de Mata-Utu',
        'city' => 'MATA-UTU',
        'postcode' => '98600',
        'country_code' => 'WF',
    ]));

    expect($formatted)->toBe("Route de Mata-Utu\n98600 MATA-UTU\nWallis and Futuna Islands");
});

it('formats Futuna addresses with their own code', function (): void {
    $formatted = app(WallisAndFutunaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 5',
        'city' => 'LEAVA',
        'postcode' => '98620',
        'country_code' => 'WF',
    ]));

    expect($formatted)->toBe("BP 5\n98620 LEAVA\nWallis and Futuna Islands");
});
