<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SaintMartin\SaintMartinAddressFormatter;
use AIArmada\Addressing\Geography\SaintMartin\SaintMartinGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SaintMartinGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('overseas_collectivity')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Saint-Martinois tree with state links', function (): void {
    $this->seedCountry('MF');

    $result = app(SeedCountryGeographiesAction::class)->execute('MF');
    $country = AddressCountry::query()->where('iso2', 'MF')->firstOrFail();

    expect($result['seeded'])->toContain('MF')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'overseas_collectivity')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(1);
});

it('formats Saint-Martinois addresses with the single code left of the locality', function (): void {
    $formatted = app(SaintMartinAddressFormatter::class)->format(AddressData::from([
        'line1' => '5 RUE DES ACACIAS',
        'city' => 'SAINT-MARTIN',
        'postcode' => '97150',
        'country_code' => 'MF',
    ]));

    expect($formatted)->toBe("5 RUE DES ACACIAS\n97150 SAINT-MARTIN\nSaint-Martin (French part)");
});
it('formats Saint-Martinois Marigot addresses with the same single code', function (): void {
    $formatted = app(SaintMartinAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la République 4',
        'city' => 'Marigot',
        'postcode' => '97150',
        'country_code' => 'MF',
    ]));

    expect($formatted)->toBe("Rue de la République 4\n97150 Marigot\nSaint-Martin (French part)");
});
