<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\USVirginIslands\USVirginIslandsAddressFormatter;
use AIArmada\Addressing\Geography\USVirginIslands\USVirginIslandsGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(USVirginIslandsGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('district')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the US Virgin Islander tree with state links', function (): void {
    $this->seedCountry('VI');

    $result = app(SeedCountryGeographiesAction::class)->execute('VI');
    $country = AddressCountry::query()->where('iso2', 'VI')->firstOrFail();

    expect($result['seeded'])->toContain('VI')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(3)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'district')->count())->toBe(3)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(3);
});

it('formats US Virgin Islander addresses as locality VI ZIP', function (): void {
    $formatted = app(USVirginIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => '123 Main St.',
        'city' => 'ST THOMAS',
        'postcode' => '00802-1222',
        'country_code' => 'VI',
    ]));

    expect($formatted)->toBe("123 Main St.\nST THOMAS VI 00802-1222\nVirgin Islands (US)");
});
it('formats Kingshill addresses with the rural route ZIP', function (): void {
    $formatted = app(USVirginIslandsAddressFormatter::class)->format(AddressData::from([
        'line1' => 'RR 1 BOX 6601',
        'city' => 'KINGSHILL',
        'postcode' => '00850-9802',
        'country_code' => 'VI',
    ]));

    expect($formatted)->toBe("RR 1 BOX 6601\nKINGSHILL VI 00850-9802\nVirgin Islands (US)");
});
