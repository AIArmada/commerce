<?php

declare(strict_types=1);

use AIArmada\Addressing\Actions\SeedCountryGeographiesAction;
use AIArmada\Addressing\Data\AddressData;
use AIArmada\Addressing\Geography\Zambia\ZambiaAddressFormatter;
use AIArmada\Addressing\Geography\Zambia\ZambiaGeographyProvider;
use AIArmada\Addressing\Models\AddressArea;
use AIArmada\Addressing\Models\AddressAreaStateLink;
use AIArmada\Addressing\Models\AddressCountry;
use AIArmada\Addressing\Models\State;

it('defines a single-level administrative hierarchy', function (): void {
    $hierarchies = app(ZambiaGeographyProvider::class)->addressHierarchies();

    expect($hierarchies)->toHaveCount(1)
        ->and($hierarchies[0]->key)->toBe('administrative')
        ->and($hierarchies[0]->levels)->toHaveCount(1)
        ->and($hierarchies[0]->levels[0]->key)->toBe('province')
        ->and($hierarchies[0]->levels[0]->kind)->toBe('state');
});

it('imports the Zambian tree with state links', function (): void {
    $this->seedCountry('ZM');

    $result = app(SeedCountryGeographiesAction::class)->execute('ZM');
    $country = AddressCountry::query()->where('iso2', 'ZM')->firstOrFail();

    expect($result['seeded'])->toContain('ZM')
        ->and(State::query()->where('country_id', $country->id)->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('is_active', true)->count())->toBe(10)
        ->and(AddressArea::query()->where('country_id', $country->id)->where('type', 'province')->count())->toBe(10)
        ->and(AddressAreaStateLink::query()->whereHas(
            'addressArea',
            fn ($query) => $query->where('country_id', $country->id),
        )->count())->toBe(10);
});

it('formats Zambian addresses with the postcode right of the locality', function (): void {
    $formatted = app(ZambiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '21 Independence Avenue',
        'city' => 'KITWE',
        'postcode' => '23456',
        'country_code' => 'ZM',
    ]));

    expect($formatted)->toBe("21 Independence Avenue\nKITWE 23456\nZambia");
});
it('formats Zambian addresses omitting the routinely skipped postcode', function (): void {
    $formatted = app(ZambiaAddressFormatter::class)->format(AddressData::from([
        'line1' => '21 Independence Avenue',
        'city' => 'LUSAKA',
        'state' => 'Lusaka',
        'country_code' => 'ZM',
    ]));

    expect($formatted)->toBe("21 Independence Avenue\nLUSAKA\nZambia");
});
