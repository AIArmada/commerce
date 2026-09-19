<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\SaintLucia\SaintLuciaAddressFormatter;
use AIArmada\Addressing\Geography\SaintLucia\SaintLuciaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(SaintLuciaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Saint Lucian tree with state links', function (): void {
    $this->seedCountry('LC');

    $result = app(SeedCountryGeographiesAction::class)->execute('LC');
    $country = AddressCountry::query()->where('iso2', 'LC')->firstOrFail();

    expect($result['seeded'])->toContain('LC')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(10)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(10);
});

it('formats Saint Lucian addresses with the postcode right of the locality', function (): void {
    $formatted = app(SaintLuciaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Block A, Apt 146',
        'line2' => 'High Street',
        'city' => 'CASTRIES',
        'postcode' => 'LC04  101',
        'country_code' => 'LC',
    ]));

    expect($formatted)->toBe("Block A, Apt 146\nHigh Street\nCASTRIES, LC04  101\nSaint Lucia");
});
it('formats Saint Lucian Choiseul addresses with the district postcode', function (): void {
    $formatted = app(SaintLuciaAddressFormatter::class)->format(AddressData::from([
        'line1' => 'Church Street',
        'city' => 'CHOISEUL',
        'postcode' => 'LC10  101',
        'country_code' => 'LC',
    ]));

    expect($formatted)->toBe("Church Street\nCHOISEUL, LC10  101\nSaint Lucia");
});
