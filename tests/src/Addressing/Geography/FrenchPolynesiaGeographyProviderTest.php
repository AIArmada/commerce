<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\FrenchPolynesia\FrenchPolynesiaAddressFormatter;
use AIArmada\Addressing\Geography\FrenchPolynesia\FrenchPolynesiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(FrenchPolynesiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('division')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the French Polynesian tree with state links', function (): void {
    $this->seedCountry('PF');

    $result = app(SeedCountryGeographiesAction::class)->execute('PF');
    $country = AddressCountry::query()->where('iso2', 'PF')->firstOrFail();

    expect($result['seeded'])->toContain('PF')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(5)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'division')->count())->toBe(5)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(5);
});

it('formats French Polynesian addresses with the code left of the locality', function (): void {
    $formatted = app(FrenchPolynesiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BP 123',
        'city' => 'PAPEETE',
        'postcode' => '98714',
        'country_code' => 'PF',
    ]));

    expect($formatted)->toBe("BP 123\n98714 PAPEETE\nFrench Polynesia");
});

it('formats Faaa addresses with their own code', function (): void {
    $formatted = app(FrenchPolynesiaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de l’Aéroport',
        'city' => 'FAAA',
        'postcode' => '98704',
        'country_code' => 'PF',
    ]));

    expect($formatted)->toBe("Rue de l’Aéroport\n98704 FAAA\nFrench Polynesia");
});
