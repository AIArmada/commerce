<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SaintBarthelemy\SaintBarthelemyAddressFormatter;
use AIArmada\Addressing\Geography\SaintBarthelemy\SaintBarthelemyGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SaintBarthelemyGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('overseas_collectivity')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Barthélemois tree with state links', function (): void {
    $this->seedCountry('BL');

    $result = app(SeedCountryGeographiesAction::class)->execute('BL');
    $country = AddressCountry::query()->where('iso2', 'BL')->firstOrFail();

    expect($result['seeded'])->toContain('BL')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(1)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'overseas_collectivity')->count())->toBe(1)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(1);
});

it('formats Barthélemois addresses with the single code left of the locality', function (): void {
    $formatted = app(SaintBarthelemyAddressFormatter::class)->format(AddressData::from([
        'line1' => '5 RUE VICTOR HUGO',
        'city' => 'SAINT-BARTHELEMY',
        'postcode' => '97133',
        'country_code' => 'BL',
    ]));

    expect($formatted)->toBe("5 RUE VICTOR HUGO\n97133 SAINT-BARTHELEMY\nSaint-Barthelemy");
});
it('formats Barthélemois Gustavia addresses with the same single code', function (): void {
    $formatted = app(SaintBarthelemyAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Rue de la République 2',
        'city' => 'Gustavia',
        'postcode' => '97133',
        'country_code' => 'BL',
    ]));

    expect($formatted)->toBe("Rue de la République 2\n97133 Gustavia\nSaint-Barthelemy");
});
