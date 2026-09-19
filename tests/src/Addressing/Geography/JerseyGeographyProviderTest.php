<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Jersey\JerseyAddressFormatter;
use AIArmada\Addressing\Geography\Jersey\JerseyGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(JerseyGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('parish')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Jersey tree with state links', function (): void {
    $this->seedCountry('JE');

    $result = app(SeedCountryGeographiesAction::class)->execute('JE');
    $country = AddressCountry::query()->where('iso2', 'JE')->firstOrFail();

    expect($result['seeded'])->toContain('JE')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(12)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'parish')->count())->toBe(12)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(12);
});

it('formats Jersey addresses with the postcode below the post town', function (): void {
    $formatted = app(JerseyAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Town View',
        'line2' => 'Stopford Road',
        'line3' => 'St Helier',
        'city' => 'JERSEY',
        'postcode' => 'JE2 4LB',
        'country_code' => 'JE',
    ]));

    expect($formatted)->toBe("Town View\nStopford Road\nSt Helier\nJERSEY\nJE2 4LB\nJersey");
});
it('formats Jersey town addresses with the parish postcode', function (): void {
    $formatted = app(JerseyAddressFormatter::class)->format(AddressData::from([
        'line1' => '5 Esplanade',
        'city' => 'St Helier',
        'postcode' => 'JE1 1AA',
        'country_code' => 'JE',
    ]));

    expect($formatted)->toBe("5 Esplanade\nSt Helier\nJE1 1AA\nJersey");
});
