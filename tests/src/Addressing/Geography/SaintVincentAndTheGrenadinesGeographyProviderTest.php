<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SaintVincentAndTheGrenadines\SaintVincentAndTheGrenadinesAddressFormatter;
use AIArmada\Addressing\Geography\SaintVincentAndTheGrenadines\SaintVincentAndTheGrenadinesGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SaintVincentAndTheGrenadinesGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('parish')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Vincentian tree with state links', function (): void {
    $this->seedCountry('VC');

    $result = app(SeedCountryGeographiesAction::class)->execute('VC');
    $country = AddressCountry::query()->where('iso2', 'VC')->firstOrFail();

    expect($result['seeded'])->toContain('VC')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(6)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'parish')->count())->toBe(6)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(6);
});

it('formats Vincentian addresses with the postcode below the locality', function (): void {
    $formatted = app(SaintVincentAndTheGrenadinesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'HALIFAX STREET',
        'city' => 'KINGSTOWN',
        'postcode' => 'VC0120',
        'country_code' => 'VC',
    ]));

    expect($formatted)->toBe("HALIFAX STREET\nKINGSTOWN\nVC0120\nSaint Vincent and the Grenadines");
});
it('formats Bequia addresses with the island postcode', function (): void {
    $formatted = app(SaintVincentAndTheGrenadinesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O BOX BQ400',
        'city' => 'BEQUIA',
        'postcode' => 'VC0400',
        'country_code' => 'VC',
    ]));

    expect($formatted)->toBe("P.O BOX BQ400\nBEQUIA\nVC0400\nSaint Vincent and the Grenadines");
});
