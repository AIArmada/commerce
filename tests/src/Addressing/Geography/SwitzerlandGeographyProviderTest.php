<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Switzerland\SwitzerlandAddressFormatter;
use AIArmada\Addressing\Geography\Switzerland\SwitzerlandGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SwitzerlandGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('canton')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Swiss tree with state links', function (): void {
    $this->seedCountry('CH');

    $result = app(SeedCountryGeographiesAction::class)->execute('CH');
    $country = AddressCountry::query()->where('iso2', 'CH')->firstOrFail();

    expect($result['seeded'])->toContain('CH')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(26)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(26)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'canton')->count())->toBe(26)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(26);
});

it('formats Swiss addresses with the postcode left of the town', function (): void {
    $formatted = app(SwitzerlandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Solothurnerstrasse 28',
        'city' => 'BETTLACH',
        'postcode' => '2544',
        'country_code' => 'CH',
    ]));

    expect($formatted)->toBe("Solothurnerstrasse 28\n2544 BETTLACH\nSwitzerland");
});
it('formats Swiss addresses passing canton suffixes through', function (): void {
    $formatted = app(SwitzerlandAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Route de la Gare 2',
        'city' => 'CERNIAZ VD',
        'postcode' => '1556',
        'country_code' => 'CH',
    ]));

    expect($formatted)->toBe("Route de la Gare 2\n1556 CERNIAZ VD\nSwitzerland");
});
