<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SaintHelena\SaintHelenaAddressFormatter;
use AIArmada\Addressing\Geography\SaintHelena\SaintHelenaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SaintHelenaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Saint Helenian tree with state links', function (): void {
    $this->seedCountry('SH');

    $result = app(SeedCountryGeographiesAction::class)->execute('SH');
    $country = AddressCountry::query()->where('iso2', 'SH')->firstOrFail();

    expect($result['seeded'])->toContain('SH')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(8)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(8)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(8);
});

it('formats Saint Helena addresses with the code right of the locality', function (): void {
    $formatted = app(SaintHelenaAddressFormatter::class)->format(AddressData::from([
        'line1' => '95 MARKET STREET',
        'city' => 'JAMESTOWN',
        'postcode' => 'STHL 1ZZ',
        'country_code' => 'SH',
    ]));

    expect($formatted)->toBe("95 MARKET STREET\nJAMESTOWN STHL 1ZZ\nSaint Helena");
});

it('formats Ascension addresses with their own code', function (): void {
    $formatted = app(SaintHelenaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'PO Box 1',
        'city' => 'Georgetown',
        'postcode' => 'ASCN 1ZZ',
        'country_code' => 'SH',
    ]));

    expect($formatted)->toBe("PO Box 1\nGeorgetown ASCN 1ZZ\nSaint Helena");
});
