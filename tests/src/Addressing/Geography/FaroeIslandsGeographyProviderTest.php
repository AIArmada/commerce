<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\FaroeIslands\FaroeIslandsAddressFormatter;
use AIArmada\Addressing\Geography\FaroeIslands\FaroeIslandsGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(FaroeIslandsGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('region')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Faroese tree with state links', function (): void {
    $this->seedCountry('FO');

    $result = app(SeedCountryGeographiesAction::class)->execute('FO');
    $country = AddressCountry::query()->where('iso2', 'FO')->firstOrFail();

    expect($result['seeded'])->toContain('FO')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'region')->count())->toBe(6)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(6);
});

it('formats Faroese addresses with the postcode left of the locality', function (): void {
    $formatted = app(FaroeIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Óðinshædd 2',
        'city' => 'Tórshavn',
        'postcode' => 'FO-100',
        'country_code' => 'FO',
    ]));

    expect($formatted)->toBe("Óðinshædd 2\nFO-100 Tórshavn\nFaroe Islands");
});
it('formats Faroese northern addresses with the town postcode', function (): void {
    $formatted = app(FaroeIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Bøgøta 5',
        'city' => 'Klaksvík',
        'postcode' => 'FO-700',
        'country_code' => 'FO',
    ]));

    expect($formatted)->toBe("Bøgøta 5\nFO-700 Klaksvík\nFaroe Islands");
});
