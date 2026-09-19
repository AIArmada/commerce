<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Sweden\SwedenAddressFormatter;
use AIArmada\Addressing\Geography\Sweden\SwedenGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SwedenGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('county')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Swedish tree with state links', function (): void {
    $this->seedCountry('SE');

    $result = app(SeedCountryGeographiesAction::class)->execute('SE');
    $country = AddressCountry::query()->where('iso2', 'SE')->firstOrFail();

    expect($result['seeded'])->toContain('SE')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(21)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(21)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'county')->count())->toBe(21)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(21);
});

it('formats Swedish addresses with the spaced postcode left of the locality', function (): void {
    $formatted = app(SwedenAddressFormatter::class)->format(AddressData::from([
        'line1' => 'NYBY 10',
        'city' => 'LILLBYN',
        'postcode' => '123 45',
        'country_code' => 'SE',
    ]));

    expect($formatted)->toBe("NYBY 10\n123 45 LILLBYN\nSweden");
});
it('formats Swedish box addresses with the box postcode', function (): void {
    $formatted = app(SwedenAddressFormatter::class)->format(AddressData::from([
        'line1' => 'BOX 222',
        'city' => 'STOCKHOLM',
        'postcode' => '111 81',
        'country_code' => 'SE',
    ]));

    expect($formatted)->toBe("BOX 222\n111 81 STOCKHOLM\nSweden");
});
