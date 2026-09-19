<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Seychelles\SeychellesAddressFormatter;
use AIArmada\Addressing\Geography\Seychelles\SeychellesGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SeychellesGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Seychellois tree with state links', function (): void {
    $this->seedCountry('SC');

    $result = app(SeedCountryGeographiesAction::class)->execute('SC');
    $country = AddressCountry::query()->where('iso2', 'SC')->firstOrFail();

    expect($result['seeded'])->toContain('SC')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(27)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(27)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(27)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(27);
});

it('formats Seychellois addresses without a postcode system', function (): void {
    $formatted = app(SeychellesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'S19 - E36',
        'city' => 'Victoria',
        'state' => 'Mahé',
        'country_code' => 'SC',
    ]));

    expect($formatted)->toBe("S19 - E36\nVictoria\nMahé\nSeychelles");
});
it('prints any supplied Seychellois code on its own line', function (): void {
    $formatted = app(SeychellesAddressFormatter::class)->format(AddressData::from([
        'line1' => 'P.O. Box 1538',
        'city' => 'Victoria',
        'postcode' => '99999',
        'country_code' => 'SC',
    ]));

    expect($formatted)->toBe("P.O. Box 1538\nVictoria\n99999\nSeychelles");
});
